<?php
// cash.php - Кассовая система (исправленная версия)
require_once 'inc/layout.php';

requireAuth();
requirePermission('cash:access');

global $pdo;

// Проверяем активную кассовую смену
$activeShift = getActiveCashShift($_SESSION['user_id']);

// Текущая дата для фильтров
$currentDate = date('Y-m-d');
$startDate = $_GET['start_date'] ?? $currentDate;
$endDate = $_GET['end_date'] ?? $currentDate;

// Инициализируем переменные с значениями по умолчанию
$stats = ['total_income' => 0, 'total_expense' => 0, 'balance' => 0, 'operations_count' => 0];
$paymentStats = [];
$readyOrders = [];
$operations = [];

// Получаем статистику по кассе
try {
    // Итоги за выбранный период
    $statsStmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) as balance,
            COALESCE(COUNT(*), 0) as operations_count
        FROM cash_operations 
        WHERE user_id = ? AND DATE(operation_date) BETWEEN ? AND ?
    ");
    $statsStmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
    $result = $statsStmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = [
            'total_income' => (float)($result['total_income'] ?? 0),
            'total_expense' => (float)($result['total_expense'] ?? 0),
            'balance' => (float)($result['balance'] ?? 0),
            'operations_count' => (int)($result['operations_count'] ?? 0)
        ];
    }
    
    // Статистика по методам оплаты
    $paymentStatsStmt = $pdo->prepare("
        SELECT 
            payment_method,
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense
        FROM cash_operations 
        WHERE user_id = ? AND DATE(operation_date) BETWEEN ? AND ?
        GROUP BY payment_method
    ");
    $paymentStatsStmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
    $paymentStats = $paymentStatsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Заказы готовые к выдаче (статус 5 = "Готов к выдаче")
    $readyOrdersStmt = $pdo->prepare("
    SELECT o.id, o.problem_description, o.created_at, o.status_id,
           c.full_name as client_name, c.phone as client_phone,
           s.name as status_name, s.id as status_id
    FROM orders o 
    LEFT JOIN clients c ON o.client_id = c.id 
    LEFT JOIN statuses s ON o.status_id = s.id 
    WHERE o.status_id IN (4, 18) -- Готов к выдаче (4) и На выдаче (18)
    AND o.id NOT IN (SELECT order_id FROM order_issuance WHERE order_id IS NOT NULL)
    ORDER BY 
        CASE 
            WHEN o.status_id = 18 THEN 1 
            WHEN o.status_id = 4 THEN 2 
        END,
        o.created_at DESC
    LIMIT 20
");
    $readyOrdersStmt->execute();
    $readyOrders = $readyOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Последние операции
    $operationsStmt = $pdo->prepare("
        SELECT co.*, u.full_name as user_name, o.id as order_number
        FROM cash_operations co 
        LEFT JOIN users u ON co.user_id = u.id 
        LEFT JOIN orders o ON co.order_id = o.id 
        WHERE co.user_id = ? AND DATE(co.operation_date) BETWEEN ? AND ?
        ORDER BY co.operation_date DESC 
        LIMIT 50
    ");
    $operationsStmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
    $operations = $operationsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Ошибка загрузки данных: ' . $e->getMessage();
    error_log("Cash.php error: " . $e->getMessage());
}

renderHeader('Касса');
?>

<style>
.cash-stats { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
.payment-method-card { border-left: 4px solid #007bff; }
.cash-income { border-left-color: #28a745 !important; }
.cash-expense { border-left-color: #dc3545 !important; }
.ready-order { cursor: pointer; transition: background 0.3s; }
.ready-order:hover { background: #f8f9fa; }
.shift-status { padding: 10px; border-radius: 5px; margin-bottom: 20px; }
.shift-open { background: #d4edda; border: 1px solid #c3e6cb; }
.shift-closed { background: #f8d7da; border: 1px solid #f5c6cb; }
</style>

<div class="row">
    <div class="col-md-12">
        <!-- Статус кассовой смены -->
        <div class="shift-status <?= $activeShift ? 'shift-open' : 'shift-closed' ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1"><?= $activeShift ? '✅ Кассовая смена открыта' : '❌ Кассовая смена закрыта' ?></h5>
                    <?php if ($activeShift): ?>
                        <p class="mb-0">Начало смены: <?= date('d.m.Y H:i', strtotime($activeShift['start_date'])) ?></p>
                        <p class="mb-0">Начальный баланс: <?= number_format($activeShift['start_balance'], 2) ?> ₽</p>
                    <?php else: ?>
                        <p class="mb-0">Для работы с кассой необходимо открыть смену</p>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($activeShift): ?>
                        <a href="cash_shift.php?action=close" class="btn btn-warning btn-sm">
                            📋 Закрыть смену
                        </a>
                    <?php else: ?>
                        <a href="cash_shift.php?action=open" class="btn btn-success btn-sm">
                            📊 Открыть смену
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>💵 Кассовая система</h2>
            <div>
                <?php if ($activeShift): ?>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#incomeModal">
                        ➕ Приход
                    </button>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#expenseModal">
                        ➖ Расход
                    </button>
                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#adjustmentModal">
                        🔧 Корректировка
                    </button>
                <?php else: ?>
                    <span class="text-muted">Откройте смену для работы с кассой</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Фильтр по дате -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">С</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">По</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Применить</button>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <a href="cash.php" class="btn btn-secondary w-100">Сегодня</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Статистика -->
        <div class="row mb-4">
    <div class="col-md-3">
        <div class="card cash-income">
            <div class="card-body text-center">
                <h5>💰 Приход</h5>
                <h3 class="text-success"><?= number_format($stats['total_income'], 2) ?> ₽</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card cash-expense">
            <div class="card-body text-center">
                <h5>💸 Расход</h5>
                <h3 class="text-danger"><?= number_format($stats['total_expense'], 2) ?> ₽</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h5>⚖️ Баланс</h5>
                <h3 class="text-primary"><?= number_format($stats['balance'], 2) ?> ₽</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h5>📊 Операции</h5>
                <h3><?= $stats['operations_count'] ?></h3>
            </div>
        </div>
    </div>
</div>

        <!-- Статистика по методам оплаты -->
        <?php if (!empty($paymentStats)): ?>
        <div class="row mb-4">
            <?php foreach ($paymentStats as $method): ?>
            <div class="col-md-3">
                <div class="card payment-method-card">
                    <div class="card-body text-center">
                        <h6><?= getPaymentMethodName($method['payment_method']) ?></h6>
                        <div class="text-success">+<?= number_format($method['income'], 2) ?> ₽</div>
                        <div class="text-danger">-<?= number_format($method['expense'], 2) ?> ₽</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Заказы готовые к выдаче -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">📦 Заказы готовые к выдаче (<?= count($readyOrders) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($readyOrders)): ?>
                    <div class="text-center p-4 text-muted">Нет заказов готовых к выдаче</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($readyOrders as $order): ?>
                        <div class="list-group-item ready-order" data-bs-toggle="modal" data-bs-target="#issueOrderModal" 
                             data-order-id="<?= $order['id'] ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Заказ #<?= $order['id'] ?></h6>
                                    <p class="mb-1"><?= htmlspecialchars($order['client_name']) ?> (<?= htmlspecialchars($order['client_phone']) ?>)</p>
                                    <small class="text-muted"><?= htmlspecialchars($order['problem_description']) ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success"><?= htmlspecialchars($order['status_name']) ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Последние операции -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">📋 Последние операции</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Тип</th>
                                <th>Сумма</th>
                                <th>Метод</th>
                                <th>Описание</th>
                                <th>Заказ</th>
                                <th>Пользователь</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($operations)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted p-3">Нет операций за выбранный период</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($operations as $op): ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime($op['operation_date'])) ?></td>
                                    <td>
                                        <span class="badge <?= $op['type'] == 'income' ? 'bg-success' : 'bg-danger' ?>">
                                            <?= $op['type'] == 'income' ? 'Приход' : 'Расход' ?>
                                        </span>
                                    </td>
                                    <td><strong><?= number_format($op['amount'], 2) ?> ₽</strong></td>
                                    <td><?= getPaymentMethodName($op['payment_method']) ?></td>
                                    <td><?= htmlspecialchars($op['description']) ?></td>
                                    <td>
                                        <?php if ($op['order_id']): ?>
                                            <a href="order_view.php?id=<?= $op['order_id'] ?>">#<?= $op['order_id'] ?></a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($op['user_name']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальные окна -->
<?php include 'modals/order_modals.php'; ?>

<script>
let currentOrderId = null;

// Обработка выдачи заказа
document.querySelectorAll('.ready-order').forEach(item => {
    item.addEventListener('click', function() {
        currentOrderId = this.dataset.orderId;
        document.getElementById('issue_order_id').value = currentOrderId;
        
        // Сбрасываем форму
        document.getElementById('issue_final_amount').value = '';
        document.getElementById('issue_amount_received').value = '';
        document.getElementById('issue_change_amount').value = '';
        document.getElementById('amount_details').style.display = 'none';
        
        // Загружаем детали заказа
        fetch('ajax_get_order_details.php?id=' + currentOrderId)
            .then(response => response.json())
            .then(data => {
                const statusBadge = data.status_id == 18 ? 
                    '<span class="badge bg-warning">На выдаче</span>' : 
                    '<span class="badge bg-success">Готов к выдаче</span>';
                
                document.getElementById('issue_order_info').innerHTML = 
                    `<strong>Заказ #${data.id}</strong> ${statusBadge}<br>
                     ${data.client_name} (${data.client_phone})<br>
                     ${data.problem_description}`;
                
                // Автоматически загружаем сумму заказа
                loadOrderTotal();
            });
    });
});

// Автоматическая загрузка суммы заказа
function loadOrderTotal() {
    if (!currentOrderId) return;
    
    document.getElementById('amount_info').innerHTML = 'Загрузка суммы...';
    
    fetch('ajax_get_order_total.php?id=' + currentOrderId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('amount_info').innerHTML = 'Ошибка: ' + data.error;
                return;
            }
            
            if (!data.success) {
                document.getElementById('amount_info').innerHTML = 'Ошибка загрузки суммы';
                return;
            }
            
            const totalAmount = data.total_amount || 0;
            const servicesAmount = data.services_amount || 0;
            const partsAmount = data.parts_amount || 0;
            
            document.getElementById('issue_final_amount').value = totalAmount.toFixed(2);
            document.getElementById('services_amount').textContent = servicesAmount.toFixed(2);
            document.getElementById('parts_amount').textContent = partsAmount.toFixed(2);
            document.getElementById('total_amount').textContent = totalAmount.toFixed(2);
            
            document.getElementById('amount_details').style.display = 'block';
            document.getElementById('amount_info').innerHTML = `Сумма автоматически рассчитана`;
            
            // Если сумма 0 - автоматически выбираем гарантию
            if (totalAmount === 0) {
                document.getElementById('issue_payment_method').value = 'warranty';
                document.getElementById('cash_fields').style.display = 'none';
                document.getElementById('issue_amount_received').required = false;
            } else {
                document.getElementById('issue_payment_method').value = 'cash';
                document.getElementById('cash_fields').style.display = 'block';
                document.getElementById('issue_amount_received').required = true;
            }
            
            calculateChange();
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('amount_info').innerHTML = 'Ошибка загрузки суммы';
        });
}

// Автоматический расчет сдачи
function calculateChange() {
    const amount = parseFloat(document.getElementById('issue_final_amount').value) || 0;
    const received = parseFloat(document.getElementById('issue_amount_received').value) || 0;
    const change = received - amount;
    
    document.getElementById('issue_change_amount').value = change > 0 ? change.toFixed(2) : '0.00';
}

// Показ/скрытие поля для наличных
document.getElementById('issue_payment_method').addEventListener('change', function() {
    const isCash = this.value === 'cash';
    document.getElementById('cash_fields').style.display = isCash ? 'block' : 'none';
    
    // Если выбрана гарантия и сумма не 0 - предупреждение
    if (this.value === 'warranty') {
        const amount = parseFloat(document.getElementById('issue_final_amount').value) || 0;
        if (amount > 0) {
            if (!confirm('Внимание! Сумма заказа больше 0, но выбран способ оплаты "Гарантия". Продолжить?')) {
                this.value = 'cash';
                document.getElementById('cash_fields').style.display = 'block';
            }
        }
    }
});

// При изменении суммы пересчитываем сдачу
document.getElementById('issue_final_amount').addEventListener('change', function() {
    calculateChange();
});
</script>

<?php renderFooter(); ?>