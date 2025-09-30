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
    // Пробуем получить сумму из order_items
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
    
    // Если в order_items нет данных, пробуем получить из других таблиц
    if ($amounts['total_amount'] == 0) {
        // Сумма услуг из order_services
        $servicesStmt = $pdo->prepare("
            SELECT COALESCE(SUM(price * quantity), 0) as services_total 
            FROM order_services 
            WHERE order_id = ?
        ");
        $servicesStmt->execute([$orderId]);
        $servicesTotal = $servicesStmt->fetch(PDO::FETCH_ASSOC)['services_total'];
        
        // Сумма запчастей из order_parts
        $partsStmt = $pdo->prepare("
            SELECT COALESCE(SUM(price * quantity), 0) as parts_total 
            FROM order_parts 
            WHERE order_id = ?
        ");
        $partsStmt->execute([$orderId]);
        $partsTotal = $partsStmt->fetch(PDO::FETCH_ASSOC)['parts_total'];
        
        $totalAmount = $servicesTotal + $partsTotal;
        
        $amounts = [
            'total_amount' => $totalAmount,
            'services_amount' => $servicesTotal,
            'parts_amount' => $partsTotal
        ];
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