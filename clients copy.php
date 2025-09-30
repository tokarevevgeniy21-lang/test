<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('clients:view');

// Пагинация
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Поиск и фильтры
$search = $_GET['search'] ?? '';
$sourceFilter = $_GET['source'] ?? '';
$ageGroupFilter = $_GET['age_group'] ?? '';
$typeFilter = $_GET['type'] ?? '';

// Получаем клиентов с пагинацией и фильтрами
function getClients($offset, $perPage, $search = '', $sourceFilter = '', $ageGroupFilter = '', $typeFilter = '') {
    global $pdo;
    
    $whereConditions = [];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.company_name LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($sourceFilter)) {
        $whereConditions[] = "c.source_id = ?";
        $params[] = $sourceFilter;
    }
    
    if (!empty($ageGroupFilter)) {
        $whereConditions[] = "c.age_group_id = ?";
        $params[] = $ageGroupFilter;
    }
    
    if (!empty($typeFilter)) {
        $whereConditions[] = "c.type = ?";
        $params[] = $typeFilter;
    }
    
    $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Получаем данные
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            ag.name as age_group_name,
            cs.name as source_name,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_spent,
            MAX(o.created_at) as last_order_date
        FROM clients c
        LEFT JOIN age_groups ag ON c.age_group_id = ag.id
        LEFT JOIN client_sources cs ON c.source_id = cs.id
        LEFT JOIN orders o ON c.id = o.client_id
        $whereClause
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT $offset, $perPage
    ");
    
    $stmt->execute($params);
    $clients = $stmt->fetchAll();
    
    // Получаем общее количество для пагинации
    $countStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id) 
        FROM clients c
        LEFT JOIN age_groups ag ON c.age_group_id = ag.id
        LEFT JOIN client_sources cs ON c.source_id = cs.id
        $whereClause
    ");
    
    $countStmt->execute($params);
    $totalClients = $countStmt->fetchColumn();
    
    return [
        'clients' => $clients,
        'total' => $totalClients
    ];
}

// Получаем фильтры
$sources = getFromTable('client_sources', '*', 'is_active = 1', [], 'ORDER BY name');
$ageGroups = getFromTable('age_groups', '*', 'is_active = 1', [], 'ORDER BY name');

// Получаем клиентов
$clientsData = getClients($offset, $perPage, $search, $sourceFilter, $ageGroupFilter, $typeFilter);
$clients = $clientsData['clients'];
$totalClients = $clientsData['total'];
$totalPages = ceil($totalClients / $perPage);

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_client' && requirePermission('clients:delete')) {
        $clientId = (int)$_POST['client_id'];
        
        // Проверяем есть ли заказы у клиента
        $checkOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE client_id = ?");
        $checkOrders->execute([$clientId]);
        $hasOrders = $checkOrders->fetchColumn();
        
        if ($hasOrders > 0) {
            $_SESSION['error'] = 'Нельзя удалить клиента с существующими заказами';
        } else {
            $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
            if ($stmt->execute([$clientId])) {
                $_SESSION['success'] = 'Клиент успешно удален';
            } else {
                $_SESSION['error'] = 'Ошибка при удалении клиента';
            }
        }
        
        header("Location: clients.php");
        exit;
    }
}

renderHeader('Управление клиентами');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Управление клиентами</h5>
                <span class="badge bg-secondary">Всего: <?= $totalClients ?></span>
            </div>
            <div class="card-body">
                <!-- Фильтры и поиск -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Поиск по имени, телефону, email...">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="source">
                            <option value="">Все источники</option>
                            <?php foreach ($sources as $source): ?>
                            <option value="<?= $source['id'] ?>" <?= $sourceFilter == $source['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($source['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="age_group">
                            <option value="">Все возрастные группы</option>
                            <?php foreach ($ageGroups as $group): ?>
                            <option value="<?= $group['id'] ?>" <?= $ageGroupFilter == $group['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($group['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="type">
                            <option value="">Все типы</option>
                            <option value="individual" <?= $typeFilter == 'individual' ? 'selected' : '' ?>>Физ. лицо</option>
                            <option value="legal" <?= $typeFilter == 'legal' ? 'selected' : '' ?>>Юр. лицо</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">Применить</button>
                    </div>
                    <div class="col-md-1">
                        <a href="clients.php" class="btn btn-secondary w-100">Сбросить</a>
                    </div>
                    <div class="col-md-1">
                        <a href="client_edit.php?action=create" class="btn btn-success w-100">
                            <i class="bi bi-plus"></i> Добавить
                        </a>
                    </div>
                </form>

                <!-- Таблица клиентов -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ФИО / Компания</th>
                                <th>Телефон</th>
                                <th>Email</th>
                                <th>Тип</th>
                                <th>Возрастная группа</th>
                                <th>Источник</th>
                                <th>Заказов</th>
                                <th>Потрачено</th>
                                <th>Последний заказ</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clients)): ?>
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">
                                    <i class="bi bi-people" style="font-size: 2rem;"></i>
                                    <p class="mt-2">Клиенты не найдены</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><?= $client['id'] ?></td>
                                    <td>
                                        <?php if ($client['is_company'] || $client['type'] === 'legal'): ?>
                                            <strong><?= htmlspecialchars($client['company_name']) ?></strong>
                                            <?php if ($client['director']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($client['director']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <strong><?= htmlspecialchars($client['full_name']) ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($client['phone']): ?>
                                            <a href="tel:<?= htmlspecialchars($client['phone']) ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($client['phone']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($client['email']): ?>
                                            <a href="mailto:<?= htmlspecialchars($client['email']) ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($client['email']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $client['type'] === 'legal' ? 'info' : 'primary' ?>">
                                            <?= $client['type'] === 'legal' ? 'Юр. лицо' : 'Физ. лицо' ?>
                                        </span>
                                    </td>
                                    <td><?= $client['age_group_name'] ? htmlspecialchars($client['age_group_name']) : '<span class="text-muted">—</span>' ?></td>
                                    <td><?= $client['source_name'] ? htmlspecialchars($client['source_name']) : '<span class="text-muted">—</span>' ?></td>
                                    <td>
                                        <span class="badge bg-<?= $client['order_count'] > 0 ? 'success' : 'secondary' ?>">
                                            <?= $client['order_count'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($client['total_spent'] > 0): ?>
                                            <span class="text-success fw-bold">
                                                <?= number_format($client['total_spent'], 2) ?> ₽
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($client['last_order_date']): ?>
                                            <?= date('d.m.Y', strtotime($client['last_order_date'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="client_edit.php?id=<?= $client['id'] ?>" class="btn btn-outline-primary" title="Редактировать">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="client_orders.php?id=<?= $client['id'] ?>" class="btn btn-outline-info" title="История заказов">
                                                <i class="bi bi-list-check"></i>
                                            </a>
                                            <?php if (hasPermission('clients:delete')): ?>
                                            <button class="btn btn-outline-danger" title="Удалить"
                                                    onclick="confirmDelete(<?= $client['id'] ?>, '<?= htmlspecialchars(addslashes($client['full_name'] ?: $client['company_name'])) ?>')">
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
function confirmDelete(clientId, clientName) {
    if (confirm(`Удалить клиента "${clientName}"?\n\nВнимание: Это действие нельзя отменить!`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_client">
            <input type="hidden" name="client_id" value="${clientId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Динамический поиск
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    const table = document.querySelector('table');
    const rows = table.querySelectorAll('tbody tr');
    
    searchInput.addEventListener('input', function() {
        const searchText = this.value.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchText) ? '' : 'none';
        });
    });
});
</script>

<?php renderFooter(); ?>