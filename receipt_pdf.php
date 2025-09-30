<?php
// receipt_pdf.php
require_once 'inc/layout.php';
requireAuth();

if (!isset($_GET['id'])) { exit('Missing id'); }
$orderId = (int)$_GET['id'];

// Подтянем данные (тот же запрос)
$stmt = $pdo->prepare("
    SELECT o.*, c.full_name AS client_name, c.phone AS client_phone, c.address AS client_address,
           cat.name AS category_name, b.name AS brand_name,
           dm.name AS model_name
    FROM orders o 
    LEFT JOIN clients c ON o.client_id = c.id 
    LEFT JOIN device_categories cat ON o.device_category_id = cat.id 
    LEFT JOIN brands b ON o.brand_id = b.id 
    LEFT JOIN device_models dm ON o.device_model_id = dm.id 
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) { exit('Order not found'); }

function safe($v){ return htmlspecialchars((string)$v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$device = trim(implode(', ', array_filter([
    $order['category_name'] ?? '',
    $order['brand_name'] ?? '',
    ($order['custom_device_model'] ?? '') !== '' ? $order['custom_device_model'] : ($order['model_name'] ?? '')
])));
$created = $order['created_at'] ? date('d.m.Y', strtotime($order['created_at'])) : date('d.m.Y');
$clientFirst = explode(' ', (string)$order['client_name']); $clientFirst = safe($clientFirst[0] ?? '');
$nowDate = date('d.m.Y'); $nowTime = date('H:i');

require_once __DIR__ . '/tcpdf/tcpdf.php';

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('ON Service CRM');
$pdf->SetAuthor('ON Service');
$pdf->SetTitle("Квитанция №$orderId");
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

// Стили под печать (две копии на странице)
$html = <<<HTML
<style>
  .wrapper{font-family:dejavusans, arial, sans-serif; font-size:10pt;}
  .copy{border:1px solid #000; padding:6mm; margin-bottom:6mm;}
  .label{font-weight:bold; text-align:center; border-bottom:2px solid #000; padding-bottom:2mm; margin-bottom:3mm;}
  .h1{font-size:13pt; font-weight:bold; text-align:center; margin:0 0 2mm;}
  .center{ text-align:center; }
  table.tbl{ width:100%; border-collapse:collapse; }
  .tbl td{ border:1px solid #000; padding:2mm; vertical-align:top; }
  .tbl td.first{ width:28%; font-weight:bold; background:#eee; }
  ol{ margin:1mm 0 0 5mm; padding:0; }
  li{ margin:0 0 1mm 0; }
  .small{ font-size:8pt; }
</style>
<div class="wrapper">
  <div class="copy">
    <div class="label">ЭКЗЕМПЛЯР ДЛЯ СЕРВИСА</div>
    <div class="h1">Приёмная квитанция №{$orderId} от {$created}</div>
    <div class="center small"><b>ON Сервис, Заревый пр, д2</b> • +7 993 898 33 40</div>
    <br/>
    <table class="tbl">
      <tr><td class="first">Клиент</td><td>{safe($order['client_name'])}, {safe($order['client_phone'])}</td></tr>
      <tr><td class="first">Устройство</td><td>{safe($device ?: '-')}</td></tr>
      <tr><td class="first">Внешний вид</td><td>{safe($order['appearance'] ?? 'б/у')}</td></tr>
      <tr><td class="first">Комплектация</td><td>{safe(($order['accessories'] ?? '') ?: 'стандартная')}</td></tr>
      <tr><td class="first">Неисправность со слов клиента</td><td>{nl2br(safe($order['problem_description'] ?? ''))}</td></tr>
    </table>
    <br/>
    <div class="small"><b>Условия оказания услуг</b>
      <ol>
        <li>Диагностика — отдельная услуга, бесплатна даже при отказе от ремонта. Срок — 2–5 рабочих дней, может быть увеличен.</li>
        <li>СЦ вправе отказать в ремонте при невозможности, отсутствии запчастей/оборудования и т. п.</li>
        <li>Гарантия только на заявленную неисправность; для деформаций/коррозии — 3 дня (проверка). Расширение до 3 мес. — +20%.</li>
        <li>Заменённая запчасть — по предварительному требованию (если не разрушилась при снятии).</li>
        <li>Хранение — 2 мес., далее платное хранение и последующая утилизация/реализация.</li>
        <li>Квитанция — гарантийный талон и собственность сервиса, выдаётся при выполненном ремонте.</li>
        <li>Клиент согласен с возможным выявлением доп. неисправностей в ходе/после ремонта.</li>
        <li>Выдача — по квитанции; при утрате — владельцу по паспорту.</li>
      </ol>
    </div>
    <br/>
    <table style="width:100%;">
      <tr>
        <td style="width:60%; font-size:9pt;"><b>Менеджер:</b> __________________<br/><b>С условиями оказания услуг ознакомлен и согласен</b></td>
        <td style="width:40%; text-align:right; font-size:9pt;"><b>Заказчик:</b> __________________<br/>{$clientFirst}</td>
      </tr>
    </table>
    <hr/>
    <div class="small">
      <b>Статус заказа</b><br/>
      <b>Устройство получил(а), претензий не имею, комплектацию и внешний вид проверил(а)</b><br/>
      Дата: {$nowDate} &nbsp;&nbsp; <b>Заказчик:</b> __________________ {$clientFirst}
      <div style="text-align:right;">{$nowTime}</div>
    </div>
  </div>

  <div class="copy">
    <div class="label">ЭКЗЕМПЛЯР ДЛЯ КЛИЕНТА</div>
    <div class="h1">Приёмная квитанция №{$orderId} от {$created}</div>
    <div class="center small"><b>ON Сервис, Заревый пр, д2</b> • +7 993 898 33 40</div>
    <br/>
    <table class="tbl" style="text-align:center;">
      <tr><td><b>Мастер</b></td><td><b>Выполненные работы</b></td><td><b>Сумма ремонта</b></td></tr>
      <tr><td>__________________</td><td>__________________</td><td>__________________</td></tr>
    </table>
    <br/>
    <table class="tbl">
      <tr><td class="first">Клиент</td><td>{safe($order['client_name'])}, {safe($order['client_phone'])}</td></tr>
      <tr><td class="first">Устройство</td><td>{safe($device ?: '-')}</td></tr>
      <tr><td class="first">Внешний вид</td><td>{safe($order['appearance'] ?? 'б/у')}</td></tr>
    </table>
    <br/>
    <div><b>Комплектация</b><br/><div style="border:1px solid #000; padding:3mm;">{safe(($order['accessories'] ?? '') ?: 'стандартная')}</div></div>
    <br/>
    <div><b>Неисправность со слов клиента</b><br/><div style="border:1px solid #000; padding:3mm;">{nl2br(safe($order['problem_description'] ?? ''))}</div></div>
    <br/>
    <table style="width:100%; font-size:9pt;">
      <tr>
        <td style="width:60%;"><b>Менеджер:</b> __________________<br/><b>Дата:</b> {$nowDate} {$nowTime}</td>
        <td style="width:40%; text-align:right;"><b>С условиями оказания услуг ознакомлен и согласен</b><br/><b>Заказчик:</b> __________________</td>
      </tr>
    </table>
    <hr/>
    <div class="small"><b>Устройство получил(а), претензий не имею, комплектацию и внешний вид проверил(а)</b><br/><b>Заказчик:</b> __________________ {$clientFirst}</div>
  </div>
</div>
HTML;

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output("receipt_{$orderId}.pdf", 'I'); // I = inline в браузере