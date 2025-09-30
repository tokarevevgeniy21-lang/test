<?php

require_once 'inc/layout.php';

$masterId = 13; // ID вашего мастера
$orderId = 7526; // ID заказа
$partCost = 2000; // Стоимость запчасти
$quantity = 1; // Количество

$result = SalaryCalculator::deductForParts($masterId, $orderId, 'Тестовая запчасть', $partCost, $quantity);

echo "<pre>";
print_r($result);
echo "</pre>";

// Проверим баланс мастера
$balanceStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as balance FROM salary_payments WHERE user_id = ?");
$balanceStmt->execute([$masterId]);
$balance = $balanceStmt->fetch(PDO::FETCH_ASSOC);

echo "Текущий баланс мастера: " . $balance['balance'] . " руб.";
?>