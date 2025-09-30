<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('orders:delete');

$orderId = (int)$_POST['order_id'];
$partId = (int)$_POST['part_id'];

// Получаем информацию о запчасти в заказе
$part = getFromTable('order_parts', '*', 'id = ? AND order_id = ?', [$partId, $orderId]);
if (!$part) {
    die('Запчасть не найдена в заказе');
}

$part = $part[0];

// Начинаем транзакцию
global $pdo;
$pdo->beginTransaction();

try {
    // Если запчасть была выдана мастеру, вернем ее на склад
    if ($part['issued_to_master']) {
        $stmt = $pdo->prepare("UPDATE parts SET stock_quantity = stock_quantity + ? WHERE id = ?");
        $stmt->execute([$part['quantity'], $part['part_id']]);
    }

    // Удаляем запчасть из заказа
    $stmt = $pdo->prepare("DELETE FROM order_parts WHERE id = ? AND order_id = ?");
    $stmt->execute([$partId, $orderId]);
    
    // Обновляем общую сумму заказа
    updateOrderTotalAmount($orderId);
    
    // Добавляем комментарий
    $partName = getFromTable('parts', 'name', 'id = ?', [$part['part_id']]);
    $partName = $partName[0]['name'] ?? 'Запчасть';
    
    $comment = "Удалена запчасть: {$partName} x{$part['quantity']}";
    addOrderComment($orderId, $_SESSION['user_id'], $comment);
    
    $pdo->commit();
    
    header("Location: order_view.php?id=$orderId");
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    die('Ошибка при удалении запчасти: ' . $e->getMessage());
}