<?php
// ajax_get_order_details.php
require_once 'inc/layout.php';

if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$orderId = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT o.*, c.full_name as client_name, c.phone as client_phone,
               cat.name as category_name, b.name as brand_name,
               oi.name as item_name, oi.price, oi.quantity, oi.total_amount
        FROM orders o 
        LEFT JOIN clients c ON o.client_id = c.id 
        LEFT JOIN device_categories cat ON o.device_category_id = cat.id 
        LEFT JOIN brands b ON o.brand_id = b.id 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($order ?: []);
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}
?>