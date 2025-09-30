<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('services:view');

// Пагинация
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Поиск и фильтры
$search = $_GET['search'] ?? '';
$activeFilter = $_GET['active'] ?? '';

// Получаем услуги
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(name LIKE ? OR description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($activeFilter !== '') {
    $whereConditions[] = "is_active = ?";
    $params[] = (int)$activeFilter;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Получаем данные
$stmt = $pdo->prepare("
    SELECT * FROM services 
    $whereClause 
    ORDER BY name 
    LIMIT $offset, $perPage
");
$stmt->execute($params);
$services = $stmt->fetchAll();

// Общее количество
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM services $whereClause");
$countStmt->execute($params);
$totalServices = $countStmt->fetchColumn();
$totalPages = ceil($totalServices / $perPage);

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_service' && hasPermission('services:edit')) {
        $serviceId = (int)$_POST['service_id'];
        $isActive = (int)$_POST['is_active'];
        
        $stmt = $pdo->prepare("UPDATE services SET is_active = ? WHERE id = ?");
        if ($stmt->execute([$isActive, $serviceId])) {
            $_SESSION['success'] = 'Статус услуги обновлен';
        } else {
            $_SESSION['error'] = 'Ошибка при обновлении статуса';
        }
        header("Location: services.php");
        exit;
    }
    
    if ($action === 'delete_service' && hasPermission('services:delete')) {
        $serviceId = (int)$_POST['service_id'];
        
        // Проверяем, используется ли услуга в заказах
        $checkUsage = $pdo->prepare("SELECT COUNT(*) FROM order_services WHERE service_id = ?");
        $checkUsage->execute([$serviceId]);
        $usageCount = $checkUsage->fetchColumn();
        
        if ($usageCount > 0) {
            $_SESSION['error'] = 'Нельзя удалить услугу, которая используется в заказах';
        } else {
            $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
            if ($stmt->execute([$serviceId])) {
                $_SESSION['success'] = 'Услуга успешно удалена';
            } else {
                $_SESSION['error'] = 'Ошибка при удалении услуги';
            }
        }
        header("Location: services.php");
        exit;
    }
}

renderHeader('Управление услугами');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Управление услугами</h5>
                <span class="badge bg-secondary">Всего: <?= $totalServices ?></span>
            </div>
            <div class="card-body">
                <!-- Фильтры и поиск -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Поиск по названию или описанию...">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="active">
                            <option value="">Все статусы</option>
                            <option value="1" <?= $activeFilter === '1' ? 'selected' : '' ?>>Активные</option>
                            <option value="0" <?= $activeFilter === '0' ? 'selected' : '' ?>>Неактивные</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Применить</button>
                    </div>
                    <div class="col-md-2">
                        <a href="services.php" class="btn btn-secondary w-100">Сбросить</a>
                    </div>
                    <div class="col-md-2">
                        <?php if (hasPermission('services:create')): ?>
                        <a href="service_edit.php?action=create" class="btn btn-success w-100">
                            <i class="bi bi-plus"></i> Добавить
                        </a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Таблица услуг -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Описание</th>
                                <th>Цена</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($services)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-tools" style="font-size: 2rem;"></i>
                                    <p class="mt-2">Услуги не найдены</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($services as $service): ?>
                                <tr>
                                    <td><?= $service['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($service['name']) ?></strong>
                                    </td>
                                    <td><?= $service['description'] ? htmlspecialchars($service['description']) : '<span class="text-muted">—</span>' ?></td>
                                    <td>
                                        <span class="fw-bold text-success">
                                            <?= number_format($service['price'], 2) ?> ₽
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $service['is_active'] ? 'success' : 'secondary' ?>">
                                            <?= $service['is_active'] ? 'Активна' : 'Неактивна' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="service_edit.php?id=<?= $service['id'] ?>" class="btn btn-outline-primary" title="Редактировать">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="service_stats.php?id=<?= $service['id'] ?>" class="btn btn-outline-info" title="Статистика">
                                                <i class="bi bi-graph-up"></i>
                                            </a>
                                            <?php if (hasPermission('services:edit')): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_service">
                                                <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                                <input type="hidden" name="is_active" value="<?= $service['is_active'] ? 0 : 1 ?>">
                                                <button type="submit" class="btn btn-outline-<?= $service['is_active'] ? 'warning' : 'success' ?>" 
                                                        title="<?= $service['is_active'] ? 'Деактивировать' : 'Активировать' ?>">
                                                    <i class="bi bi-power"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if (hasPermission('services:delete')): ?>
                                            <button class="btn btn-outline-danger" title="Удалить"
                                                    onclick="confirmDelete(<?= $service['id'] ?>, '<?= htmlspecialchars(addslashes($service['name'])) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
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

<script>
function confirmDelete(serviceId, serviceName) {
    if (confirm(`Удалить услугу "${serviceName}"?\n\nВнимание: Это действие нельзя отменить!`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_service">
            <input type="hidden" name="service_id" value="${serviceId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php renderFooter(); ?>