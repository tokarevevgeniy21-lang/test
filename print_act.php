<?php
// print_act.php - Печать акта выполненных работ
require_once 'inc/layout.php';

requireAuth();

if (!isset($_GET['order_id'])) {
    die('Не указан ID заказа');
}

$orderId = (int)$_GET['order_id'];

// Получаем данные заказа
try {
    $stmt = $pdo->prepare("
        SELECT o.*, c.full_name as client_name, c.phone as client_phone, 
               cat.name as category_name, b.name as brand_name,
               u.full_name as manager_name, s.name as status_name,
               oi.issued_at, oi.payment_method, oi.final_amount
        FROM orders o 
        LEFT JOIN clients c ON o.client_id = c.id 
        LEFT JOIN device_categories cat ON o.device_category_id = cat.id 
        LEFT JOIN brands b ON o.brand_id = b.id 
        LEFT JOIN users u ON o.manager_id = u.id 
        LEFT JOIN statuses s ON o.status_id = s.id 
        LEFT JOIN order_issuance oi ON o.id = oi.order_id 
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception("Заказ не найден");
    }
    
    // Получаем позиции заказа
    $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY type, created_at");
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Рассчитываем итоговую сумму
    $totalAmount = 0;
    foreach ($items as $item) {
        $totalAmount += $item['total_amount'];
    }
    
} catch (Exception $e) {
    die('Ошибка: ' . $e->getMessage());
}

// Устанавливаем заголовки для печати
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Акт выполненных работ #<?= $orderId ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-family: Arial, sans-serif; font-size: 12pt; margin: 0; padding: 20px; }
            .act { border: 2px solid #000; padding: 20px; margin: 10px; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { border: 1px solid #000; padding: 8px; text-align: left; }
            th { background: #f0f0f0; font-weight: bold; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .signature-area { margin-top: 50px; }
            .signature-line { display: flex; justify-content: space-between; margin-bottom: 40px; }
            .conditions { font-size: 10pt; margin-top: 20px; }
        }
        @media screen {
            body { background: #f5f5f5; padding: 20px; }
            .act { background: white; padding: 30px; max-width: 210mm; margin: 0 auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        }
        .act-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
        .client-info { margin-bottom: 20px; }
        .client-info table { width: 100%; }
        .client-info td { padding: 5px 10px; border: none; }
        .client-info td:first-child { font-weight: bold; width: 30%; }
        .total-row { font-weight: bold; background: #f0f0f0; }
        .no-print { text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Печать</button>
        <button onclick="window.close()" class="btn btn-secondary">Закрыть</button>
    </div>

    <div class="act">
        <div class="act-header">
            <h1>Акт выполненных работ</h1>
            <h2>Заказ №<?= $orderId ?> от <?= date('d.m.Y', strtotime($order['created_at'])) ?></h2>
            <p><strong>ON Сервис, Заревый проезд, дом 2.</strong><br>+7 993 898 33 40</p>
        </div>

        <div class="client-info">
            <table>
                <tr>
                    <td>Клиент</td>
                    <td><?= htmlspecialchars($order['client_name']) ?>, <?= htmlspecialchars($order['client_phone']) ?></td>
                </tr>
                <tr>
                    <td>Устройство</td>
                    <td>
                        <?= htmlspecialchars($order['category_name']) ?>, 
                        <?= htmlspecialchars($order['brand_name']) ?>,
                        <?= htmlspecialchars($order['device_model']) ?>
                    </td>
                </tr>
                <tr>
                    <td>Внешний вид</td>
                    <td>б/у, оплетка повреждена</td>
                </tr>
                <tr>
                    <td>Комплектация</td>
                    <td><?= !empty($order['accessories']) ? htmlspecialchars($order['accessories']) : 'стандартная' ?></td>
                </tr>
                <tr>
                    <td>Неисправность</td>
                    <td><?= htmlspecialchars($order['problem_description']) ?></td>
                </tr>
            </table>
        </div>

        <table>
            <thead>
                <tr>
                    <th>№</th>
                    <th>Позиция</th>
                    <th>Артикул</th>
                    <th>Гарантия, дн.</th>
                    <th>Цена, ₽</th>
                    <th>Скидка, ₽</th>
                    <th>Количество</th>
                    <th>Сумма, ₽</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="8" class="text-center">Нет выполненных работ</td>
                    </tr>
                <?php else: ?>
                    <?php $counter = 1; ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= htmlspecialchars($item['article'] ?? '-') ?></td>
                        <td class="text-center"><?= $item['warranty_days'] ?></td>
                        <td class="text-right"><?= number_format($item['price'], 2) ?></td>
                        <td class="text-right"><?= number_format($item['discount'], 2) ?></td>
                        <td class="text-center"><?= $item['quantity'] ?></td>
                        <td class="text-right"><?= number_format($item['total_amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="7" class="text-right"><strong>ИТОГО:</strong></td>
                    <td class="text-right"><strong><?= number_format($totalAmount, 2) ?> ₽</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="conditions">
            <p><strong>Условия гарантийного обслуживания</strong></p>
            <p>1. ГАРАНТИЙНОЕ ОБСЛУЖИВАНИЕ распространяется только на ремонт заявленной неисправности и при наличии неповреждённого гарантийного стикера. Гарантия на ремонт аппаратов, попавших под воздействие агрессивной среды (воды и т.д.), имеющие внешние, внутренние повреждения, или видимые деформации корпуса, составляет 3 дня (на проверку). Стоимость РАСШИРЕНИЯ ГАРАНТИИ до трех месяцев составляет 20% от стоимости ремонта.</p>
        </div>

        <div class="signature-area">
            <div class="signature-line">
                <div>
                    <p>Менеджер: __________________</p>
                    <p>(подпись)</p>
                </div>
                <div>
                    <p>Заказчик: __________________ <?= htmlspecialchars(explode(' ', $order['client_name'])[0]) ?></p>
                    <p>(подпись)</p>
                </div>
            </div>
            <div class="text-center">
                <p>с условиями гарантийного обслуживания ознакомлен и согласен</p>
                <p><strong>Дата: <?= date('d.m.Y H:i') ?></strong></p>
            </div>
        </div>
    </div>

    <script>
        // Автопечать при открытии
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>