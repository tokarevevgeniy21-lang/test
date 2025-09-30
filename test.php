<?php
require_once 'inc/config.php';
require_once 'inc/utils.php';

echo "<h1>Тест услуг и запчастей</h1>";

// Тестируем услуги
try {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $services = $stmt->fetchAll();
    
    echo "<h2>Услуги (" . count($services) . "):</h2>";
    foreach ($services as $service) {
        echo $service['id'] . " - " . $service['name'] . " - " . $service['price'] . " руб.<br>";
    }
} catch (PDOException $e) {
    echo "Ошибка услуг: " . $e->getMessage();
}

// Тестируем запчасти
try {
    $stmt = $pdo->prepare("SELECT * FROM parts WHERE is_active = 1 AND stock_quantity > 0 ORDER BY name");
    $stmt->execute();
    $parts = $stmt->fetchAll();
    
    echo "<h2>Запчасти (" . count($parts) . "):</h2>";
    foreach ($parts as $part) {
        echo $part['id'] . " - " . $part['name'] . " - " . $part['sale_price'] . " руб. (в наличии: " . $part['stock_quantity'] . ")<br>";
    }
} catch (PDOException $e) {
    echo "Ошибка запчастей: " . $e->getMessage();
}
?>