<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('orders:delete');


$orderId = (int)$_POST['order_id'];
$serviceId = (int)$_POST['service_id'];

// Получаем информацию об услуге
$service = getFromTable('order_services', '*', 'id = ? AND order_id = ?', [$serviceId, $orderId]);
if (!$service) {
    die('Услуга не найдена в заказе');
}

// Удаляем услугу из заказа
global $pdo;
$stmt = $pdo->prepare("DELETE FROM order_services WHERE id = ? AND order_id = ?");
if ($stmt->execute([$serviceId, $orderId])) {
    // Обновляем общую сумму заказа
    updateOrderTotalAmount($orderId);
    
    // Добавляем комментарий
    $serviceName = getFromTable('services', 'name', 'id = ?', [$service[0]['service_id']]);
    $serviceName = $serviceName[0]['name'] ?? 'Услуга';
    
    $comment = "Удалена услуга: {$serviceName}";
    addOrderComment($orderId, $_SESSION['user_id'], $comment);
    
    header("Location: order_view.php?id=$orderId");
    exit;
} else {
    die('Ошибка при удалении услуги');
}