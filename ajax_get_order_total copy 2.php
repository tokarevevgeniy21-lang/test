<?php
// ajax_get_order_total.php - Получение суммы заказа
require_once 'inc/layout.php';

if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Не указан ID заказа']);
    exit;
}

$orderId = (int)$_GET['id'];

try {
    // Получаем общую сумму из позиций заказа
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_amount,
            COALESCE(SUM(CASE WHEN type = 'service' THEN total_amount ELSE 0 END), 0) as services_amount,
            COALESCE(SUM(CASE WHEN type = 'part' THEN total_amount ELSE 0 END), 0) as parts_amount
        FROM order_items 
        WHERE order_id = ?
    ");
    $stmt->execute([$orderId]);
    $amounts = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Если нет позиций, пытаемся получить сумму из других источников
    if ($amounts['total_amount'] == 0) {
        $orderStmt = $pdo->prepare("
            SELECT o.*, c.full_name as client_name
            FROM orders o 
            LEFT JOIN clients c ON o.client_id = c.id 
            WHERE o.id = ?
        ");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // Можно добавить логику расчета суммы на основе других полей
            $amounts['total_amount'] = 0; // По умолчанию 0
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'total_amount' => (float)$amounts['total_amount'],
        'services_amount' => (float)$amounts['services_amount'],
        'parts_amount' => (float)$amounts['parts_amount']
    ]);
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}
?>