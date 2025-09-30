<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('reports:view');

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Функции для отчетов по сотрудникам
// Функции для отчетов по сотрудникам
function getMastersPerformance($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.full_name as master_name,
            COUNT(o.id) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(AVG(o.total_amount), 0) as avg_order_amount,
            COALESCE(SUM(o.salary_amount), 0) as total_salary,
            AVG(TIMESTAMPDIFF(HOUR, o.created_at, o.closed_at)) as avg_repair_time
        FROM orders o
        JOIN users u ON o.master_id = u.id
        WHERE o.created_at BETWEEN ? AND ? AND o.status_id IN (
            SELECT id FROM statuses WHERE name LIKE '%заверш%' OR name LIKE '%выдан%'
        )
        GROUP BY o.master_id
        ORDER BY total_revenue DESC
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

function getManagersPerformance($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.full_name as manager_name,
            COUNT(o.id) as total_orders,
            COUNT(DISTINCT o.client_id) as new_clients,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(AVG(o.total_amount), 0) as avg_order_amount,
            SUM(CASE WHEN o.warranty = 1 THEN 1 ELSE 0 END) as warranty_orders
        FROM orders o
        JOIN users u ON o.manager_id = u.id
        WHERE o.created_at BETWEEN ? AND ?
        GROUP BY o.manager_id
        ORDER BY total_revenue DESC
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

function getSalaryReport($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            u.full_name as employee_name,
            r.name as role_name,
            COUNT(sp.id) as payment_count,
            COUNT(DISTINCT sp.order_id) as orders_count,
            COALESCE(SUM(sp.amount), 0) as total_salary,
            COALESCE(AVG(sp.amount), 0) as avg_salary,
            MIN(sp.payment_date) as first_payment,
            MAX(sp.payment_date) as last_payment,
            COALESCE(SUM(o.total_amount), 0) as total_orders_amount
        FROM salary_payments sp
        JOIN users u ON sp.user_id = u.id
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN orders o ON sp.order_id = o.id
        WHERE sp.payment_date BETWEEN ? AND ?
        GROUP BY sp.user_id
        ORDER BY total_salary DESC
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

function getEmployeeSchedule($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            u.full_name as employee_name,
            r.name as role_name,
            u.salary_value,
            COUNT(DISTINCT ws.id) as scheduled_days,
            COUNT(DISTINCT CASE WHEN ws.shift_type = 'work' THEN ws.id END) as work_days,
            COUNT(DISTINCT CASE WHEN ws.shift_type = 'dayoff' THEN ws.id END) as dayoff_days,
            COUNT(DISTINCT CASE WHEN ws.shift_type = 'vacation' THEN ws.id END) as vacation_days,
            COUNT(DISTINCT CASE WHEN ws.shift_type = 'sick' THEN ws.id END) as sick_days,
            COUNT(DISTINCT o.id) as completed_orders,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(SUM(sp.amount), 0) as total_salary_paid,
            COALESCE(SUM(os.services_revenue), 0) as services_revenue,
            COALESCE(SUM(op.parts_revenue), 0) as parts_revenue
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN work_schedule ws ON u.id = ws.user_id AND ws.work_date BETWEEN ? AND ?
        LEFT JOIN orders o ON u.id = o.master_id AND o.created_at BETWEEN ? AND ?
        LEFT JOIN salary_payments sp ON u.id = sp.user_id AND sp.payment_date BETWEEN ? AND ?
        LEFT JOIN (
            SELECT order_id, COALESCE(SUM(price * quantity), 0) as services_revenue
            FROM order_services GROUP BY order_id
        ) os ON o.id = os.order_id
        LEFT JOIN (
            SELECT order_id, COALESCE(SUM(price * quantity), 0) as parts_revenue
            FROM order_parts GROUP BY order_id
        ) op ON o.id = op.order_id
        WHERE u.is_active = 1
        GROUP BY u.id
        ORDER BY r.name, u.full_name
    ");
    
    $stmt->execute([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
    return $stmt->fetchAll();
}
function getShiftStatistics($startDate, $endDate) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ws.shift_type,
                COUNT(ws.id) as shift_count,
                COUNT(DISTINCT ws.user_id) as unique_employees,
                GROUP_CONCAT(DISTINCT u.full_name) as employees_list,
                MIN(ws.work_date) as first_shift,
                MAX(ws.work_date) as last_shift
            FROM work_schedule ws
            JOIN users u ON ws.user_id = u.id
            WHERE ws.work_date BETWEEN ? AND ?
            GROUP BY ws.shift_type
            ORDER BY shift_count DESC
        ");
        
        $stmt->execute([$startDate, $endDate]);
        $result = $stmt->fetchAll();
        
        return $result ?: []; // Всегда возвращаем массив, даже пустой
    } catch (Exception $e) {
        error_log("Error in getShiftStatistics: " . $e->getMessage());
        return []; // Возвращаем пустой массив в случае ошибки
    }
}  

$mastersPerformance = getMastersPerformance($startDate, $endDate);
$managersPerformance = getManagersPerformance($startDate, $endDate);
$salaryReport = getSalaryReport($startDate, $endDate);
$scheduleData = getEmployeeSchedule($startDate, $endDate);
$shiftStatistics = getShiftStatistics($startDate, $endDate);
$shiftStats = getShiftStatistics($startDate, $endDate) ?? [];
renderHeader('Отчеты по сотрудникам');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Эффективность сотрудников</h5>
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
                <!-- Мастера -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Эффективность мастеров</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Мастер</th>
                                        <th>Заказов</th>
                                        <th>Выручка</th>
                                        <th>Средний чек</th>
                                        <th>Зарплата</th>
                                        <th>Среднее время ремонта</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mastersPerformance as $row): ?>
                                    <tr>
                                        <td><?= safe($row['master_name']) ?></td>
                                        <td><?= $row['total_orders'] ?></td>
                                        <td><?= number_format($row['total_revenue'], 2) ?> ₽</td>
                                        <td><?= number_format($row['avg_order_amount'], 2) ?> ₽</td>
                                        <td><?= number_format($row['total_salary'], 2) ?> ₽</td>
                                        <td><?= round($row['avg_repair_time'] ?? 0, 1) ?> часов</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Менеджеры -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Эффективность менеджеров</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Менеджер</th>
                                        <th>Заказов</th>
                                        <th>Новых клиентов</th>
                                        <th>Выручка</th>
                                        <th>Средний чек</th>
                                        <th>Гарантийные заказы</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($managersPerformance as $row): ?>
                                    <tr>
                                        <td><?= safe($row['manager_name']) ?></td>
                                        <td><?= $row['total_orders'] ?></td>
                                        <td><?= $row['new_clients'] ?></td>
                                        <td><?= number_format($row['total_revenue'], 2) ?> ₽</td>
                                        <td><?= number_format($row['avg_order_amount'], 2) ?> ₽</td>
                                        <td><?= $row['warranty_orders'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Зарплаты -->
<div class="card">
    <div class="card-header">
        <h6>Отчет по зарплатам</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Сотрудник</th>
                        <th>Должность</th>
                        <th>Выплат</th>
                        <th>Заказов</th>
                        <th>Общая сумма</th>
                        <th>Средняя выплата</th>
                        <th>Выручка по заказам</th>
                        <th>Период</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salaryReport as $row): ?>
                    <tr>
                        <td><?= safe($row['employee_name']) ?></td>
                        <td><?= safe($row['role_name']) ?></td>
                        <td><?= $row['payment_count'] ?></td>
                        <td><?= $row['orders_count'] ?? 'N/A' ?></td>
                        <td><?= number_format($row['total_salary'], 2) ?> ₽</td>
                        <td><?= number_format($row['avg_salary'], 2) ?> ₽</td>
                        <td><?= number_format($row['total_orders_amount'] ?? 0, 2) ?> ₽</td>
                        <td>
                            <?= $row['first_payment'] ? date('d.m.Y', strtotime($row['first_payment'])) : '' ?> - 
                            <?= $row['last_payment'] ? date('d.m.Y', strtotime($row['last_payment'])) : '' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- График работы и производительность сотрудников -->
<div class="card">
    <div class="card-header">
        <h6>График работы и производительность сотрудников</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Сотрудник</th>
                        <th>Должность</th>
                        <th>Оклад</th>
                        <th>Рабочих дней</th>
                        <th>Выходных</th>
                        <th>Отпуск</th>
                        <th>Больничный</th>
                        <th>Заказов</th>
                        <th>Выручка</th>
                        <th>Услуги</th>
                        <th>Запчасти</th>
                        <th>Зарплата</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scheduleData as $row): ?>
                    <tr>
                        <td><?= safe($row['employee_name']) ?></td>
                        <td><?= safe($row['role_name']) ?></td>
                        <td><?= number_format($row['salary_value'], 2) ?> ₽</td>
                        <td>
                            <span class="badge bg-success"><?= $row['work_days'] ?></span>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= $row['dayoff_days'] ?></span>
                        </td>
                        <td>
                            <span class="badge bg-info"><?= $row['vacation_days'] ?></span>
                        </td>
                        <td>
                            <span class="badge bg-warning"><?= $row['sick_days'] ?></span>
                        </td>
                        <td><?= $row['completed_orders'] ?></td>
                        <td><?= number_format($row['total_revenue'], 2) ?> ₽</td>
                        <td><?= number_format($row['services_revenue'], 2) ?> ₽</td>
                        <td><?= number_format($row['parts_revenue'], 2) ?> ₽</td>
                        <td class="fw-bold"><?= number_format($row['total_salary_paid'], 2) ?> ₽</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Детализация по сменам -->
        <div class="mt-4">
            <h6>Детализация смен</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Сотрудник</th>
                            <th>Дата</th>
                            <th>Тип смены</th>
                            <th>Заметки</th>
                            <th>Цвет</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Получим детализацию смен
                        $stmtDetails = $pdo->prepare("
                            SELECT 
                                u.full_name as employee_name,
                                ws.work_date,
                                ws.shift_type,
                                ws.shift_notes,
                                ws.color
                            FROM work_schedule ws
                            JOIN users u ON ws.user_id = u.id
                            WHERE ws.work_date BETWEEN ? AND ?
                            ORDER BY ws.work_date, u.full_name
                        ");
                        $stmtDetails->execute([$startDate, $endDate]);
                        $shiftDetails = $stmtDetails->fetchAll();
                        
                        foreach ($shiftDetails as $detail):
                        ?>
                        <tr>
                            <td><?= safe($detail['employee_name']) ?></td>
                            <td><?= date('d.m.Y', strtotime($detail['work_date'])) ?></td>
                            <td>
                                <span class="badge bg-<?= 
                                    $detail['shift_type'] === 'work' ? 'success' : 
                                    ($detail['shift_type'] === 'vacation' ? 'info' : 
                                    ($detail['shift_type'] === 'sick' ? 'warning' : 'secondary')) 
                                ?>">
                                    <?= $detail['shift_type'] === 'work' ? 'Работа' : 
                                       ($detail['shift_type'] === 'dayoff' ? 'Выходной' : 
                                       ($detail['shift_type'] === 'vacation' ? 'Отпуск' : 'Больничный')) ?>
                                </span>
                            </td>
                            <td><?= safe($detail['shift_notes']) ?></td>
                            <td>
                                <div style="width: 20px; height: 20px; background-color: <?= $detail['color'] ?>; border-radius: 3px;"></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<!-- Статистика по сменам -->
<?php if (!empty($shiftStats)): ?>
<div class="card mt-4">
    <div class="card-header">
        <h6>Статистика смен за период</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($shiftStats as $stat): ?>
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5>
                            <span class="badge bg-<?= 
                                $stat['shift_type'] === 'work' ? 'success' : 
                                ($stat['shift_type'] === 'vacation' ? 'info' : 
                                ($stat['shift_type'] === 'sick' ? 'warning' : 'secondary')) 
                            ?>">
                                <?= $stat['shift_type'] === 'work' ? 'Рабочих' : 
                                   ($stat['shift_type'] === 'dayoff' ? 'Выходных' : 
                                   ($stat['shift_type'] === 'vacation' ? 'Отпускных' : 'Больничных')) ?>
                            </span>
                        </h5>
                        <h3><?= $stat['shift_count'] ?></h3>
                        <small><?= $stat['unique_employees'] ?> сотрудников</small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card mt-4">
    <div class="card-body text-center text-muted">
        <i class="bi bi-calendar-x" style="font-size: 2rem;"></i>
        <p class="mt-2">Нет данных о сменах за выбранный период</p>
    </div>
</div>
<?php endif; ?>

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
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>