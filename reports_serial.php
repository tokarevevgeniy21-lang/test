<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('reports:view');

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

function getSerialNumberAnalysis($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            -- Анализ по брендам
            b.name as brand_name,
            COUNT(DISTINCT o.serial_number) as unique_serials,
            COUNT(o.id) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            
            -- Статистика по серийным номерам
            AVG(LENGTH(o.serial_number)) as avg_serial_length,
            COUNT(DISTINCT CASE WHEN o.serial_number IS NOT NULL AND o.serial_number != '' THEN o.serial_number END) as total_serials_with_data,
            
            -- Повторные обращения
            COUNT(DISTINCT CASE WHEN serial_orders.order_count > 1 THEN o.serial_number END) as repeated_serials
        FROM orders o
        JOIN brands b ON o.brand_id = b.id
        LEFT JOIN (
            SELECT serial_number, COUNT(*) as order_count
            FROM orders 
            WHERE serial_number IS NOT NULL AND serial_number != ''
            GROUP BY serial_number
        ) serial_orders ON o.serial_number = serial_orders.serial_number
        WHERE o.created_at BETWEEN ? AND ?
        AND o.serial_number IS NOT NULL 
        AND o.serial_number != ''
        GROUP BY o.brand_id
        ORDER BY total_orders DESC
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

function getDeviceHistory($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            o.serial_number,
            b.name as brand_name,
            dm.name as model_name,
            dc.name as category_name,
            COUNT(o.id) as service_count,
            MIN(o.created_at) as first_service,
            MAX(o.created_at) as last_service,
            COALESCE(SUM(o.total_amount), 0) as total_spent,
            GROUP_CONCAT(DISTINCT s.name ORDER BY o.created_at DESC) as services_performed
        FROM orders o
        JOIN brands b ON o.brand_id = b.id
        JOIN device_models dm ON o.device_model_id = dm.id
        JOIN device_categories dc ON o.device_category_id = dc.id
        LEFT JOIN order_services os ON o.id = os.order_id
        LEFT JOIN services s ON os.service_id = s.id
        WHERE o.created_at BETWEEN ? AND ?
        AND o.serial_number IS NOT NULL 
        AND o.serial_number != ''
        GROUP BY o.serial_number
        HAVING service_count > 1
        ORDER BY service_count DESC
        LIMIT 50
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

$serialAnalysis = getSerialNumberAnalysis($startDate, $endDate);
$deviceHistory = getDeviceHistory($startDate, $endDate);

renderHeader('Анализ серийных номеров');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Анализ серийных номеров</h5>
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
                <!-- Статистика по брендам -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Статистика по серийным номерам по брендам</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Бренд</th>
                                        <th>Уникальных серийников</th>
                                        <th>Заказов</th>
                                        <th>Выручка</th>
                                        <th>Средняя длина</th>
                                        <th>С данными</th>
                                        <th>Повторные</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($serialAnalysis as $row): ?>
                                    <tr>
                                        <td><?= safe($row['brand_name']) ?></td>
                                        <td><?= $row['unique_serials'] ?></td>
                                        <td><?= $row['total_orders'] ?></td>
                                        <td><?= number_format($row['total_revenue'], 2) ?> ₽</td>
                                        <td><?= round($row['avg_serial_length'], 1) ?> симв.</td>
                                        <td><?= $row['total_serials_with_data'] ?></td>
                                        <td><?= $row['repeated_serials'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- История устройств -->
                <div class="card">
                    <div class="card-header">
                        <h6>История обслуживания устройств (топ-50 по количеству обращений)</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Серийный номер</th>
                                        <th>Бренд</th>
                                        <th>Модель</th>
                                        <th>Обращений</th>
                                        <th>Первое</th>
                                        <th>Последнее</th>
                                        <th>Потрачено</th>
                                        <th>Услуги</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deviceHistory as $row): ?>
                                    <tr>
                                        <td><code><?= safe($row['serial_number']) ?></code></td>
                                        <td><?= safe($row['brand_name']) ?></td>
                                        <td><?= safe($row['model_name']) ?></td>
                                        <td><?= $row['service_count'] ?></td>
                                        <td><?= date('d.m.Y', strtotime($row['first_service'])) ?></td>
                                        <td><?= date('d.m.Y', strtotime($row['last_service'])) ?></td>
                                        <td><?= number_format($row['total_spent'], 2) ?> ₽</td>
                                        <td>
                                            <small><?= safe(implode(', ', array_slice(explode(',', $row['services_performed']), 0, 3))) ?><?= substr_count($row['services_performed'], ',') > 2 ? '...' : '' ?></small>
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

<?php renderFooter(); ?>