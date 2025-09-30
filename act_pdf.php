<?php
require_once 'inc/layout.php';
requireAuth();
if (!isset($_GET['id'])) { exit('Missing id'); }
$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
  SELECT o.*, c.full_name AS client_name, c.phone AS client_phone,
         dm.name AS model_name, b.name AS brand_name
  FROM orders o
  LEFT JOIN clients c ON c.id = o.client_id
  LEFT JOIN device_models dm ON dm.id = o.device_model_id
  LEFT JOIN brands b ON b.id = o.brand_id
  WHERE o.id = ?
");
$stmt->execute([$id]);
$o = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$o) { exit('Order not found'); }

function safe($v){ return htmlspecialchars((string)$v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$created = $o['created_at'] ? date('d.m.Y', strtotime($o['created_at'])) : date('d.m.Y');
$device  = trim(implode(', ', array_filter([$o['brand_name'] ?? '', $o['model_name'] ?? '', $o['custom_device_model'] ?? ''])));

require_once __DIR__ . '/tcpdf/tcpdf.php';
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('ON Service CRM'); $pdf->SetAuthor('ON Service'); $pdf->SetTitle("Акт №$id");
$pdf->SetMargins(10,10,10); $pdf->SetAutoPageBreak(true,10); $pdf->AddPage();

$html = <<<HTML
<style>
  .w{font-family:dejavusans, arial, sans-serif; font-size:10pt;}
  .h1{font-size:16pt; font-weight:bold; text-align:center; margin:0 0 2mm;}
  .small{font-size:9pt; text-align:center; color:#333; margin:0 0 3mm;}
  table.tbl{width:100%; border-collapse:collapse;}
  .tbl td,.tbl th{border:1px solid #000; padding:2mm;}
  .tbl th{background:#eee;}
</style>
<div class="w">
  <div class="h1">Акт выполненных работ</div>
  <div class="small">Заказ №{$id} от {$created}<br/><b>ON Сервис, Заревый пр, д2</b> • +7 993 898 33 40</div>

  <table class="tbl">
    <tr><td style="width:28%;"><b>Клиент</b></td><td>{safe($o['client_name'])}, {safe($o['client_phone'])}</td></tr>
    <tr><td><b>Устройство</b></td><td>{safe($device ?: '-')}</td></tr>
    <tr><td><b>Внешний вид</b></td><td>{safe($o['appearance'] ?? 'б/у')}</td></tr>
    <tr><td><b>Комплектация</b></td><td>{safe(($o['accessories'] ?? '') ?: 'стандартная')}</td></tr>
    <tr><td><b>Неисправность</b></td><td>{nl2br(safe($o['problem_description'] ?? ''))}</td></tr>
  </table>
  <br/>
  <table class="tbl">
    <thead><tr>
      <th style="width:8%;">№</th>
      <th>Позиция</th>
      <th style="width:18%;">Артикул</th>
      <th style="width:12%;">Гарантия, дн.</th>
      <th style="width:12%;">Цена, ₽</th>
      <th style="width:12%;">Скидка, ₽</th>
      <th style="width:12%;">Кол-во</th>
      <th style="width:14%;">Сумма, ₽</th>
    </tr></thead>
    <tbody>
      <tr><td>1</td><td>__________________</td><td>________</td><td>30</td><td>0,00</td><td>0,00</td><td>1</td><td>0,00</td></tr>
    </tbody>
    <tfoot>
      <tr><td colspan="7" style="text-align:right;"><b>Итого</b></td><td><b>0,00</b></td></tr>
    </tfoot>
  </table>

  <br/>
  <div style="font-size:9pt;">
    <b>Условия гарантийного обслуживания</b>
    <ul style="margin:2mm 0 0 6mm; padding:0;">
      <li>Гарантия только на заявленную неисправность и при наличии неповреждённого стикера.</li>
      <li>Для деформаций/коррозии — 3 дня (проверка). Расширение до 3 мес. — +20% от стоимости ремонта.</li>
    </ul>
  </div>

  <br/><br/>
  <table style="width:100%; font-size:10pt;">
    <tr>
      <td><b>Менеджер:</b> __________________</td>
      <td style="text-align:right;"><b>Заказчик:</b> __________________ {safe(explode(' ', $o['client_name'])[0] ?? '')}</td>
    </tr>
  </table>
</div>
HTML;

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output("act_{$id}.pdf", 'I');