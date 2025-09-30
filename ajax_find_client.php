<?php
// ajax_find_client.php
require_once 'inc/layout.php';

header('Content-Type: application/json');

if (isset($_GET['phone'])) {
    $phone = preg_replace('/\D/', '', $_GET['phone']);
    
    try {
        // Ищем клиента по телефону
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE REPLACE(REPLACE(phone, ' ', ''), '-', '') LIKE ?");
        $stmt->execute(['%' . $phone . '%']);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($client) {
            echo json_encode([
                'exists' => true,
                'id' => $client['id'],
                'full_name' => $client['full_name'],
                'phone' => $client['phone'],
                'email' => $client['email'],
                'address' => $client['address'],
                'company_name' => $client['company_name'] ?? '',
                'director' => $client['director'] ?? '',
                'inn' => $client['inn'] ?? '',
                'type' => $client['type'] ?? 'individual',
                'age_group_id' => $client['age_group_id'] ?? null,
                'source_id' => $client['source_id'] ?? null,
                'notes' => $client['notes'] ?? ''
            ]);
        } else {
            echo json_encode(['exists' => false]);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Phone parameter is required']);
}
?>