<?php
// order_receipt.php
require_once 'inc/layout.php';
requireAuth();

if (!isset($_GET['id'])) { header("Location: orders.php"); exit; }
$orderId = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT o.*, c.full_name AS client_name, c.phone AS client_phone, c.address AS client_address,
               cat.name AS category_name, b.name AS brand_name, s.name AS status_name,
               u1.full_name AS manager_name, u2.full_name AS master_name,
               dm.name AS model_name
        FROM orders o 
        LEFT JOIN clients c ON o.client_id = c.id 
        LEFT JOIN device_categories cat ON o.device_category_id = cat.id 
        LEFT JOIN brands b ON o.brand_id = b.id 
        LEFT JOIN statuses s ON o.status_id = s.id 
        LEFT JOIN users u1 ON o.manager_id = u1.id 
        LEFT JOIN users u2 ON o.master_id = u2.id 
        LEFT JOIN device_models dm ON o.device_model_id = dm.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) { throw new Exception("Заказ не найден"); }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: orders.php"); exit;
}



$device = trim(implode(', ', array_filter([
    $order['category_name'] ?? '',
    $order['brand_name'] ?? '',
    ($order['custom_device_model'] ?? '') !== '' ? $order['custom_device_model'] : ($order['model_name'] ?? '')
])));
$created = $order['created_at'] ? date('d.m.Y', strtotime($order['created_at'])) : date('d.m.Y');
$clientFirst = explode(' ', (string)$order['client_name']);
$clientFirst = safe($clientFirst[0] ?? '');
$nowDate = date('d.m.Y');
$nowTime = date('H:i');

$printMode = isset($_GET['print']);
if ($printMode):
?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Квитанция №<?= $orderId ?></title>
<style>
/* --- НОВОЕ: Жёсткая вёрстка под А4 и две копии на странице --- */
:root{
  --font-base: 10pt;
  --font-small: 8.5pt;
  --line-tight: 1.15;
  --pad: 10px;
  --gap: 10px;
}
@page { size: A4; margin: 10mm; }
@media print {
  html,body { background:#fff; }
  .no-print { display:none !important; }
}
@media screen {
  body { background:#f5f5f5; margin:0; padding:20px; font-family: Arial, sans-serif; }
  .sheet { background:#fff; margin:0 auto; width:210mm; min-height:297mm; box-shadow:0 0 10px rgba(0,0,0,.1); }
}
body { font-family: Arial, sans-serif; color:#000; }
.sheet {
  box-sizing:border-box; padding:12mm;
  display:flex; flex-direction:column; gap:12mm;
}
.copy {
  border:1px solid #000; padding:8mm; box-sizing:border-box;
  display:flex; flex-direction:column; gap:8px;
}
.header { text-align:center; }
.header h1 { margin:0 0 2px; font-size: 13pt; line-height:1.2; }
.header p { margin:0; font-size: var(--font-small); }

.copy-label {
  text-align:center; font-weight:bold; font-size: 10pt;
  border-bottom:2px solid #000; padding-bottom:4px; margin-bottom:6px;
}
.table { width:100%; border-collapse:collapse; font-size: var(--font-base); line-height: var(--line-tight); }
.table td { border:1px solid #000; padding:3px 5px; vertical-align:top; }
.table td:first-child { width:27%; font-weight:bold; background:#f0f0f0; }

.block { font-size: var(--font-base); }
.block-title { font-weight:bold; margin:4px 0 2px; }
.box {
  border:1px solid #000; padding:4px; min-height:18px;
  font-size: var(--font-base); line-height: var(--line-tight);
  /* ВКЛ/ВЫКЛ обрезку, чтобы всегда влезло на 1 лист */
  display:-webkit-box; -webkit-line-clamp:6; -webkit-box-orient:vertical; overflow:hidden;
}

.conditions { font-size: 7pt; line-height:1.15; text-align:justify; }
.conditions ol { margin:4px 0; padding-left:14px; }
.conditions li { margin:2px 0; }

.row { display:flex; gap:10px; }
.col { flex:1; }
.sign { font-size: 9pt; margin-top:4px; }
.signline { display:flex; justify-content:space-between; gap:12px; margin-top:6px; }
.hr { border-top:1px solid #000; margin-top:6px; padding-top:4px; font-size: 8pt; }

.master-table { width:100%; border-collapse:collapse; font-size: var(--font-base); margin:6px 0; }
.master-table td{ border:1px solid #000; padding:4px; text-align:center; }
.cut { text-align:center; font-size:9pt; margin:2mm 0 -2mm; }
.small-muted { color:#666; font-size: 10pt; }

.btnbar.no-print { text-align:center; margin:10px 0 16px; }
.btn {
  padding:10px 18px; margin:0 6px; background:#007bff; color:#fff; border:none; border-radius:4px;
  cursor:pointer; text-decoration:none; display:inline-block; font-size:14px;
}
.btn.secondary { background:#6c757d; }
.btn.outline { background:#fff; color:#007bff; border:1px solid #007bff; }
</style>
</head>
<body>

<div class="btnbar no-print">
  <button class="btn" onclick="window.print()">🖨️ Печатать</button>
  <a class="btn outline" href="receipt_pdf.php?id=<?= $orderId ?>" target="_blank">⬇️ Экспорт в PDF</a>
  <button class="btn secondary" onclick="window.close()">✖ Закрыть</button>
  <div class="small-muted" style="margin-top:8px;">Окно печати откроется автоматически</div>
</div>

<div class="sheet">
  <!-- Копия для сервиса -->
  <div class="copy">
    <div class="copy-label">ЭКЗЕМПЛЯР ДЛЯ СЕРВИСА</div>
    <div class="header">
      <h1>Приёмная квитанция №<?= $orderId ?> от <?= $created ?></h1>
      <p><strong>ON Сервис, Заревый пр, д2</strong> • +7&nbsp;993&nbsp;898&nbsp;33&nbsp;40</p>
    </div>

    <table class="table">
      <tr><td>Клиент</td><td><?= safe($order['client_name']) ?>, <?= safe($order['client_phone']) ?></td></tr>
      <tr><td>Устройство</td><td><?= safe($device ?: '-') ?></td></tr>
      <tr><td>Внешний вид</td><td><?= safe($order['appearance'] ?? 'б/у') ?></td></tr>
      <tr><td>Комплектация</td><td><?= safe(($order['accessories'] ?? '') ?: 'стандартная') ?></td></tr>
      <tr>
        <td>Неисправность со слов клиента</td>
        <td><div class="box"><?= nl2br(safe($order['problem_description'] ?? '')) ?></div></td>
      </tr>
    </table>

    <div class="conditions">
      <strong>Условия оказания услуг</strong>
      <ol>
        <li>Диагностика — отдельная услуга, бесплатна даже при отказе от ремонта. Срок — 2–5 рабочих дней и может быть увеличен из-за сложности.</li>
        <li>СЦ вправе отказать в ремонте при невозможности, отсутствии запчастей/оборудования, выявлении доп. неисправностей и т. п.</li>
        <li>Гарантия распространяется только на заявленную неисправность при наличии неповреждённого стикера. Для устройств с деформациями/коррозией — 3 дня на проверку. Расширение до 3 месяцев — +20% к стоимости.</li>
        <li>Заменённая запчасть может быть выдана по предварительному требованию (если не разрушилась при снятии).</li>
        <li>Срок хранения — до 2 месяцев, далее — склад платного хранения с последующей утилизацией/реализацией для компенсации расходов.</li>
        <li>Данная квитанция является гарантийным талоном и собственностью сервиса, выдаётся при выполненном ремонте.</li>
        <li>Клиент согласен с возможным выявлением дополнительных неисправностей в ходе/после ремонта.</li>
        <li>Выдача техники — по квитанции; при утрате — владельцу по паспорту.</li>
      </ol>
    </div>

    <div class="signline">
      <div class="col sign">
        <p><strong>Менеджер:</strong> __________________</p>
        <p><strong>С условиями оказания услуг ознакомлен и согласен</strong></p>
      </div>
      <div class="col sign" style="text-align:right;">
        <p><strong>Заказчик:</strong> __________________</p>
        <p><?= $clientFirst ?></p>
      </div>
    </div>

    <div class="hr">
      <strong>Статус заказа</strong><br>
      <strong>Устройство получил(а), претензий не имею, комплектацию и внешний вид проверил(а)</strong><br>
      Дата: <?= $nowDate ?> &nbsp; <strong>Заказчик:</strong> __________________ <?= $clientFirst ?>
      <div style="text-align:right;"><?= $nowTime ?></div>
    </div>
  </div>

  <div class="cut">✂ ———————————————————————————————————————————————————————————————————————————————</div>

  <!-- Копия для клиента -->
  <div class="copy">
    <div class="copy-label">ЭКЗЕМПЛЯР ДЛЯ КЛИЕНТА</div>
    <div class="header">
      <h1>Приёмная квитанция №<?= $orderId ?> от <?= $created ?></h1>
      <p><strong>ON Сервис, Заревый пр, д2</strong> • +7&nbsp;993&nbsp;898&nbsp;33&nbsp;40</p>
    </div>

    <table class="master-table">
      <tr><td><strong>Мастер</strong></td><td><strong>Выполненные работы</strong></td><td><strong>Сумма ремонта</strong></td></tr>
      <tr><td>__________________</td><td>__________________</td><td>__________________</td></tr>
    </table>

    <table class="table">
      <tr><td>Клиент</td><td><?= safe($order['client_name']) ?>, <?= safe($order['client_phone']) ?></td></tr>
      <tr><td>Устройство</td><td><?= safe($device ?: '-') ?></td></tr>
      <tr><td>Внешний вид</td><td><?= safe($order['appearance'] ?? 'б/у') ?></td></tr>
    </table>

    <div class="block">
      <div class="block-title"><strong>Комплектация</strong></div>
      <div class="box"><?= safe(($order['accessories'] ?? '') ?: 'стандартная') ?></div>
    </div>

    <div class="block">
      <div class="block-title"><strong>Неисправность со слов клиента</strong></div>
      <div class="box"><?= nl2br(safe($order['problem_description'] ?? '')) ?></div>
    </div>

    <div class="signline" style="margin-top:8px;">
      <div class="col sign">
        <p><strong>Менеджер:</strong> __________________</p>
        <p><strong>Дата:</strong> <?= date('d.m.Y H:i') ?></p>
      </div>
      <div class="col sign" style="text-align:right;">
        <p><strong>С условиями оказания услуг ознакомлен и согласен</strong></p>
        <p><strong>Заказчик:</strong> __________________</p>
      </div>
    </div>

    <div class="hr">
      <strong>Устройство получил(а), претензий не имею, комплектацию и внешний вид проверил(а)</strong><br>
      <strong>Заказчик:</strong> __________________ <?= $clientFirst ?>
    </div>
  </div>
</div>

<script>
window.onload = () => { setTimeout(() => window.print(), 400); };
window.onafterprint = () => { setTimeout(() => window.close(), 600); };
</script>
</body>
</html>
<?php
exit; // конец print-режима
endif;

// Обычный режим – страница с кнопками
renderHeader('Квитанция #' . $orderId);
?>
<div style="text-align:center; margin:50px;">
  <h2>Квитанция №<?= $orderId ?> готова</h2>
  <a class="btn" href="order_receipt.php?id=<?= $orderId ?>&print=1" target="_blank"
     style="display:inline-block; padding:14px 26px; background:#007bff; color:#fff; border-radius:6px; text-decoration:none;">
     🖨️ Печать на A4 (2 копии)
  </a>
  <a class="btn" href="receipt_pdf.php?id=<?= $orderId ?>" target="_blank"
     style="display:inline-block; padding:14px 26px; background:#198754; color:#fff; border-radius:6px; text-decoration:none; margin-left:8px;">
     ⬇️ Экспорт в PDF
  </a>
  <p style="margin-top:16px; color:#666">Откроется новое окно; после печати закроется автоматически.</p>
</div>
<?php renderFooter(); ?>