<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('reports:view');

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

function getCashSummary($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            type,
            payment_method,
            COUNT(*) as operation_count,
            COALESCE(SUM(amount), 0) as total_amount,
            COUNT(DISTINCT user_id) as unique_users
        FROM cash_operations 
        WHERE operation_date BETWEEN ? AND ?
        GROUP BY type, payment_method
        ORDER BY type, payment_method
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

function getCashDaily($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(operation_date) as operation_date,
            type,
            COUNT(*) as operation_count,
            COALESCE(SUM(amount), 0) as daily_amount,
            GROUP_CONCAT(DISTINCT payment_method) as payment_methods
        FROM cash_operations 
        WHERE operation_date BETWEEN ? AND ?
        GROUP BY DATE(operation_date), type
        ORDER BY operation_date DESC, type
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

function getCashierShifts($startDate, $endDate) {
    global $pdo;
    
    // Проверим наличие столбца shift_id в cash_operations
    $stmtCheck = $pdo->prepare("SHOW COLUMNS FROM cash_operations LIKE 'shift_id'");
    $stmtCheck->execute();
    $hasShiftId = $stmtCheck->fetch();
    
    if ($hasShiftId) {
        // Если есть shift_id
        $stmt = $pdo->prepare("
            SELECT 
                cs.id,
                u.full_name as cashier_name,
                cs.start_balance,
                cs.end_balance,
                cs.actual_cash,
                cs.difference,
                cs.start_date,
                cs.end_date,
                cs.status,
                COUNT(co.id) as operations_count,
                COALESCE(SUM(CASE WHEN co.type = 'income' THEN co.amount ELSE 0 END), 0) as income_total,
                COALESCE(SUM(CASE WHEN co.type = 'expense' THEN co.amount ELSE 0 END), 0) as expense_total
            FROM cashier_shifts cs
            JOIN users u ON cs.cashier_id = u.id
            LEFT JOIN cash_operations co ON cs.id = co.shift_id 
                AND DATE(co.operation_date) BETWEEN ? AND ?
            WHERE DATE(cs.start_date) BETWEEN ? AND ?
            GROUP BY cs.id
            ORDER BY cs.start_date DESC
        ");
    } else {
        // Если нет shift_id, свяжем через дату операции
        $stmt = $pdo->prepare("
            SELECT 
                cs.id,
                u.full_name as cashier_name,
                cs.start_balance,
                cs.end_balance,
                cs.actual_cash,
                cs.difference,
                cs.start_date,
                cs.end_date,
                cs.status,
                COUNT(co.id) as operations_count,
                COALESCE(SUM(CASE WHEN co.type = 'income' THEN co.amount ELSE 0 END), 0) as income_total,
                COALESCE(SUM(CASE WHEN co.type = 'expense' THEN co.amount ELSE 0 END), 0) as expense_total
            FROM cashier_shifts cs
            JOIN users u ON cs.cashier_id = u.id
            LEFT JOIN cash_operations co ON DATE(co.operation_date) = DATE(cs.start_date)
                AND co.user_id = cs.cashier_id
                AND DATE(co.operation_date) BETWEEN ? AND ?
            WHERE DATE(cs.start_date) BETWEEN ? AND ?
            GROUP BY cs.id
            ORDER BY cs.start_date DESC
        ");
    }
    
    $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
    return $stmt->fetchAll();
}

$cashSummary = getCashSummary($startDate, $endDate);
$cashDaily = getCashDaily($startDate, $endDate);
$cashierShifts = getCashierShifts($startDate, $endDate);

renderHeader('Кассовые отчеты');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Кассовые отчеты</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 mb-4">
    <div class="col-md-2">
        <label class="form-label">Начало</label>
        <input type="date" class="form-control" name="start_date" value="<?= $startDate ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label">Конец</label>
        <input type="date" class="form-control" name="end_date" value="<?= $endDate ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label">Период</label>
        <select class="form-select" onchange="setPeriod(this.value)">
            <option value="">Выберите период</option>
            <option value="today">Сегодня</option>
            <option value="yesterday">Вчера</option>
            <option value="week">Эта неделя</option>
            <option value="month">Этот месяц</option>
            <option value="quarter">Этот квартал</option>
            <option value="year">Этот год</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">&nbsp;</label>
        <button type="submit" class="btn btn-primary w-100">Применить</button>
    </div>
    <div class="col-md-2">
        <label class="form-label">&nbsp;</label>
        <a href="<?= basename($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary w-100">Сбросить</a>
    </div>
</form>

<script>
// Замените функцию setPeriod на эту улучшенную версию
function setPeriod(period) {
    const today = new Date();
    let startDate, endDate;
    
    switch(period) {
        case 'today':
            startDate = endDate = today.toISOString().split('T')[0];
            break;
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            startDate = endDate = yesterday.toISOString().split('T')[0];
            break;
        case 'week':
            const firstDay = new Date(today);
            firstDay.setDate(today.getDate() - today.getDay() + (today.getDay() === 0 ? -6 : 1));
            startDate = firstDay.toISOString().split('T')[0];
            endDate = today.toISOString().split('T')[0];
            break;
        case 'month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
            break;
        case 'quarter':
            const quarter = Math.floor(today.getMonth() / 3);
            startDate = new Date(today.getFullYear(), quarter * 3, 1).toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), quarter * 3 + 3, 0).toISOString().split('T')[0];
            break;
        case 'year':
            startDate = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
            endDate = new Date(today.getFullYear(), 11, 31).toISOString().split('T')[0];
            break;
        case 'last_month':
            const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            startDate = new Date(lastMonth.getFullYear(), lastMonth.getMonth(), 1).toISOString().split('T')[0];
            endDate = new Date(lastMonth.getFullYear(), lastMonth.getMonth() + 1, 0).toISOString().split('T')[0];
            break;
        default:
            return;
    }
    
    document.querySelector('[name="start_date"]').value = startDate;
    document.querySelector('[name="end_date"]').value = endDate;
    
    // Автоматически применяем фильтр
    document.querySelector('form').submit();
}
</script>
<div class="row mb-3">
    <div class="col-md-4">
        <div class="input-group">
            <input type="text" id="searchInput" class="form-control" placeholder="Поиск в таблице...">
            <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                <i class="bi bi-x-circle"></i>
            </button>
        </div>
    </div>
</div>

<script>
function initSearch() {
    const searchInput = document.getElementById('searchInput');
    const tables = document.querySelectorAll('table');
    
    searchInput.addEventListener('input', function() {
        const searchText = this.value.toLowerCase();
        
        tables.forEach(table => {
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    });
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    document.querySelectorAll('table tbody tr').forEach(row => {
        row.style.display = '';
    });
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', initSearch);
</script>
<!-- Добавьте этот код в КАЖДЫЙ отчет после фильтров и перед основным содержимым -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap gap-2">
                    <a href="reports_dashboard.php" class="btn btn-sm btn-outline-primary">Дашборд</a>
                    <a href="reports_orders.php" class="btn btn-sm btn-outline-secondary">Заказы</a>
                    <a href="reports_finance.php" class="btn btn-sm btn-outline-success">Финансы</a>
                    <a href="reports_employees.php" class="btn btn-sm btn-outline-info">Сотрудники</a>
                    <a href="reports_clients.php" class="btn btn-sm btn-outline-warning">Клиенты</a>
                    <a href="reports_cash.php" class="btn btn-sm btn-outline-danger">Касса</a>
                    <a href="reports_serial.php" class="btn btn-sm btn-outline-dark">Серийные номера</a>
                </div>
            </div>
        </div>
    </div>
</div>
                <!-- Сводка по кассе -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Сводка по кассовым операциям</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Тип операции</th>
                                        <th>Метод оплаты</th>
                                        <th>Количество</th>
                                        <th>Сумма</th>
                                        <th>Кассиров</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $totalIncome = 0;
                                    $totalExpense = 0;
                                    foreach ($cashSummary as $row): 
                                        if ($row['type'] === 'income') $totalIncome += $row['total_amount'];
                                        if ($row['type'] === 'expense') $totalExpense += $row['total_amount'];
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?= $row['type'] === 'income' ? 'success' : 'danger' ?>">
                                                <?= $row['type'] === 'income' ? 'Приход' : 'Расход' ?>
                                            </span>
                                        </td>
                                        <td><?= $row['payment_method'] ?></td>
                                        <td><?= $row['operation_count'] ?></td>
                                        <td><?= number_format($row['total_amount'], 2) ?> ₽</td>
                                        <td><?= $row['unique_users'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-primary">
                                        <td colspan="2"><strong>Итого:</strong></td>
                                        <td><?= array_sum(array_column($cashSummary, 'operation_count')) ?></td>
                                        <td><?= number_format($totalIncome - $totalExpense, 2) ?> ₽</td>
                                        <td><?= count(array_unique(array_column($cashSummary, 'unique_users'))) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Ежедневная детализация -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Ежедневная детализация</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>Тип</th>
                                        <th>Операций</th>
                                        <th>Сумма</th>
                                        <th>Методы оплаты</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cashDaily as $row): ?>
                                    <tr>
                                        <td><?= date('d.m.Y', strtotime($row['operation_date'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $row['type'] === 'income' ? 'success' : 'danger' ?>">
                                                <?= $row['type'] === 'income' ? 'Приход' : 'Расход' ?>
                                            </span>
                                        </td>
                                        <td><?= $row['operation_count'] ?></td>
                                        <td><?= number_format($row['daily_amount'], 2) ?> ₽</td>
                                        <td><?= $row['payment_methods'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

               <!-- Смены кассиров -->
<div class="card">
    <div class="card-header">
        <h6>Смены кассиров</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Кассир</th>
                        <th>Начало смены</th>
                        <th>Конец смены</th>
                        <th>Начальный баланс</th>
                        <th>Конечный баланс</th>
                        <th>Фактическая наличность</th>
                        <th>Расхождение</th>
                        <th>Операций</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cashierShifts as $row): ?>
                    <tr>
                        <td><?= safe($row['cashier_name']) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($row['start_date'])) ?></td>
                        <td><?= $row['end_date'] ? date('d.m.Y H:i', strtotime($row['end_date'])) : '—' ?></td>
                        <td><?= number_format($row['start_balance'], 2) ?> ₽</td>
                        <td><?= number_format($row['end_balance'], 2) ?> ₽</td>
                        <td class="<?= $row['actual_cash'] >= $row['end_balance'] ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($row['actual_cash'], 2) ?> ₽
                        </td>
                        <td class="<?= $row['difference'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($row['difference'], 2) ?> ₽
                        </td>
                        <td><?= $row['operations_count'] ?></td>
                        <td>
                            <span class="badge bg-<?= $row['status'] === 'closed' ? 'success' : 'warning' ?>">
                                <?= $row['status'] === 'closed' ? 'Закрыта' : 'Открыта' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
            </div>
        </div>
    </div>
</div>
<!-- Кнопки экспорта -->
<div class="d-flex gap-2 mb-3">
    <button class="btn btn-success" onclick="exportToExcel()">
        <i class="bi bi-file-earmark-excel"></i> Excel
    </button>
    <button class="btn btn-danger" onclick="window.print()">
        <i class="bi bi-printer"></i> Печать
    </button>
</div>

<script>
function exportToExcel(tableId = null, filename = null) {
    const table = tableId ? document.getElementById(tableId) : document.querySelector('table');
    if (!table) return;
    
    let html = table.outerHTML;
    let blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    let link = document.createElement('a');
    
    const reportName = filename || 'report_' + new Date().toISOString().split('T')[0];
    link.href = URL.createObjectURL(blob);
    link.download = reportName + '.xls';
    link.click();
}

function exportToPDF() {
    alert('Экспорт в PDF будет доступен в следующей версии');
}
</script>

<?php renderFooter(); ?>