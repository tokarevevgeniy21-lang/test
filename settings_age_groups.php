<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('settings:manage');
$test = getFromTable('age_groups');
error_log("Direct test count: " . count($test));
$tableConfig = [
    'table' => 'age_groups',
    'fields' => [
        'id' => 'int',
        'name' => 'string',
        'is_active' => 'bool'
    ],
    'id_field' => 'id'
];

// Обработка AJAX запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = CRUDController::handleRequest(
        $tableConfig['table'], 
        $tableConfig['fields'], 
        $tableConfig['id_field']
    );
    echo json_encode($response);
    exit;
}

// Получаем список возрастных групп (ПЕРЕИМЕНОВАЛ ПЕРЕМЕННУЮ)
// В age_groups.php - убрать параметры where
$ageGroups = getFromTable('age_groups', '*', 'is_active = 1', [], 'name ASC');
renderHeader('Управление возрастными группами');
?>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Добавить возрастную группу</h5>
            </div>
            <div class="card-body">
                <form id="crudForm">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="id" value="0">
                    
                    <div class="mb-3">
                        <label class="form-label">Название *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                        <label class="form-check-label">Активная</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mt-3">Сохранить</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Список возрастных групп</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ageGroups as $group): ?> <!-- ИСПРАВИЛ НА $group -->
                            <tr>
                                <td><?= safe($group['name']) ?></td>
                                <td>
                                    <?php if ($group['is_active']): ?>
                                        <span class="badge bg-success">Активна</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Неактивна</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary edit-item" 
                                                data-item='<?= json_encode($group) ?>'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger delete-item" 
                                                data-id="<?= $group['id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
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
    const form = document.getElementById('crudForm');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        formData.set('is_active', form.querySelector('[name="is_active"]').checked ? '1' : '0');
        
        fetch('settings_age_groups.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Ошибка: ' + data.message);
            }
        });
    });
    
    // Редактирование
    document.querySelectorAll('.edit-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const item = JSON.parse(this.dataset.item);
            form.querySelector('[name="action"]').value = 'update';
            form.querySelector('[name="id"]').value = item.id;
            form.querySelector('[name="name"]').value = item.name;
            form.querySelector('[name="is_active"]').checked = item.is_active;
        });
    });
    
    // Удаление
    document.querySelectorAll('.delete-item').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Удалить эту возрастную группу?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', this.dataset.id);
                
                fetch('settings_age_groups.php', {
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