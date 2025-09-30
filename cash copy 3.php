<?php
// cash.php - Кассовая система с исправленными запросами
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

// Инициализируем переменные чтобы избежать ошибок
$stats = ['total_income' => 0, 'total_expense' => 0, 'balance' => 0, 'operations_count' => 0];
$paymentStats = [];
$readyOrders = [];
$operations = [];

// Получаем статистику по кассе
try {
    // Сначала проверим структуру таблицы cash_operations
    $checkTableStmt = $pdo->query("DESCRIBE cash_operations");
    $tableStructure = $checkTableStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Определяем имя колонки с датой
    $dateColumn = in_array('created_at', $tableStructure) ? 'created_at' : 
                 (in_array('date', $tableStructure) ? 'date' : 'start_date');
    
    // Определяем имя колонки с пользователем
    $userColumn = in_array('user_id', $tableStructure) ? 'user_id' :
                 (in_array('cashier_id', $tableStructure) ? 'cashier_id' : 'id');
    
    // Итоги за выбранный период - АДАПТИРОВАННЫЙ ЗАПРОС
    $statsStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
            SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as balance,
            COUNT(*) as operations_count
        FROM cash_operations 
        WHERE $userColumn = ? AND DATE($dateColumn) BETWEEN ? AND ?
    ");
    $statsStmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: $stats;
    
    // Статистика по методам оплаты
    $paymentStatsStmt = $pdo->prepare("
        SELECT 
            payment_method,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
        FROM cash_operations 
        WHERE $userColumn = ? AND DATE($dateColumn) BETWEEN ? AND ?
        GROUP BY payment_method
    ");
    $paymentStatsStmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
    $paymentStats = $paymentStatsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Заказы готовые к выдаче - упрощенный запрос
    $readyOrdersStmt = $pdo->prepare("
        SELECT o.id, o.problem_description, o.created_at,
               c.full_name as client_name, c.phone as client_phone,
               s.name as status_name
        FROM orders o 
        LEFT JOIN clients c ON o.client_id = c.id 
        LEFT JOIN statuses s ON o.status_id = s.id 
        WHERE o.status_id IN (5,6) 
        ORDER BY o.created_at DESC
        LIMIT 20
    ");
    $readyOrdersStmt->execute();
    $readyOrders = $readyOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Последние операции
    $operationsStmt = $pdo->prepare("
        SELECT co.*, u.full_name as user_name
        FROM cash_operations co 
        LEFT JOIN users u ON co.$userColumn = u.id 
        WHERE co.$userColumn = ? AND DATE(co.$dateColumn) BETWEEN ? AND ?
        ORDER BY co.$dateColumn DESC 
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

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>💵 Кассовая система</h2>
            <div>
                <?php if ($activeShift): ?>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#incomeModal" 
                        <?= !hasPermission('cash:operations') ? 'disabled' : '' ?>>
                        ➕ Приход
                    </button>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#expenseModal"
                        <?= !hasPermission('cash:operations') ? 'disabled' : '' ?>>
                        ➖ Расход
                    </button>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#correctionModal"
                        <?= !hasPermission('cash:operations') ? 'disabled' : '' ?>>
                        🔧 Корректировка
                    </button>
                <?php endif; ?>
                
                <?php if (hasPermission('cash:reports')): ?>
                    <a href="cash_reports.php" class="btn btn-info">📈 Отчеты</a>
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
                        <h3 class="text-success"><?= number_format($stats['total_income'] ?? 0, 2) ?> ₽</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card cash-expense">
                    <div class="card-body text-center">
                        <h5>💸 Расход</h5>
                        <h3 class="text-danger"><?= number_format($stats['total_expense'] ?? 0, 2) ?> ₽</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5>⚖️ Баланс</h5>
                        <h3 class="text-primary"><?= number_format($stats['balance'] ?? 0, 2) ?> ₽</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5>📊 Операции</h5>
                        <h3><?= $stats['operations_count'] ?? 0 ?></h3>
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
                        <div class="text-success">+<?= number_format($method['income'] ?? 0, 2) ?> ₽</div>
                        <div class="text-danger">-<?= number_format($method['expense'] ?? 0, 2) ?> ₽</div>
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
                             data-order-id="<?= $order['id'] ?>" data-order-total="<?= $order['total_amount'] ?? 0 ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Заказ #<?= $order['id'] ?></h6>
                                    <p class="mb-1"><?= htmlspecialchars($order['client_name']) ?> (<?= htmlspecialchars($order['client_phone']) ?>)</p>
                                    <small class="text-muted"><?= htmlspecialchars($order['problem_description']) ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success"><?= htmlspecialchars($order['status_name']) ?></span>
                                    <div class="mt-1"><strong><?= number_format($order['total_amount'] ?? 0, 2) ?> ₽</strong></div>
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
                                    <td><?= date('d.m.Y H:i', strtotime($op['created_at'])) ?></td>
                                    <td>
                                        <span class="badge <?= $op['type'] == 'income' ? 'bg-success' : 'bg-danger' ?>">
                                            <?= $op['type'] == 'income' ? 'Приход' : 'Расход' ?>
                                        </span>
                                    </td>
                                    <td><strong><?= number_format($op['amount'], 2) ?> ₽</strong></td>
                                    <td><?= getPaymentMethodName($op['payment_method']) ?></td>
                                    <td><?= htmlspecialchars($op['description']) ?></td>
                                    <td>
                                        <?php if ($op['order_number']): ?>
                                            <a href="order_view.php?id=<?= $op['order_number'] ?>">#<?= $op['order_number'] ?></a>
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
// Обработка выдачи заказа
document.querySelectorAll('.ready-order').forEach(item => {
    item.addEventListener('click', function() {
        const orderId = this.dataset.orderId;
        const orderTotal = this.dataset.orderTotal;
        
        document.getElementById('issue_order_id').value = orderId;
        document.getElementById('issue_final_amount').value = orderTotal;
        
        // Загружаем детали заказа
        fetch('ajax_get_order_details.php?id=' + orderId)
            .then(response => response.json())
            .then(data => {
                document.getElementById('issue_order_info').innerHTML = 
                    `<strong>Заказ #${data.id}</strong><br>${data.client_name}<br>${data.problem_description}`;
            });
    });
});

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
});
</script>



renderFooter(); 
?>