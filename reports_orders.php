<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('reports:view');

// Устанавливаем период по умолчанию
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Функции для получения данных
function getOrdersByStatus($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            s.name as status_name,
            COUNT(*) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_amount,
            COALESCE(AVG(o.total_amount), 0) as avg_amount
        FROM orders o
        LEFT JOIN statuses s ON o.status_id = s.id
        WHERE o.created_at BETWEEN ? AND ?
        GROUP BY o.status_id
        ORDER BY order_count DESC
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

function getOrdersTimeline($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as order_count,
            COALESCE(SUM(total_amount), 0) as daily_revenue
        FROM orders 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

// Получаем данные
$statusData = getOrdersByStatus($startDate, $endDate);
$timelineData = getOrdersTimeline($startDate, $endDate);

renderHeader('Отчеты по заказам');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Отчет по заказам</h5>
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
                <!-- Статистика по статусам -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Заказы по статусам</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Статус</th>
                                                <th>Количество</th>
                                                <th>Сумма</th>
                                                <th>Средний чек</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($statusData as $row): ?>
                                            <tr>
                                                <td><?= safe($row['status_name']) ?></td>
                                                <td><?= $row['order_count'] ?></td>
                                                <td><?= number_format($row['total_amount'], 2) ?> ₽</td>
                                                <td><?= number_format($row['avg_amount'], 2) ?> ₽</td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Динамика заказов</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Дата</th>
                                                <th>Заказов</th>
                                                <th>Выручка</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($timelineData as $row): ?>
                                            <tr>
                                                <td><?= date('d.m.Y', strtotime($row['date'])) ?></td>
                                                <td><?= $row['order_count'] ?></td>
                                                <td><?= number_format($row['daily_revenue'], 2) ?> ₽</td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Кнопки экспорта -->
                <!-- Кнопки экспорта -->
<div class="d-flex gap-2 mb-3">
    <button class="btn btn-success" onclick="exportToExcel()">
        <i class="bi bi-file-earmark-excel"></i> Excel
    </button>
    <button class="btn btn-danger" onclick="window.print()">
        <i class="bi bi-printer"></i> Печать
    </button>
</div>
            </div>
        </div>
    </div>
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