<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('orders:view');

global $pdo;

// Фильтры, поиск и пагинация
$statusFilter = $_GET['status'] ?? '';
$masterFilter = $_GET['master'] ?? '';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 30;

// Получаем статусы для фильтра
try {
    $stmt = $pdo->query("SELECT id, name FROM statuses WHERE is_active = 1 ORDER BY name");
    $statuses = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error getting statuses: " . $e->getMessage());
    $statuses = [];
}

// Получаем мастеров для фильтра
try {
    $stmt = $pdo->query("SELECT id, full_name FROM users WHERE role_id = 4 AND is_active = 1 ORDER BY full_name");
    $masters = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error getting masters: " . $e->getMessage());
    $masters = [];
}

$totalOrders = getTotalOrdersCount($statusFilter, $masterFilter, $search);
$totalPages = ceil($totalOrders / $perPage);

// Получаем заказы
$orders = getOrdersWithFilters($statusFilter, $masterFilter, $page, $perPage, $search);

renderHeader('Управление заказами');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Список заказов</h5>
                <a href="order_create.php" class="btn btn-success">
                    <i class="bi bi-plus"></i> Новый заказ
                </a>
            </div>
            <div class="card-body">
                <!-- Фильтры и поиск -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">Все статусы</option>
                            <?php foreach ($statuses as $status): ?>
                            <option value="<?= $status['id'] ?>" <?= $statusFilter == $status['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- В секции фильтров -->
<?php if ($_SESSION['user_role_id'] != 4): ?>
<!-- Показываем фильтр по мастеру только не-мастерам -->
<div class="col-md-2">
    <select class="form-select" name="master">
        <option value="">Все мастера</option>
        <?php foreach ($masters as $master): ?>
        <option value="<?= $master['id'] ?>" <?= $masterFilter == $master['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($master['full_name']) ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Поиск..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Применить</button>
                    </div>
                    <div class="col-md-2">
                        <a href="orders.php" class="btn btn-secondary w-100">Сбросить</a>
                    </div>
                </form>

                <!-- Таблица заказов -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Клиент</th>
                                <th>Устройство</th>
                                <th>Мастер</th>
                                <th>Статус</th>
                                <th>Сумма</th>
                                <th>Дата создания</th>
                                <th>Срок</th>
                                <th>Гарантия</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                    <p class="mt-2">Заказы не найдены</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): 
                                    $isWarranty = $order['warranty'] == 1;
                                    $isOverdue = $order['is_overdue'] == 1;
                                    $rowClass = '';
                                    if ($isWarranty) $rowClass = 'table-warning';
                                    if ($isOverdue) $rowClass = 'table-danger';
                                ?>
                                <tr onclick="window.location='order_view.php?id=<?= $order['id'] ?>'" 
                                    style="cursor: pointer;" class="<?= $rowClass ?>">
                                    <td>#<?= $order['id'] ?></td>
                                    <td>
                                        <?php
                                        $clientName = $order['client_full_name'] ?: $order['client_company_name'];
                                        echo htmlspecialchars($clientName ?: 'Без имени');
                                        ?>
                                        <?php if ($order['client_phone']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($order['client_phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $deviceInfo = [];
                                        if ($order['device_category']) $deviceInfo[] = $order['device_category'];
                                        if ($order['brand_name']) $deviceInfo[] = $order['brand_name'];
                                        if ($order['device_model']) $deviceInfo[] = $order['device_model'];
                                        echo htmlspecialchars(implode(' ', $deviceInfo) ?: '—');
                                        ?>
                                        <?php if ($order['serial_number']): ?>
                                            <br><small class="text-muted">SN: <?= htmlspecialchars($order['serial_number']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $order['master_name'] ? htmlspecialchars($order['master_name']) : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: <?= $order['status_color'] ?>; color: white;">
                                            <?= htmlspecialchars($order['status_name']) ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-success">
                                        <?= number_format($order['total_amount'] ?? 0, 2) ?> ₽
                                    </td>
                                    <td>
                                        <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
                                    </td>
                                    <td>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge bg-danger" title="Просрочено на <?= $order['overdue_days'] ?> дней">
                                                +<?= $order['overdue_days'] ?> дн.
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isWarranty): ?>
                                            <span class="badge bg-warning text-dark">Гарантия</span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
    <div class="btn-group btn-group-sm">
        <a href="order_view.php?id=<?= $order['id'] ?>" class="btn btn-outline-primary">
            <i class="bi bi-eye"></i>
        </a>
        <?php if (hasPermission('orders:edit') && $_SESSION['user_role_id'] != 4): ?>
        <!-- Скрываем кнопку редактирования для мастеров -->
        <a href="order_edit.php?id=<?= $order['id'] ?>" class="btn btn-outline-secondary">
            <i class="bi bi-pencil"></i>
        </a>
        <?php endif; ?>
    </div>
</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Пагинация -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Назад</a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Вперед</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>