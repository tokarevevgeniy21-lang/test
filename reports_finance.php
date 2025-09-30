<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('reports:view');

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Функции для финансовых отчетов
function getFinanceSummary($startDate, $endDate) {
    global $pdo;
    
    // Получаем основную финансовую информацию из заказов
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(salary_amount), 0) as salary_cost,
            COALESCE(SUM(profit), 0) as total_profit,
            COALESCE(SUM(payments_total), 0) as total_payments,
            COUNT(*) as total_orders
        FROM orders 
        WHERE created_at BETWEEN ? AND ? 
        AND status_id NOT IN (
            SELECT id FROM statuses WHERE name LIKE '%отмен%' OR name LIKE '%возврат%'
        )
    ");
    
    $stmt->execute([$startDate, $endDate]);
    $result = $stmt->fetch();
    
    // Получаем стоимость запчастей из order_parts
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(op.price * op.quantity), 0) as parts_cost
        FROM order_parts op
        JOIN orders o ON op.order_id = o.id
        WHERE o.created_at BETWEEN ? AND ?
        AND o.status_id NOT IN (
            SELECT id FROM statuses WHERE name LIKE '%отмен%' OR name LIKE '%возврат%'
        )
    ");
    
    $stmt->execute([$startDate, $endDate]);
    $parts = $stmt->fetch();
    $result['parts_cost'] = $parts['parts_cost'];
    
    // Получаем стоимость услуг из order_services
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(os.price * os.quantity), 0) as services_cost
        FROM order_services os
        JOIN orders o ON os.order_id = o.id
        WHERE o.created_at BETWEEN ? AND ?
        AND o.status_id NOT IN (
            SELECT id FROM statuses WHERE name LIKE '%отмен%' OR name LIKE '%возврат%'
        )
    ");
    
    $stmt->execute([$startDate, $endDate]);
    $services = $stmt->fetch();
    $result['services_cost'] = $services['services_cost'];
    
    return $result;
}

function getRevenueByCategory($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            dc.name as category_name,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(AVG(o.total_amount), 0) as avg_revenue,
            COALESCE(SUM(op.parts_cost), 0) as parts_cost,
            COALESCE(SUM(os.services_cost), 0) as services_cost
        FROM orders o
        JOIN device_categories dc ON o.device_category_id = dc.id
        LEFT JOIN (
            SELECT order_id, COALESCE(SUM(price * quantity), 0) as parts_cost
            FROM order_parts GROUP BY order_id
        ) op ON o.id = op.order_id
        LEFT JOIN (
            SELECT order_id, COALESCE(SUM(price * quantity), 0) as services_cost
            FROM order_services GROUP BY order_id
        ) os ON o.id = os.order_id
        WHERE o.created_at BETWEEN ? AND ?
        GROUP BY o.device_category_id
        ORDER BY total_revenue DESC
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

function getCashFlow($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            type,
            payment_method,
            COUNT(*) as operation_count,
            COALESCE(SUM(amount), 0) as total_amount,
            DATE(operation_date) as operation_date,
            description
        FROM cash_operations 
        WHERE operation_date BETWEEN ? AND ?
        GROUP BY type, payment_method, DATE(operation_date)
        ORDER BY operation_date DESC, type
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

$financeSummary = getFinanceSummary($startDate, $endDate);
$revenueByCategory = getRevenueByCategory($startDate, $endDate);
$cashFlow = getCashFlow($startDate, $endDate);

renderHeader('Финансовые отчеты');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Финансовые отчеты</h5>
            </div>
            <div class="card-body">
                <!-- Фильтры -->
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
                <!-- Финансовая сводка -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-success text-white text-center">
            <div class="card-body">
                <h6>Выручка</h6>
                <h4><?= number_format($financeSummary['total_revenue'] ?? 0, 2) ?> ₽</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-danger text-white text-center">
            <div class="card-body">
                <h6>Запчасти</h6>
                <h4><?= number_format($financeSummary['parts_cost'] ?? 0, 2) ?> ₽</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-danger text-white text-center">
            <div class="card-body">
                <h6>Услуги</h6>
                <h4><?= number_format($financeSummary['services_cost'] ?? 0, 2) ?> ₽</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white text-center">
            <div class="card-body">
                <h6>Зарплаты</h6>
                <h4><?= number_format($financeSummary['salary_cost'] ?? 0, 2) ?> ₽</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white text-center">
            <div class="card-body">
                <h6>Прибыль</h6>
                <h4><?= number_format($financeSummary['total_profit'] ?? 0, 2) ?> ₽</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-primary text-white text-center">
            <div class="card-body">
                <h6>Заказов</h6>
                <h4><?= $financeSummary['total_orders'] ?? 0 ?></h4>
            </div>
        </div>
    </div>
</div>

                <!-- Выручка по категориям -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Выручка по категориям устройств</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Категория</th>
                                        <th>Заказов</th>
                                        <th>Выручка</th>
                                        <th>Средний чек</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($revenueByCategory as $row): ?>
                                    <tr>
                                        <td><?= safe($row['category_name']) ?></td>
                                        <td><?= $row['order_count'] ?></td>
                                        <td><?= number_format($row['total_revenue'], 2) ?> ₽</td>
                                        <td><?= number_format($row['avg_revenue'], 2) ?> ₽</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Движение денежных средств -->
                <div class="card">
                    <div class="card-header">
                        <h6>Движение денежных средств</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>Тип операции</th>
                                        <th>Метод оплаты</th>
                                        <th>Количество</th>
                                        <th>Сумма</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cashFlow as $row): ?>
                                    <tr>
                                        <td><?= date('d.m.Y', strtotime($row['operation_date'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $row['type'] === 'income' ? 'success' : 'danger' ?>">
                                                <?= $row['type'] === 'income' ? 'Приход' : 'Расход' ?>
                                            </span>
                                        </td>
                                        <td><?= safe($row['payment_method']) ?></td>
                                        <td><?= $row['operation_count'] ?></td>
                                        <td><?= number_format($row['total_amount'], 2) ?> ₽</td>
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