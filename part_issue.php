<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('parts:issue');
// Функция выдачи запчасти мастеру (если не существует)
function issuePartToMaster($partId, $masterId, $quantity, $orderId = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 1. Получаем информацию о запчасти
        $partStmt = $pdo->prepare("SELECT * FROM parts WHERE id = ?");
        $partStmt->execute([$partId]);
        $part = $partStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$part) {
            throw new Exception('Запчасть не найдена');
        }
        
        // 2. Проверяем наличие на складе
        if ($part['stock_quantity'] < $quantity) {
            throw new Exception('Недостаточно запчастей на складе. Доступно: ' . $part['stock_quantity'] . ' шт.');
        }
        
        // 3. Списываем со склада
        $updateStock = $pdo->prepare("UPDATE parts SET stock_quantity = stock_quantity - ? WHERE id = ?");
        $updateStock->execute([$quantity, $partId]);
        
        // 4. Добавляем в order_parts если указан заказ
        if ($orderId) {
            $orderPartStmt = $pdo->prepare("
                INSERT INTO order_parts (order_id, part_id, quantity, price, issued_to_master) 
                VALUES (?, ?, ?, ?, 1)
            ");
            $orderPartStmt->execute([$orderId, $partId, $quantity, $part['sale_price']]);
        }
        
        // 5. Записываем в master_parts
        $insertMasterPart = $pdo->prepare("
            INSERT INTO master_parts (master_id, part_id, quantity, order_id, issue_date, status) 
            VALUES (?, ?, ?, ?, NOW(), 'issued')
        ");
        $insertMasterPart->execute([$masterId, $partId, $quantity, $orderId]);
        
        // 6. ЕСЛИ УКАЗАН ЗАКАЗ - СПИСЫВАЕМ ЗАРПЛАТУ
        if ($orderId) {
            require_once 'inc/salary_calculator.php';
            
            // Рассчитываем сумму списания (40% от стоимости запчасти)
            $deductionAmount = ($part['sale_price'] * $quantity) * 0.4;
            
            // Создаем запись о списании зарплаты
            $salaryDeduction = [
                'user_id' => $masterId,
                'order_id' => $orderId,
                'amount' => -$deductionAmount, // Отрицательная сумма - списание
                'payment_date' => date('Y-m-d H:i:s'),
                'calculation_details' => "Списание за запчасть: {$part['name']} (40% от {$part['sale_price']} руб. × {$quantity} шт.)"
            ];
            
            // Используем существующую функцию или прямую вставку
            $insertSalary = $pdo->prepare("
                INSERT INTO salary_payments (user_id, order_id, amount, payment_date, calculation_details) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertSalary->execute([
                $salaryDeduction['user_id'],
                $salaryDeduction['order_id'], 
                $salaryDeduction['amount'],
                $salaryDeduction['payment_date'],
                $salaryDeduction['calculation_details']
            ]);
            
            // Добавляем комментарий в заказ
            $comment = "📦 Выдана запчасть мастеру: {$part['name']} - {$quantity} шт. (Списание зарплаты: " . number_format($deductionAmount, 2) . " руб.)";
            $commentStmt = $pdo->prepare("INSERT INTO order_comments (order_id, user_id, comment) VALUES (?, ?, ?)");
            $commentStmt->execute([$orderId, $_SESSION['user_id'], $comment]);
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error issuing part: " . $e->getMessage());
        return false;
    }
}
// ★★★★ ДОБАВИТЬ ЭТИ СТРОКИ - получение данных ★★★★
// Получаем список запчастей и мастеров
$parts = getAllParts(true); // Эта функция должна быть определена
$masters = getActiveMasters(); // Эта функция должна быть определена
$preSelectedPartId = $_GET['part_id'] ?? 0;
// ★★★★ КОНЕЦ ДОБАВЛЕНИЯ ★★★★
// Получаем список запчастей и мастеров
// Обработка выдачи запчасти
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partId = (int)$_POST['part_id'];
    $masterId = (int)$_POST['master_id'];
    $quantity = (int)$_POST['quantity'];
    $orderId = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    
    // Проверка обязательных полей
    if (empty($_POST['order_id'])) {
        $_SESSION['error'] = 'Номер заказа обязателен для заполнения';
    } elseif ($quantity <= 0) {
        $_SESSION['error'] = 'Количество должно быть больше 0';
    } elseif (issuePartToMaster($partId, $masterId, $quantity, $orderId)) {
        $_SESSION['success'] = 'Запчасть успешно выдана мастеру';
        header('Location: part_issue.php');
        exit;
    } else {
        $_SESSION['error'] = 'Ошибка при выдаче запчасти';
    }
}

renderHeader('Выдача запчастей мастерам');
?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Выдача запчасти мастеру</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Запчасть *</label>
                       <select class="form-select" name="part_id" required>
    <option value="">-- Выберите запчасть --</option>
    <?php foreach ($parts as $part): ?>
        <?php if ($part['stock_quantity'] > 0): ?>
        <option value="<?= $part['id'] ?>" 
                data-price="<?= $part['sale_price'] ?>"
                <?= $preSelectedPartId == $part['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($part['name']) ?> 
            (<?= $part['stock_quantity'] ?> шт. - <?= number_format($part['sale_price'], 2) ?> ₽)
        </option>
        <?php endif; ?>
    <?php endforeach; ?>
</select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Мастер *</label>
                        <select class="form-select" name="master_id" required>
                            <option value="">-- Выберите мастера --</option>
                            <?php foreach ($masters as $master): ?>
                            <option value="<?= $master['id'] ?>"><?= htmlspecialchars($master['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Количество *</label>
                        <input type="number" class="form-control" name="quantity" value="1" min="1" required>
                    </div>
                    
                    <div class="mb-3">
    <label class="form-label">Номер заказа *</label>
    <input type="number" class="form-control" name="order_id" 
           placeholder="Введите номер заказа" required
           min="1">
</div>
                    
                    <div class="mb-3">
                        <div class="alert alert-info">
                            <strong>Внимание:</strong> При использовании запчасти в заказе, из зарплаты мастера 
                            будет удержано 40% от прибыли (разница между ценой продажи и себестоимостью).
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Выдать запчасть</button>
                    <a href="parts_master_list.php" class="btn btn-secondary">Список выданных запчастей</a>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Информация о выдаче</h5>
            </div>
            <div class="card-body">
                <h6>Процесс работы с запчастями:</h6>
                <ol>
                    <li>Выдайте запчасть мастеру через эту форму</li>
                    <li>Мастер получает запчасть на руки</li>
                    <li>При использовании запчасти в заказе, мастер отмечает ее использование</li>
                    <li>Система автоматически рассчитывает удержание из зарплаты</li>
                </ol>
                
                <h6 class="mt-4">Правила удержаний:</h6>
                <ul>
                    <li>Удерживается 40% от прибыли с запчасти</li>
                    <li>Прибыль = Цена продажи - Себестоимость</li>
                    <li>Удержание происходит только при использовании запчасти в заказе</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<script>
// Простая валидация номера заказа
document.querySelector('form').addEventListener('submit', function(e) {
    const orderIdInput = document.querySelector('input[name="order_id"]');
    
    if (!orderIdInput.value || orderIdInput.value <= 0) {
        e.preventDefault();
        alert('Пожалуйста, введите корректный номер заказа');
        orderIdInput.focus();
    }
});
</script>
<?php renderFooter(); ?>