<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('reports:view');

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

function getGeneralSummary($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            -- Основные метрики
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT c.id) as total_clients,
            COUNT(DISTINCT u.id) as active_employees,
            
            -- Финансы
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(SUM(o.profit), 0) as total_profit,
            COALESCE(SUM(o.salary_amount), 0) as total_salary,
            
            -- Средние значения
            COALESCE(AVG(o.total_amount), 0) as avg_order_value,
            COALESCE(AVG(DATEDIFF(o.closed_at, o.created_at)), 0) as avg_repair_time,
            
            -- Активность
            COUNT(DISTINCT o.master_id) as active_masters,
            COUNT(DISTINCT o.manager_id) as active_managers,
            
            -- Дополнительно
            SUM(CASE WHEN o.warranty = 1 THEN 1 ELSE 0 END) as warranty_orders,
            SUM(CASE WHEN o.delivery = 1 THEN 1 ELSE 0 END) as delivery_orders
        FROM orders o
        LEFT JOIN clients c ON o.client_id = c.id
        LEFT JOIN users u ON o.master_id = u.id OR o.manager_id = u.id
        WHERE o.created_at BETWEEN ? AND ?
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetch();
}

function getKPI($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            -- Конверсия
            COUNT(DISTINCT c.id) as total_clients,
            COUNT(DISTINCT o.client_id) as clients_with_orders,
            
            -- Эффективность
            COALESCE(SUM(o.total_amount) / NULLIF(COUNT(DISTINCT o.master_id), 0), 0) as revenue_per_master,
            COALESCE(SUM(o.total_amount) / NULLIF(COUNT(DISTINCT o.manager_id), 0), 0) as revenue_per_manager,
            
            -- Время
            AVG(TIMESTAMPDIFF(HOUR, o.created_at, o.closed_at)) as avg_repair_hours,
            
            -- Маржа
            COALESCE(SUM(o.profit) / NULLIF(SUM(o.total_amount), 0) * 100, 0) as profit_margin
        FROM orders o
        LEFT JOIN clients c ON o.client_id = c.id
        WHERE o.created_at BETWEEN ? AND ?
        AND o.closed_at IS NOT NULL
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetch();
}

$summary = getGeneralSummary($startDate, $endDate);
$kpi = getKPI($startDate, $endDate);

renderHeader('Общие отчеты');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Общие отчеты и KPI</h5>
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
                <!-- Ключевые метрики -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white text-center">
                            <div class="card-body">
                                <h6>Заказов</h6>
                                <h3><?= $summary['total_orders'] ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white text-center">
                            <div class="card-body">
                                <h6>Выручка</h6>
                                <h4><?= number_format($summary['total_revenue'], 0) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white text-center">
                            <div class="card-body">
                                <h6>Прибыль</h6>
                                <h4><?= number_format($summary['total_profit'], 0) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-white text-center">
                            <div class="card-body">
                                <h6>Клиентов</h6>
                                <h3><?= $summary['total_clients'] ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-danger text-white text-center">
                            <div class="card-body">
                                <h6>Средний чек</h6>
                                <h4><?= number_format($summary['avg_order_value'], 0) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white text-center">
                            <div class="card-body">
                                <h6>Время ремонта</h6>
                                <h4><?= round($summary['avg_repair_time'], 1) ?> дн.</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Ключевые показатели эффективности (KPI)</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h5>Конверсия</h5>
                                    <h3 class="text-primary">
                                        <?= $kpi['total_clients'] > 0 ? 
                                            number_format(($kpi['clients_with_orders'] / $kpi['total_clients']) * 100, 1) . '%' : 
                                            '0%' ?>
                                    </h3>
                                    <small>клиентов сделали заказ</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h5>На мастера</h5>
                                    <h3 class="text-success">
                                        <?= number_format($kpi['revenue_per_master'], 0) ?> ₽
                                    </h3>
                                    <small>выручка на мастера</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h5>На менеджера</h5>
                                    <h3 class="text-info">
                                        <?= number_format($kpi['revenue_per_manager'], 0) ?> ₽
                                    </h3>
                                    <small>выручка на менеджера</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h5>Маржа</h5>
                                    <h3 class="text-warning">
                                        <?= number_format($kpi['profit_margin'], 1) ?>%
                                    </h3>
                                    <small>рентабельность</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Детализация -->
                <div class="card">
                    <div class="card-header">
                        <h6>Детальная статистика</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Показатель</th>
                                        <th>Значение</th>
                                        <th>Показатель</th>
                                        <th>Значение</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Активных сотрудников</td>
                                        <td><?= $summary['active_employees'] ?></td>
                                        <td>Активных мастеров</td>
                                        <td><?= $summary['active_masters'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Активных менеджеров</td>
                                        <td><?= $summary['active_managers'] ?></td>
                                        <td>Гарантийных заказов</td>
                                        <td><?= $summary['warranty_orders'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Заказов с доставкой</td>
                                        <td><?= $summary['delivery_orders'] ?></td>
                                        <td>Общая зарплата</td>
                                        <td><?= number_format($summary['total_salary'], 2) ?> ₽</td>
                                    </tr>
                                    <tr>
                                        <td>Среднее время ремонта</td>
                                        <td><?= round($kpi['avg_repair_hours'], 1) ?> часов</td>
                                        <td>Оборот на сотрудника</td>
                                        <td><?= number_format($summary['total_revenue'] / max($summary['active_employees'], 1), 0) ?> ₽</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>