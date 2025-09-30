<?php
// order_issue.php - Обработка выдачи заказа
require_once 'inc/layout.php';

requireAuth();
requirePermission('orders:issue');

// ПРОВЕРКА АКТИВНОЙ СМЕНЫ
$activeShift = getActiveCashShift($_SESSION['user_id']);
if (!$activeShift) {
    $_SESSION['error'] = 'Для выдачи заказов необходимо открыть кассовую смену';
    header('Location: cash.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cash.php');
    exit;
}

$orderId = (int)$_POST['order_id'];
$finalAmount = (float)$_POST['final_amount'];
$paymentMethod = $_POST['payment_method'];
$issueComment = trim($_POST['issue_comment'] ?? '');

try {
    // Начинаем транзакцию
    $pdo->beginTransaction();
    
    // 1. Получаем информацию о заказе (ВОТ ИСПРАВЛЕНИЕ - добавляем master_id)
    $orderStmt = $pdo->prepare("
        SELECT o.*, c.full_name as client_name, u.id as master_id, u.full_name as master_name
        FROM orders o 
        LEFT JOIN clients c ON o.client_id = c.id 
        LEFT JOIN users u ON o.master_id = u.id  -- Добавляем join с таблицей users
        WHERE o.id = ?
    ");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Заказ не найден');
    }
    
    // Проверяем что заказ в правильном статусе
    if (!in_array($order['status_id'], [4, 18])) {
        throw new Exception('Заказ не готов к выдаче');
    }
    
    // 2. Определяем новый статус
    if ($finalAmount > 0) {
        $newStatusId = 5; // Выдан
    } else {
        $newStatusId = 10; // Выдан Б.Р.
    }
    
    // 3. Записываем выдачу заказа
    $issueStmt = $pdo->prepare("
        INSERT INTO order_issuance (order_id, issued_by, payment_method, final_amount, issue_comment) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $issueStmt->execute([$orderId, $_SESSION['user_id'], $paymentMethod, $finalAmount, $issueComment]);
    
    // 4. Обновляем статус заказа
    $updateOrderStmt = $pdo->prepare("UPDATE orders SET status_id = ? WHERE id = ?");
    $updateOrderStmt->execute([$newStatusId, $orderId]);
    
    // 5. Если есть оплата - записываем в кассу
    if ($finalAmount > 0 && $paymentMethod !== 'warranty') {
        $cashStmt = $pdo->prepare("
            INSERT INTO cash_operations (type, amount, payment_method, description, user_id, order_id, shift_id) 
            VALUES ('income', ?, ?, ?, ?, ?, ?)
        ");
        
        $description = "Оплата заказа #{$orderId} - " . $order['client_name'];
        $cashStmt->execute([
            $finalAmount, 
            $paymentMethod, 
            $description,
            $_SESSION['user_id'],
            $orderId,
            $activeShift['id']
        ]);
    }
        // 8. Автоматически выдаем все запчасти мастеру при выдаче заказа
if (!empty($order['master_id'])) {
    $autoIssueStmt = $pdo->prepare("
        UPDATE order_parts 
        SET issued_to_master = 1, 
            issued_date = NOW(),
            issued_by = ?,
            master_id = ?
        WHERE order_id = ? AND issued_to_master = 0
    ");
    $autoIssueStmt->execute([$_SESSION['user_id'], $order['master_id'], $orderId]);
    
    $issuedParts = $autoIssueStmt->rowCount();
    if ($issuedParts > 0) {
        $partsComment = "🔧 Автоматически выдано запчастей мастеру: {$issuedParts} шт.";
        $partsCommentStmt = $pdo->prepare("INSERT INTO order_comments (order_id, user_id, comment) VALUES (?, ?, ?)");
        $partsCommentStmt->execute([$orderId, $_SESSION['user_id'], $partsComment]);
    }
}
    // 6. Рассчитываем зарплату мастеру (если заказ назначен на мастера)
    if (!empty($order['master_id'])) {
        echo "<!-- Расчет зарплаты для мастера ID: {$order['master_id']} -->";
        
        // Проверяем наличие класса SalaryCalculator
        if (!class_exists('SalaryCalculator')) {
            // Пробуем разные пути к файлу
            $salaryCalculatorPaths = [
                'inc/salary_calculator.php',
                '/inc/salary_calculator.php',
                '../inc/salary_calculator.php',
                'salary_calculator.php'
            ];
            
            $loaded = false;
            foreach ($salaryCalculatorPaths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    $loaded = true;
                    break;
                }
            }
            
            if (!$loaded) {
                throw new Exception('Файл salary_calculator.php не найден');
            }
        }
        
        try {
            $salaryResult = SalaryCalculator::calculateForOrder($orderId, $order['master_id']);
            
            if (isset($salaryResult['success']) && $salaryResult['success']) {
                // Логируем успешный расчет
                $salaryComment = "💰 Рассчитана зарплата мастеру {$order['master_name']}: " . 
                                number_format($salaryResult['amount'], 2) . " руб. " .
                                "(Базовая: " . number_format($salaryResult['base_salary'], 2) . " руб., " .
                                "Вычет за запчасти: " . number_format($salaryResult['parts_deduction'], 2) . " руб.)";
                
                $salaryCommentStmt = $pdo->prepare("INSERT INTO order_comments (order_id, user_id, comment) VALUES (?, ?, ?)");
                $salaryCommentStmt->execute([$orderId, $_SESSION['user_id'], $salaryComment]);
                
                echo "<!-- Зарплата рассчитана успешно: {$salaryResult['amount']} руб. -->";
            } else {
                // Логируем ошибку
                $errorComment = "⚠️ Ошибка расчета зарплаты для мастера {$order['master_name']}: " . 
                               ($salaryResult['error'] ?? 'Неизвестная ошибка');
                $errorStmt = $pdo->prepare("INSERT INTO order_comments (order_id, user_id, comment) VALUES (?, ?, ?)");
                $errorStmt->execute([$orderId, $_SESSION['user_id'], $errorComment]);
                
                echo "<!-- Ошибка расчета зарплаты: {$salaryResult['error']} -->";
            }
        } catch (Exception $e) {
            // Логируем исключение
            $exceptionComment = "⚠️ Исключение при расчете зарплаты для мастера {$order['master_name']}: " . $e->getMessage();
            $exceptionStmt = $pdo->prepare("INSERT INTO order_comments (order_id, user_id, comment) VALUES (?, ?, ?)");
            $exceptionStmt->execute([$orderId, $_SESSION['user_id'], $exceptionComment]);
            
            echo "<!-- Исключение: {$e->getMessage()} -->";
        }
    } else {
        echo "<!-- Мастер не назначен заказу, расчет зарплаты пропущен -->";
        
        // Добавляем комментарий о том, что мастер не назначен
        $noMasterComment = "ℹ️ Заказ выдан без назначения мастера (зарплата не рассчитывалась)";
        $noMasterStmt = $pdo->prepare("INSERT INTO order_comments (order_id, user_id, comment) VALUES (?, ?, ?)");
        $noMasterStmt->execute([$orderId, $_SESSION['user_id'], $noMasterComment]);
    }
    
    // 7. Добавляем основной комментарий к заказу
    $commentStmt = $pdo->prepare("
        INSERT INTO order_comments (order_id, user_id, comment) 
        VALUES (?, ?, ?)
    ");
    
    $statusName = $finalAmount > 0 ? 'Выдан' : 'Выдан Б.Р.';
    $paymentInfo = $finalAmount > 0 ? 
        "Оплата: " . number_format($finalAmount, 2) . " руб. (" . getPaymentMethodName($paymentMethod) . ")" : 
        "Без оплаты";
    
    $commentText = "Заказ {$statusName}. {$paymentInfo}" . 
                  ($issueComment ? ". Комментарий: " . $issueComment : "");
    
    $commentStmt->execute([$orderId, $_SESSION['user_id'], $commentText]);
    
    $pdo->commit();
    
    // 8. Редирект
    if (isset($_POST['print_act']) && $finalAmount > 0) {
        header("Location: print_act.php?order_id=" . $orderId);
    } else {
        $_SESSION['success'] = "Заказ #{$orderId} успешно выдан";
        header("Location: cash.php");
    }
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = 'Ошибка при выдаче заказа: ' . $e->getMessage();
    error_log("Order issue error: " . $e->getMessage());
    header("Location: cash.php");
    exit;
}
?>