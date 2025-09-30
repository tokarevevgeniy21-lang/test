<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('reports:view');

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Функции для аналитики клиентов
function getClientDemographics($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            ag.name as age_group,
            COUNT(DISTINCT c.id) as client_count,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value
        FROM clients c
        LEFT JOIN age_groups ag ON c.age_group_id = ag.id
        LEFT JOIN orders o ON c.id = o.client_id AND o.created_at BETWEEN ? AND ?
        WHERE c.created_at BETWEEN ? AND ?
        GROUP BY c.age_group_id
        ORDER BY client_count DESC
    ");
    
    $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
    return $stmt->fetchAll();
}

function getClientSources($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            cs.name as source_name,
            COUNT(DISTINCT c.id) as client_count,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value,
            MIN(o.created_at) as first_order_date,
            MAX(o.created_at) as last_order_date
        FROM clients c
        LEFT JOIN client_sources cs ON c.source_id = cs.id
        LEFT JOIN orders o ON c.id = o.client_id AND o.created_at BETWEEN ? AND ?
        WHERE c.created_at BETWEEN ? AND ?
        GROUP BY c.source_id
        ORDER BY client_count DESC
    ");
    
    $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
    return $stmt->fetchAll();
}

function getRepeatClients($startDate, $endDate) {
    global $pdo;
    
    // Сначала проверим структуру таблицы clients
    $stmt = $pdo->prepare("SHOW COLUMNS FROM clients LIKE 'full_name'");
    $stmt->execute();
    $hasFullName = $stmt->fetch();
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM clients LIKE 'name'");
    $stmt->execute();
    $hasName = $stmt->fetch();
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM clients LIKE 'first_name'");
    $stmt->execute();
    $hasFirstName = $stmt->fetch();
    
    // Определяем поле для имени клиента
    $nameField = 'phone'; // по умолчанию используем телефон
    if ($hasFullName) {
        $nameField = 'full_name';
    } elseif ($hasName) {
        $nameField = 'name';
    } elseif ($hasFirstName) {
        $nameField = "CONCAT(first_name, ' ', COALESCE(last_name, ''))";
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            $nameField as client_name,
            c.phone,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_spent,
            MIN(o.created_at) as first_order,
            MAX(o.created_at) as last_order,
            DATEDIFF(MAX(o.created_at), MIN(o.created_at)) as client_lifetime
        FROM clients c
        JOIN orders o ON c.id = o.client_id
        WHERE o.created_at BETWEEN ? AND ?
        GROUP BY c.id
        HAVING order_count > 1
        ORDER BY total_spent DESC
        LIMIT 20
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

$demographics = getClientDemographics($startDate, $endDate);
$sources = getClientSources($startDate, $endDate);
$repeatClients = getRepeatClients($startDate, $endDate);
$notifications = checkSystemNotifications();
renderHeader('Аналитика клиентов');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Аналитика клиентской базы</h5>
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
                <!-- Демография -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Демографическая аналитика</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Возрастная группа</th>
                                        <th>Клиентов</th>
                                        <th>Заказов</th>
                                        <th>Общая выручка</th>
                                        <th>Средний чек</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($demographics as $row): ?>
                                    <tr>
                                        <td><?= safe($row['age_group'] ?? 'Не указана') ?></td>
                                        <td><?= $row['client_count'] ?></td>
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

                <!-- Источники клиентов -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Источники привлечения клиентов</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Источник</th>
                                        <th>Клиентов</th>
                                        <th>Заказов</th>
                                        <th>Выручка</th>
                                        <th>Средний чек</th>
                                        <th>Период активности</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sources as $row): ?>
                                    <tr>
                                        <td><?= safe($row['source_name'] ?? 'Не указан') ?></td>
                                        <td><?= $row['client_count'] ?></td>
                                        <td><?= $row['order_count'] ?></td>
                                        <td><?= number_format($row['total_revenue'], 2) ?> ₽</td>
                                        <td><?= number_format($row['avg_order_value'], 2) ?> ₽</td>
                                        <td>
                                            <?= $row['first_order_date'] ? date('d.m.Y', strtotime($row['first_order_date'])) : '' ?> - 
                                            <?= $row['last_order_date'] ? date('d.m.Y', strtotime($row['last_order_date'])) : '' ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Постоянные клиенты -->
                <div class="card">
                    <div class="card-header">
                        <h6>Постоянные клиенты (топ-20 по сумме заказов)</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Клиент</th>
                                        <th>Телефон</th>
                                        <th>Заказов</th>
                                        <th>Общая сумма</th>
                                        <th>Первый заказ</th>
                                        <th>Последний заказ</th>
                                        <th>Lifetime (дней)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($repeatClients as $row): ?>
                                    <tr>
                                        <td><?= safe($row['client_name']) ?></td>
                                        <td><?= safe($row['phone']) ?></td>
                                        <td><?= $row['order_count'] ?></td>
                                        <td><?= number_format($row['total_spent'], 2) ?> ₽</td>
                                        <td><?= date('d.m.Y', strtotime($row['first_order'])) ?></td>
                                        <td><?= date('d.m.Y', strtotime($row['last_order'])) ?></td>
                                        <td><?= $row['client_lifetime'] ?> дней</td>
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