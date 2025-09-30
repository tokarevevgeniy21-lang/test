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
    // Создаем функцию для проверки прав без прерывания выполнения
    $canIssueToMaster = hasPermission('parts:issue-to-master');
    if (!$canIssueToMaster) {
        $_SESSION['error'] = 'Недостаточно прав для выдачи запчастей мастеру';
        header("Location: order_view.php?id=$orderId");
        exit;
    }
}

// Проверяем наличие запчасти
$part = getFromTable('parts', 'sale_price, stock_quantity', 'id = ?', [$partId]);
if (!$part) {
    die('Запчасть не найдена');
}

$part = $part[0];
$price = $part['sale_price'];

// Начинаем транзакцию
global $pdo;
$pdo->beginTransaction();

try {
    // Если запчасть выдается мастеру, проверяем наличие и списываем
    if ($issuedToMaster) {
        if ($part['stock_quantity'] < $quantity) {
            throw new Exception("Недостаточно запчастей на складе. Доступно: {$part['stock_quantity']} шт.");
        }
        
        // Списываем со склада
        $stmt = $pdo->prepare("UPDATE parts SET stock_quantity = stock_quantity - ? WHERE id = ?");
        if (!$stmt->execute([$quantity, $partId])) {
            throw new Exception('Ошибка при списании запчасти со склада');
        }
        
        // Начисляем зарплату мастеру
        $order = getFromTable('orders', 'master_id', 'id = ?', [$orderId]);
        if ($order && $order[0]['master_id']) {
            $masterId = $order[0]['master_id'];
            $salaryAmount = ($price * $quantity) * 0.1; // 10% от стоимости
            
            $stmt = $pdo->prepare("
                INSERT INTO master_parts (master_id, order_id, amount, description, created_at) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)
            ");
            $description = "Начисление за запчасти к заказу #$orderId";
            $stmt->execute([$masterId, $orderId, $salaryAmount, $description]);
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
    updateOrderTotalAmount($orderId);
    
    // Добавляем комментарий
    $partName = getFromTable('parts', 'name', 'id = ?', [$partId]);
    $partName = $partName[0]['name'] ?? 'Запчасть';
    
    $comment = "Добавлена запчасть: {$partName} x{$quantity}";
    if ($issuedToMaster) {
        $comment .= " (выдано мастеру)";
    }
    
    addOrderComment($orderId, $_SESSION['user_id'], $comment);
    
    header("Location: order_view.php?id=$orderId");
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    die($e->getMessage());
}