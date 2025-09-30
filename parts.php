<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('parts:view');

// Пагинация
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Поиск и фильтры
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$stockFilter = $_GET['stock'] ?? '';
$activeFilter = $_GET['active'] ?? '';

// Получаем категории запчастей
$categories = getFromTable('part_categories', '*', 'is_active = 1', [], 'ORDER BY name');

// Получаем запчасти
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.supplier LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($categoryFilter)) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $categoryFilter;
}

if ($stockFilter === 'low') {
    $whereConditions[] = "p.stock_quantity <= p.min_stock_level";
} elseif ($stockFilter === 'out') {
    $whereConditions[] = "p.stock_quantity = 0";
}

if ($activeFilter !== '') {
    $whereConditions[] = "p.is_active = ?";
    $params[] = (int)$activeFilter;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Получаем данные
$stmt = $pdo->prepare("
    SELECT p.*, pc.name as category_name 
    FROM parts p 
    LEFT JOIN part_categories pc ON p.category_id = pc.id 
    $whereClause 
    ORDER BY p.name 
    LIMIT $offset, $perPage
");
$stmt->execute($params);
$parts = $stmt->fetchAll();

// Общее количество
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM parts p $whereClause");
$countStmt->execute($params);
$totalParts = $countStmt->fetchColumn();
$totalPages = ceil($totalParts / $perPage);

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_part' && hasPermission('parts:edit')) {
        $partId = (int)$_POST['part_id'];
        $isActive = (int)$_POST['is_active'];
        
        $stmt = $pdo->prepare("UPDATE parts SET is_active = ? WHERE id = ?");
        if ($stmt->execute([$isActive, $partId])) {
            $_SESSION['success'] = 'Статус запчасти обновлен';
        } else {
            $_SESSION['error'] = 'Ошибка при обновлении статуса';
        }
        header("Location: parts.php");
        exit;
    }
    
    if ($action === 'delete_part' && hasPermission('parts:delete')) {
        $partId = (int)$_POST['part_id'];
        
        // Проверяем, используется ли запчасть в заказах
        $checkUsage = $pdo->prepare("SELECT COUNT(*) FROM order_parts WHERE part_id = ?");
        $checkUsage->execute([$partId]);
        $usageCount = $checkUsage->fetchColumn();
        
        if ($usageCount > 0) {
            $_SESSION['error'] = 'Нельзя удалить запчасть, которая используется в заказах';
        } else {
            $stmt = $pdo->prepare("DELETE FROM parts WHERE id = ?");
            if ($stmt->execute([$partId])) {
                $_SESSION['success'] = 'Запчасть успешно удалена';
            } else {
                $_SESSION['error'] = 'Ошибка при удалении запчасти';
            }
        }
        header("Location: parts.php");
        exit;
    }
}

renderHeader('Управление запчастями');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Управление запчастями</h5>
                <span class="badge bg-secondary">Всего: <?= $totalParts ?></span>
            </div>
            <div class="card-body">
                <!-- Фильтры и поиск -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Поиск по названию, описанию, поставщику...">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="category">
                            <option value="">Все категории</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= $categoryFilter == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="stock">
                            <option value="">Все</option>
                            <option value="low" <?= $stockFilter == 'low' ? 'selected' : '' ?>>Низкий запас</option>
                            <option value="out" <?= $stockFilter == 'out' ? 'selected' : '' ?>>Нет в наличии</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="active">
                            <option value="">Все статусы</option>
                            <option value="1" <?= $activeFilter === '1' ? 'selected' : '' ?>>Активные</option>
                            <option value="0" <?= $activeFilter === '0' ? 'selected' : '' ?>>Неактивные</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">Применить</button>
                    </div>
                    <div class="col-md-1">
                        <a href="parts.php" class="btn btn-secondary w-100">Сбросить</a>
                    </div>
                    <div class="col-md-1">
                        <?php if (hasPermission('parts:create')): ?>
                        <a href="part_edit.php?action=create" class="btn btn-success w-100">
                            <i class="bi bi-plus"></i> Добавить
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-2">
    <?php if (hasPermission('parts:view')): ?>
    <a href="parts_master_list.php" class="btn btn-info w-100">
        <i class="bi bi-person-check"></i> Выданные
    </a>
    <?php endif; ?>
</div>
                </form>

                <!-- Таблица запчастей -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Категория</th>
                                <th>На складе</th>
                                <th>Мин. запас</th>
                                <th>Себестоимость</th>
                                <th>Цена продажи</th>
                                <th>Поставщик</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($parts)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="bi bi-box-seam" style="font-size: 2rem;"></i>
                                    <p class="mt-2">Запчасти не найдены</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($parts as $part): ?>
                                <tr>
                                    <td><?= $part['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($part['name']) ?></strong>
                                        <?php if ($part['description']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($part['description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $part['category_name'] ? htmlspecialchars($part['category_name']) : '<span class="text-muted">—</span>' ?></td>
                                    <td>
                                        <span class="fw-bold <?= $part['stock_quantity'] == 0 ? 'text-danger' : ($part['stock_quantity'] <= $part['min_stock_level'] ? 'text-warning' : 'text-success') ?>">
                                            <?= $part['stock_quantity'] ?>
                                        </span>
                                    </td>
                                    <td><?= $part['min_stock_level'] ?></td>
                                    <td><?= number_format($part['cost_price'], 2) ?> ₽</td>
                                    <td>
                                        <span class="fw-bold text-success">
                                            <?= number_format($part['sale_price'], 2) ?> ₽
                                        </span>
                                    </td>
                                    <td><?= $part['supplier'] ? htmlspecialchars($part['supplier']) : '<span class="text-muted">—</span>' ?></td>
                                    <td>
                                        <span class="badge bg-<?= $part['is_active'] ? 'success' : 'secondary' ?>">
                                            <?= $part['is_active'] ? 'Активна' : 'Неактивна' ?>
                                        </span>
                                    </td>
                                    <td>
    <div class="btn-group btn-group-sm">
        <a href="part_edit.php?id=<?= $part['id'] ?>" class="btn btn-outline-primary" title="Редактировать">
            <i class="bi bi-pencil"></i>
        </a>
        <a href="part_stats.php?id=<?= $part['id'] ?>" class="btn btn-outline-info" title="Статистика">
            <i class="bi bi-graph-up"></i>
        </a>
        
        <!-- Новая кнопка выдачи запчасти -->
        <?php if (hasPermission('parts:issue') && $part['stock_quantity'] > 0): ?>
        <a href="parts_quick_issue.php?id=<?= $part['id'] ?>" class="btn btn-outline-success" title="Выдать мастеру">
            <i class="bi bi-person-plus"></i>
        </a>
        <?php endif; ?>
        
        <?php if (hasPermission('parts:edit')): ?>
        <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="toggle_part">
            <input type="hidden" name="part_id" value="<?= $part['id'] ?>">
            <input type="hidden" name="is_active" value="<?= $part['is_active'] ? 0 : 1 ?>">
            <button type="submit" class="btn btn-outline-<?= $part['is_active'] ? 'warning' : 'success' ?>" 
                    title="<?= $part['is_active'] ? 'Деактивировать' : 'Активировать' ?>">
                <i class="bi bi-power"></i>
            </button>
        </form>
        <?php endif; ?>
        
        <?php if (hasPermission('parts:delete')): ?>
        <button class="btn btn-outline-danger" title="Удалить"
                onclick="confirmDelete(<?= $part['id'] ?>, '<?= htmlspecialchars(addslashes($part['name'])) ?>')">
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
function confirmDelete(partId, partName) {
    if (confirm(`Удалить запчасть "${partName}"?\n\nВнимание: Это действие нельзя отменить!`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_part">
            <input type="hidden" name="part_id" value="${partId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php renderFooter(); ?>