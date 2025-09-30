<?php
require_once 'inc/layout.php';

requireAuth();

echo "<h3>Структура таблицы orders</h3>";

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM orders");
    $columns = $stmt->fetchAll();
    
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>{$column['Field']} ({$column['Type']})</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Ошибка: " . $e->getMessage() . "</p>";
}