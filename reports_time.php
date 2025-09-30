<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('reports:view');

$period = $_GET['period'] ?? 'month'; // day, week, month
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

function getTimeSeriesData($startDate, $endDate, $period) {
    global $pdo;
    
    $groupBy = 'DATE(o.created_at)';
    $dateFormat = 'DATE(o.created_at) as date';
    
    switch ($period) {
        case 'week':
            $groupBy = 'YEARWEEK(o.created_at)';
            $dateFormat = 'CONCAT(YEAR(o.created_at), "-W", LPAD(WEEK(o.created_at), 2, "0")) as date';
            break;
        case 'month':
            $groupBy = 'DATE_FORMAT(o.created_at, "%Y-%m")';
            $dateFormat = 'DATE_FORMAT(o.created_at, "%Y-%m") as date';
            break;
    }
    
    // Упрощенный запрос без вложенных SELECT
    $stmt = $pdo->prepare("
        SELECT 
            $dateFormat,
            $groupBy as period_group,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value,
            COUNT(DISTINCT o.client_id) as unique_clients,
            COALESCE(SUM(o.profit), 0) as total_profit
        FROM orders o
        WHERE o.created_at BETWEEN ? AND ?
        GROUP BY $groupBy
        ORDER BY o.created_at
    ");
    
    $stmt->execute([$startDate, $endDate]);
    $timeData = $stmt->fetchAll();
    
    // Отдельно получим стоимость запчастей и услуг
    foreach ($timeData as &$row) {
        // Для дней
        if ($period === 'day') {
            $dateCondition = "DATE(o.created_at) = ?";
            $param = $row['date'];
        } 
        // Для недель
        elseif ($period === 'week') {
            $dateCondition = "YEARWEEK(o.created_at) = ?";
            $param = $row['period_group'];
        }
        // Для месяцев
        else {
            $dateCondition = "DATE_FORMAT(o.created_at, '%Y-%m') = ?";
            $param = $row['date'];
        }
        
        // Запчасти
        $stmtParts = $pdo->prepare("
            SELECT COALESCE(SUM(op.price * op.quantity), 0) as parts_cost
            FROM order_parts op
            JOIN orders o ON op.order_id = o.id
            WHERE $dateCondition AND o.created_at BETWEEN ? AND ?
        ");
        $stmtParts->execute([$param, $startDate, $endDate]);
        $parts = $stmtParts->fetch();
        $row['parts_cost'] = $parts['parts_cost'];
        
        // Услуги
        $stmtServices = $pdo->prepare("
            SELECT COALESCE(SUM(os.price * os.quantity), 0) as services_cost
            FROM order_services os
            JOIN orders o ON os.order_id = o.id
            WHERE $dateCondition AND o.created_at BETWEEN ? AND ?
        ");
        $stmtServices->execute([$param, $startDate, $endDate]);
        $services = $stmtServices->fetch();
        $row['services_cost'] = $services['services_cost'];
    }
    
    return $timeData;
}

function getWeekdayAnalysis($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            DAYNAME(o.created_at) as weekday,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value,
            COUNT(DISTINCT o.client_id) as unique_clients
        FROM orders o
        WHERE o.created_at BETWEEN ? AND ?
        GROUP BY DAYOFWEEK(o.created_at)
        ORDER BY DAYOFWEEK(o.created_at)
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

function getHourlyAnalysis($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(o.created_at) as hour,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value
        FROM orders o
        WHERE o.created_at BETWEEN ? AND ?
        GROUP BY HOUR(o.created_at)
        ORDER BY HOUR(o.created_at)
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

$timeData = getTimeSeriesData($startDate, $endDate, $period);
$weekdayData = getWeekdayAnalysis($startDate, $endDate);
$hourlyData = getHourlyAnalysis($startDate, $endDate);

renderHeader('Временная аналитика');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Временная аналитика</h5>
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
                <!-- Временные ряды -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Динамика по <?= $period === 'day' ? 'дням' : ($period === 'week' ? 'неделям' : 'месяцам') ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Период</th>
                                        <th>Заказов</th>
                                        <th>Выручка</th>
                                        <th>Средний чек</th>
                                        <th>Уникальных клиентов</th>
                                        <th>Прибыль</th>
                                        <th>Запчасти</th>
                                        <th>Услуги</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($timeData as $row): ?>
                                    <tr>
                                        <td><?= $row['date'] ?></td>
                                        <td><?= $row['order_count'] ?></td>
                                        <td><?= number_format($row['total_revenue'], 2) ?> ₽</td>
                                        <td><?= number_format($row['avg_order_value'], 2) ?> ₽</td>
                                        <td><?= $row['unique_clients'] ?></td>
                                        <td><?= number_format($row['total_profit'], 2) ?> ₽</td>
                                        <td><?= number_format($row['parts_cost'], 2) ?> ₽</td>
                                        <td><?= number_format($row['services_cost'], 2) ?> ₽</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Анализ по дням недели -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Анализ по дням недели</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>День недели</th>
                                        <th>Заказов</th>
                                        <th>Выручка</th>
                                        <th>Средний чек</th>
                                        <th>Уникальных клиентов</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($weekdayData as $row): ?>
                                    <tr>
                                        <td><?= $row['weekday'] ?></td>
                                        <td><?= $row['order_count'] ?></td>
                                        <td><?= number_format($row['total_revenue'], 2) ?> ₽</td>
                                        <td><?= number_format($row['avg_order_value'], 2) ?> ₽</td>
                                        <td><?= $row['unique_clients'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Анализ по часам -->
                <div class="card">
                    <div class="card-header">
                        <h6>Анализ по времени суток</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Час</th>
                                        <th>Заказов</th>
                                        <th>Выручка</th>
                                        <th>Средний чек</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hourlyData as $row): ?>
                                    <tr>
                                        <td><?= $row['hour'] ?>:00</td>
                                        <td><?= $row['order_count'] ?></td>
                                        <td><?= number_format($row['total_revenue'], 2) ?> ₽</td>
                                        <td><?= number_format($row['avg_order_value'], 2) ?> ₽</td>
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