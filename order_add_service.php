<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('orders:edit');

$orderId = (int)$_POST['order_id'];
$serviceId = (int)$_POST['service_id'];
$quantity = (int)$_POST['quantity'];

// Проверяем обязательные поля
if (!$orderId || !$serviceId || !$quantity) {
    die('Не все обязательные поля заполнены');
}

try {
    // Проверяем услугу
    $stmt = $pdo->prepare("SELECT price, name FROM services WHERE id = ? AND is_active = 1");
    $stmt->execute([$serviceId]);
    $service = $stmt->fetch();
    
    if (!$service) {
        die('Услуга не найдена или неактивна');
    }

    // Добавляем услугу
    $stmt = $pdo->prepare("INSERT INTO order_services (order_id, service_id, quantity, price) VALUES (?, ?, ?, ?)");
    $result = $stmt->execute([$orderId, $serviceId, $quantity, $service['price']]);
    
    if ($result) {
        // Обновляем сумму заказа
        updateOrderTotalAmount($orderId);
        
        // Добавляем комментарий
        $comment = "Добавлена услуга: {$service['name']} x{$quantity}";
        addOrderComment($orderId, $_SESSION['user_id'], $comment);
        
        header("Location: order_view.php?id=$orderId");
        exit;
    } else {
        die('Ошибка при добавлении услуги');
    }
    
} catch (PDOException $e) {
    die('Ошибка базы данных: ' . $e->getMessage());
}