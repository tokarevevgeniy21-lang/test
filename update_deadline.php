<?php
// update_deadline.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'inc/layout.php';


if (!isset($_SESSION['user_id'])) {
    die('Не авторизован');
}

$orderId = (int)($_POST['order_id'] ?? 0);
$deadline = $_POST['deadline'] ?? null;
$removeDeadline = isset($_POST['remove_deadline']);

if ($orderId === 0) {
    $_SESSION['error'] = 'Неверный ID заказа';
    header("Location: orders.php");
    exit;
}

global $pdo;
try {
    if ($removeDeadline) {
        // Удаляем дедлайн
        $stmt = $pdo->prepare("UPDATE orders SET deadline = NULL WHERE id = ?");
        $stmt->execute([$orderId]);
        $_SESSION['success'] = 'Дедлайн удален';
    } else {
        // Устанавливаем дедлайн
        if (empty($deadline)) {
            $_SESSION['error'] = 'Не указана дата дедлайна';
            header("Location: order_view.php?id=$orderId");
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE orders SET deadline = ? WHERE id = ?");
        $stmt->execute([$deadline, $orderId]);
        $_SESSION['success'] = 'Дедлайн установлен';
    }
    
    header("Location: order_view.php?id=$orderId");
    exit;
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
    header("Location: order_view.php?id=$orderId");
    exit;
}