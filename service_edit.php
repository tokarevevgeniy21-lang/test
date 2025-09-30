<?php
require_once 'inc/layout.php';

requireAuth();
$isEdit = isset($_GET['id']);

if ($isEdit) {
    requirePermission('services:edit');
    $serviceId = (int)$_GET['id'];
    $service = getServiceById($serviceId);
    if (!$service) {
        $_SESSION['error'] = 'Услуга не найдена';
        header('Location: services.php');
        exit;
    }
} else {
    requirePermission('services:create');
    $service = [
        'name' => '',
        'description' => '',
        'price' => '',
        'is_active' => 1
    ];
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Валидация
    if (empty($name)) {
        $_SESSION['error'] = 'Название услуги обязательно';
    } elseif ($price <= 0) {
        $_SESSION['error'] = 'Цена должна быть больше 0';
    } else {
        try {
            if ($isEdit) {
                if (updateService($serviceId, $name, $description, $price, $isActive)) {
                    $_SESSION['success'] = 'Услуга успешно обновлена';
                    header('Location: services.php');
                    exit;
                }
            } else {
                if (createService($name, $description, $price)) {
                    $_SESSION['success'] = 'Услуга успешно создана';
                    header('Location: services.php');
                    exit;
                }
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
        }
    }
}

renderHeader($isEdit ? 'Редактирование услуги' : 'Добавление услуги');
?>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?= $isEdit ? 'Редактирование услуги' : 'Добавление новой услуги' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Название услуги *</label>
                        <input type="text" class="form-control" name="name" 
                               value="<?= htmlspecialchars($service['name']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Описание</label>
                        <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($service['description']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Цена (₽) *</label>
                        <input type="number" class="form-control" name="price" 
                               value="<?= $service['price'] ?>" step="0.01" min="0" required>
                    </div>
                    
                    <?php if ($isEdit): ?>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" 
                               id="is_active" <?= $service['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Активная услуга</label>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?= $isEdit ? 'Сохранить изменения' : 'Создать услугу' ?>
                        </button>
                        <a href="services.php" class="btn btn-secondary">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>