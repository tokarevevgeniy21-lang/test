<?php
require_once 'inc/layout.php';


requireAuth();
requirePermission('settings:manage');

$brands = CRUDController::getList('brands', 'is_active = 1', [], 'name');

$tableConfig = [
    'table' => 'device_models',
    'fields' => [
        'id' => 'int',
        'name' => 'string',
        'brand_id' => 'int',
        'normalized_name' => 'string',
        'is_active' => 'bool'
    ],
    'id_field' => 'id',
    'validation' => [
        'name' => ['required' => true, 'min_length' => 2, 'max_length' => 255],
        'brand_id' => ['required' => true]
    ]
];

// Обработка AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Автоматически генерируем normalized_name для create и update
    if (isset($_POST['name']) && ($_POST['action'] === 'create' || $_POST['action'] === 'update')) {
        $_POST['normalized_name'] = mb_strtolower(trim($_POST['name']));
    }
    
    $response = CRUDController::handleRequest(
        $tableConfig['table'], 
        $tableConfig['fields'], 
        $tableConfig['id_field'],
        $tableConfig['validation']
    );
    echo json_encode($response);
    exit;
}

$models = CRUDController::getList($tableConfig['table'], 'is_active = 1', [], 'name');

renderHeader('Управление моделями устройств');
?>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Добавить модель</h5>
            </div>
            <div class="card-body">
                <form id="crudForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="id" value="0">
                    <input type="hidden" name="normalized_name" value="">
                    
                    <div class="mb-3">
                        <label class="form-label">Бренд *</label>
                        <select class="form-select" name="brand_id" required>
                            <option value="">-- Выберите бренд --</option>
                            <?php foreach ($brands as $brand): ?>
                            <option value="<?= $brand['id'] ?>"><?= safe($brand['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Название модели *</label>
                        <input type="text" class="form-control" name="name" required maxlength="255">
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                        <label class="form-check-label">Активная</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mt-3">Сохранить</button>
                    <button type="button" class="btn btn-secondary mt-3" onclick="resetForm()">Сброс</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Список моделей</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Бренд</th>
                                <th>Модель</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Получаем названия брендов для отображения
                            $brandsMap = [];
                            foreach ($brands as $brand) {
                                $brandsMap[$brand['id']] = $brand['name'];
                            }
                            
                            foreach ($models as $item): 
                            ?>
                            <tr>
                                <td><?= safe($brandsMap[$item['brand_id']] ?? 'Unknown') ?></td>
                                <td><?= safe($item['name']) ?></td>
                                <td>
                                    <?php if ($item['is_active']): ?>
                                        <span class="badge bg-success">Активна</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Неактивна</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary edit-item" 
                                                data-item='<?= json_encode($item) ?>'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger delete-item" 
                                                data-id="<?= $item['id'] ?>">
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
    const form = document.getElementById('crudForm'); // Исправлено: было statusForm
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        
        // Явно устанавливаем значение для чекбокса is_active
        formData.set('is_active', form.querySelector('[name="is_active"]').checked ? '1' : '0');
        
        fetch('settings_models.php', { // Исправлено: было settings_statuses.php
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
            form.querySelector('[name="brand_id"]').value = item.brand_id; // Добавлено
            form.querySelector('[name="name"]').value = item.name;
            form.querySelector('[name="is_active"]').checked = !!item.is_active; // Исправлено
        });
    });
    
    // Удаление
    document.querySelectorAll('.delete-item').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Удалить эту модель?')) { // Исправлено текст
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', this.dataset.id);
                
                fetch('settings_models.php', { // Исправлено: было settings_statuses.php
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