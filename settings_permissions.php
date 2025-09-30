<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('settings:manage');

// Получаем список всех ролей
$roles = getFromTable('roles', '*', '', [], 'ORDER BY name');

// Получаем список всех уникальных страниц/разрешений
try {
    $stmt = $pdo->prepare("SELECT DISTINCT page FROM role_permissions ORDER BY page");
    $stmt->execute();
    $pages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error getting pages: " . $e->getMessage());
    $pages = [];
}

// Обработка AJAX запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    try {
        if ($_POST['action'] === 'add_permission') {
            $role_id = (int)$_POST['role_id'];
            $page = trim($_POST['page']);
            
            // Проверяем, не существует ли уже такое разрешение
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM role_permissions WHERE role_id = ? AND page = ?");
            $stmt->execute([$role_id, $page]);
            $exists = $stmt->fetch();
            
            if ($exists['count'] > 0) {
                $response['message'] = 'Это разрешение уже существует для данной роли';
            } else {
                // Добавляем новое разрешение
                $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, page) VALUES (?, ?)");
                $stmt->execute([$role_id, $page]);
                
                $response['success'] = true;
                $response['message'] = 'Разрешение успешно добавлено';
            }
        } 
        elseif ($_POST['action'] === 'delete_permission') {
            $id = (int)$_POST['id'];
            
            // Удаляем разрешение
            $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE id = ?");
            $stmt->execute([$id]);
            
            $response['success'] = true;
            $response['message'] = 'Разрешение успешно удалено';
        }
        elseif ($_POST['action'] === 'add_page') {
            $page = trim($_POST['new_page']);
            
            // Проверяем, не существует ли уже такая страница
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM role_permissions WHERE page = ?");
            $stmt->execute([$page]);
            $exists = $stmt->fetch();
            
            if ($exists['count'] > 0) {
                $response['message'] = 'Эта страница уже существует';
            } else {
                // Добавляем новую страницу для администратора по умолчанию
                $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, page) VALUES (1, ?)");
                $stmt->execute([$page]);
                
                $response['success'] = true;
                $response['message'] = 'Страница успешно добавлена';
            }
        }
    } catch (Exception $e) {
        $response['message'] = 'Ошибка: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Получаем все разрешения с информацией о ролях
try {
    $stmt = $pdo->prepare("
        SELECT rp.*, r.name as role_name 
        FROM role_permissions rp 
        LEFT JOIN roles r ON rp.role_id = r.id 
        ORDER BY r.name, rp.page
    ");
    $stmt->execute();
    $permissions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error getting permissions: " . $e->getMessage());
    $permissions = [];
}

renderHeader('Управление разрешениями ролей');
?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Добавить разрешение</h5>
            </div>
            <div class="card-body">
                <form id="addPermissionForm">
                    <input type="hidden" name="action" value="add_permission">
                    
                    <div class="mb-3">
                        <label class="form-label">Роль</label>
                        <select class="form-select" name="role_id" required>
                            <option value="">-- Выберите роль --</option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>"><?= safe($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Страница/Разрешение</label>
                        <select class="form-select" name="page" required>
                            <option value="">-- Выберите страницу --</option>
                            <?php foreach ($pages as $page): ?>
                            <option value="<?= $page['page'] ?>"><?= safe($page['page']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Добавить новую страницу</h5>
            </div>
            <div class="card-body">
                <form id="addPageForm">
                    <input type="hidden" name="action" value="add_page">
                    
                    <div class="mb-3">
                        <label class="form-label">Новая страница/Разрешение</label>
                        <input type="text" class="form-control" name="new_page" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Список разрешений</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Роль</th>
                                <th>Страница/Разрешение</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissions as $perm): ?>
                            <tr>
                                <td><?= safe($perm['role_name']) ?></td>
                                <td><?= safe($perm['page']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-danger delete-permission" data-id="<?= $perm['id'] ?>">
                                        <i class="bi bi-trash"></i> Удалить
                                    </button>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Добавление разрешения
    const addPermissionForm = document.getElementById('addPermissionForm');
    addPermissionForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(addPermissionForm);
        
        fetch('settings_permissions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                location.reload();
            }
        });
    });
    
    // Добавление новой страницы
    const addPageForm = document.getElementById('addPageForm');
    addPageForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(addPageForm);
        
        fetch('settings_permissions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                location.reload();
            }
        });
    });
    
    // Удаление разрешения
    document.querySelectorAll('.delete-permission').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Удалить это разрешение?')) {
                const formData = new FormData();
                formData.append('action', 'delete_permission');
                formData.append('id', this.dataset.id);
                
                fetch('settings_permissions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        });
    });
});
</script>

<?php renderFooter(); ?>