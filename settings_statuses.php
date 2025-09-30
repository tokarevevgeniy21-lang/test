<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('settings:manage');

// Определяем структуру таблицы статусов
$tableConfig = [
    'table' => 'statuses',
    'fields' => [
        'id' => 'int',
        'name' => 'string',
        'color' => 'string',
        'is_default' => 'bool',
        'is_active' => 'bool'
    ],
    'id_field' => 'id'
];

// Обработка AJAX запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = CRUDController::handleRequest($tableConfig['table'], $tableConfig['fields'], $tableConfig['id_field']);
    echo json_encode($response);
    exit;
}

// Получаем список статусов
$statuses = CRUDController::getList($tableConfig['table'], 'name');

renderHeader('Управление статусами заказов');
?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Добавить статус</h5>
            </div>
            <div class="card-body">
                <form id="statusForm">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="id" value="0">
                    
                    <div class="mb-3">
                        <label class="form-label">Название статуса *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Цвет</label>
                        <input type="color" class="form-control form-control-color" name="color" value="#007bff">
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default">
                                <label class="form-check-label">По умолчанию</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" checked>
                                <label class="form-check-label">Активный</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mt-3">Сохранить</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Список статусов</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Цвет</th>
                                <th>По умолчанию</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statuses as $status): ?>
                            <tr>
                                <td>
                                    <span class="badge" style="background-color: <?= $status['color'] ?>; color: white;">
                                        <?= safe($status['name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="width: 20px; height: 20px; background-color: <?= $status['color'] ?>; border-radius: 3px;"></div>
                                </td>
                                <td>
                                    <?php if ($status['is_default']): ?>
                                        <i class="bi bi-check-circle text-success"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary edit-item" 
                                                data-item='<?= json_encode($status) ?>'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger delete-item" 
                                                data-id="<?= $status['id'] ?>">
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
    const form = document.getElementById('statusForm');
    
    // В обработчике submit формы добавьте:
form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(form);
    
    // Явно устанавливаем значения для чекбоксов
    formData.set('is_default', form.querySelector('[name="is_default"]').checked ? '1' : '0');
    formData.set('is_active', form.querySelector('[name="is_active"]').checked ? '1' : '0');
    
    fetch('settings_statuses.php', {
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
            form.querySelector('[name="color"]').value = item.color;
            form.querySelector('[name="is_default"]').checked = item.is_default;
            form.querySelector('[name="is_active"]').checked = item.is_active;
        });
    });
    
    // Удаление
    document.querySelectorAll('.delete-item').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Удалить этот статус?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', this.dataset.id);
                
                fetch('settings_statuses.php', {
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