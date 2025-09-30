<?php
// cash_operations.php - Обработка кассовых операций
require_once 'inc/layout.php';


requireAuth();
requirePermission('cash:operations');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cash.php');
    exit;
}

$type = $_POST['operation_type'];
$amount = (float)$_POST['amount'];
$paymentMethod = $_POST['payment_method'];
$description = trim($_POST['description']);
$orderId = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;

try {
    $stmt = $pdo->prepare("
        INSERT INTO cash_operations (type, amount, payment_method, description, user_id, order_id, operation_date) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $type, 
        $amount, 
        $paymentMethod, 
        $description,
        $_SESSION['user_id'],
        $orderId
    ]);
    
    $_SESSION['success'] = 'Операция успешно проведена';
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Ошибка при проведении операции: ' . $e->getMessage();
}

header("Location: cash.php");
exit;
?>