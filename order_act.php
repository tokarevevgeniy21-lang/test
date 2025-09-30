<?php
// order_act.php
require_once 'inc/layout.php';
requireAuth();

if (!isset($_GET['id'])) { header('Location: orders.php'); exit; }
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
if (!$o) { $_SESSION['error'] = 'Заказ не найден'; header('Location: orders.php'); exit; }

function safe($v){ return htmlspecialchars((string)$v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$created = $o['created_at'] ? date('d.m.Y', strtotime($o['created_at'])) : date('d.m.Y');
$device  = trim(implode(', ', array_filter([$o['brand_name'] ?? '', $o['model_name'] ?? '', $o['custom_device_model'] ?? ''])));

?><!doctype html>
<html lang="ru"><head>
<meta charset="utf-8"><title>Акт по заказу №<?= $id ?></title>
<style>
@page{ size:A4; margin:12mm; } @media print { .no-print{display:none} }
body{ font-family:Arial, sans-serif; color:#000; }
h1{ margin:0 0 6px; font-size:16pt; text-align:center; }
.small{ font-size:9pt; color:#333; text-align:center; margin:0 0 8px; }
.tbl{ width:100%; border-collapse:collapse; font-size:10pt; }
.tbl td, .tbl th{ border:1px solid #000; padding:4px 6px; }
.tbl th{ background:#eee; }
.sign{ margin-top:10mm; display:flex; justify-content:space-between; font-size:10pt; }
.btnbar{ text-align:center; margin:10px 0 16px; }
.btn{ padding:10px 18px; background:#007bff; color:#fff; text-decoration:none; border-radius:4px; }
.btn + .btn{ margin-left:8px; }
</style>
</head><body>

<div class="no-print btnbar">
  <a class="btn" href="act_pdf.php?id=<?= $id ?>" target="_blank">⬇️ PDF</a>
  <a class="btn" href="#" onclick="window.print()">🖨️ Печать</a>
</div>

<h1>Акт выполненных работ</h1>
<div class="small">Заказ №<?= $id ?> от <?= $created ?><br><b>ON Сервис, Заревый пр, д2</b> • +7 993 898 33 40</div>

<table class="tbl">
  <tr><td style="width:28%;"><b>Клиент</b></td><td><?= safe($o['client_name']) ?>, <?= safe($o['client_phone']) ?></td></tr>
  <tr><td><b>Устройство</b></td><td><?= safe($device ?: '-') ?></td></tr>
  <tr><td><b>Внешний вид</b></td><td><?= safe($o['appearance'] ?? 'б/у') ?></td></tr>
  <tr><td><b>Комплектация</b></td><td><?= safe(($o['accessories'] ?? '') ?: 'стандартная') ?></td></tr>
  <tr><td><b>Неисправность</b></td><td><?= nl2br(safe($o['problem_description'] ?? '')) ?></td></tr>
</table>

<br>
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

<div style="margin-top:8px; font-size:9pt;">
  <b>Условия гарантийного обслуживания</b>
  <ul style="margin:4px 0 0 16px; padding:0;">
    <li>Гарантия только на заявленную неисправность и при наличии неповреждённого стикера.</li>
    <li>Для деформаций/коррозии — 3 дня на проверку. Расширение до 3 месяцев — +20% от стоимости ремонта.</li>
  </ul>
</div>

<div class="sign">
  <div><b>Менеджер:</b> __________________</div>
  <div><b>Заказчик:</b> __________________ <?= safe(explode(' ', $o['client_name'])[0] ?? '') ?></div>
</div>

</body></html>