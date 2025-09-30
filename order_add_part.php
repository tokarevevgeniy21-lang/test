<?php
require_once 'inc/layout.php';
requireAuth();
requirePermission('orders:edit');

$orderId = (int)$_POST['order_id'];
$partId = (int)$_POST['part_id'];
$quantity = (int)$_POST['quantity'];
$issuedToMaster = isset($_POST['issued_to_master']) ? 1 : 0;

// Проверяем права для выдачи запчастей мастеру
if ($issuedToMaster) {
    $canIssueToMaster = hasPermission('parts:issue-to-master');
    if (!$canIssueToMaster) {
        $_SESSION['error'] = 'Недостаточно прав для выдачи запчастей мастеру';
        header("Location: order_view.php?id=$orderId");
        exit;
    }
}

// Проверяем наличие запчасти
$part = getFromTable('parts', 'sale_price, stock_quantity, name', 'id = ?', [$partId]);
if (!$part) {
    $_SESSION['error'] = 'Запчасть не найдена';
    header("Location: order_view.php?id=$orderId");
    exit;
}

$part = $part[0];
$price = $part['sale_price'];
$partName = $part['name'];

// Начинаем транзакцию
global $pdo;
$pdo->beginTransaction();

try {
    // Если запчасть выдается мастеру, проверяем наличие и списываем
    if ($issuedToMaster) {
        if ($part['stock_quantity'] < $quantity) {
            throw new Exception("Недостаточно запчастей на складе. Доступно: {$part['stock_quantity']} шт.");
        }
        
        // Получаем мастера заказа
        $order = getFromTable('orders', 'master_id', 'id = ?', [$orderId]);
        if (!$order || !$order[0]['master_id']) {
            throw new Exception("Не назначен мастер для заказа. Нельзя выдать запчасть.");
        }
        $masterId = $order[0]['master_id'];
        
        // 1. Списываем со склада
        $stmt = $pdo->prepare("UPDATE parts SET stock_quantity = stock_quantity - ? WHERE id = ?");
        if (!$stmt->execute([$quantity, $partId])) {
            throw new Exception('Ошибка при списании запчасти со склада');
        }
        
        // 2. Добавляем запись в master_parts (УБРАЛ НАЧИСЛЕНИЕ ЗАРПЛАТЫ)
        $stmt = $pdo->prepare("
            INSERT INTO master_parts (master_id, part_id, quantity, order_id, issue_date, status) 
            VALUES (?, ?, ?, ?, CURDATE(), 'issued')
        ");
        if (!$stmt->execute([$masterId, $partId, $quantity, $orderId])) {
            throw new Exception('Ошибка при записи выдачи запчасти мастеру');
        }
        
    }
    
    // Добавляем запчасть к заказу
    $stmt = $pdo->prepare("INSERT INTO order_parts (order_id, part_id, quantity, price, issued_to_master) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt->execute([$orderId, $partId, $quantity, $price, $issuedToMaster])) {
        throw new Exception('Ошибка при добавлении запчасти');
    }
    
    // Коммитим транзакцию
    $pdo->commit();
    
    // Обновляем общую сумму заказа
    if (function_exists('updateOrderTotalAmount')) {
        updateOrderTotalAmount($orderId);
    }
    
    // Добавляем комментарий
    $comment = "Добавлена запчасть: {$partName} x{$quantity}";
    if ($issuedToMaster) {
        $master = getFromTable('users', 'full_name', 'id = ?', [$masterId]);
        $masterName = $master[0]['full_name'] ?? 'Мастер';
        $comment .= " (выдано мастеру: {$masterName})";
    }
    
    addOrderComment($orderId, $_SESSION['user_id'], $comment);
    
    $_SESSION['success'] = 'Запчасть успешно добавлена к заказу' . ($issuedToMaster ? ' и выдана мастеру' : '');
    header("Location: order_view.php?id=$orderId");
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = $e->getMessage();
    header("Location: order_view.php?id=$orderId");
    exit;
}