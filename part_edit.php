<?php
require_once 'inc/layout.php';

requireAuth();
$isEdit = isset($_GET['id']);

if ($isEdit) {
    requirePermission('parts:edit');
    $partId = (int)$_GET['id'];
    $part = getPartById($partId);
    if (!$part) {
        $_SESSION['error'] = 'Запчасть не найдена';
        header('Location: parts.php');
        exit;
    }
} else {
    requirePermission('parts:create');
    $part = [
        'name' => '',
        'description' => '',
        'category_id' => '',
        'stock_quantity' => 0,
        'min_stock' => 1,
        'cost_price' => 0,
        'sale_price' => 0,
        'supplier' => '',
        'is_active' => 1
    ];
}

// Получаем категории
// Получаем категории
try {
    $stmt = $pdo->prepare("SELECT * FROM part_categories WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error getting categories: " . $e->getMessage());
    $categories = [];
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $categoryId = (int)$_POST['category_id'];
    $stockQuantity = (int)$_POST['stock_quantity'];
    $minStock = (int)$_POST['min_stock'];
    $costPrice = (float)$_POST['cost_price'];
    $salePrice = (float)$_POST['sale_price'];
    $supplier = trim($_POST['supplier']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Валидация
    if (empty($name)) {
        $_SESSION['error'] = 'Название запчасти обязательно';
    } elseif ($costPrice <= 0) {
        $_SESSION['error'] = 'Себестоимость должна быть больше 0';
    } elseif ($salePrice <= 0) {
        $_SESSION['error'] = 'Цена продажи должна быть больше 0';
    } elseif ($salePrice < $costPrice) {
        $_SESSION['error'] = 'Цена продажи не может быть ниже себестоимости';
    } else {
        try {
            if ($isEdit) {
                if (updatePart($partId, $name, $description, $categoryId, $stockQuantity, $minStock, $costPrice, $salePrice, $supplier, $isActive)) {
                    $_SESSION['success'] = 'Запчасть успешно обновлена';
                    header('Location: parts.php');
                    exit;
                }
            } else {
                if (createPart($name, $description, $categoryId, $stockQuantity, $minStock, $costPrice, $salePrice, $supplier)) {
                    $_SESSION['success'] = 'Запчасть успешно создана';
                    header('Location: parts.php');
                    exit;
                }
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
        }
    }
}

renderHeader($isEdit ? 'Редактирование запчасти' : 'Добавление запчасти');
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?= $isEdit ? 'Редактирование запчасти' : 'Добавление новой запчасти' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Название запчасти *</label>
                                <input type="text" class="form-control" name="name" 
                                       value="<?= htmlspecialchars($part['name']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Категория</label>
                                <select class="form-select" name="category_id">
                                    <option value="">-- Без категории --</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= $part['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Количество на складе *</label>
                                <input type="number" class="form-control" name="stock_quantity" 
                                       value="<?= $part['stock_quantity'] ?>" min="0" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Минимальный запас *</label>
                                <input type="number" class="form-control" name="min_stock" 
       value="<?= $part['min_stock_level'] ?? 1 ?>" min="1" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Описание</label>
                                <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($part['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Себестоимость (₽) *</label>
                                <input type="number" class="form-control" name="cost_price" 
                                       value="<?= $part['cost_price'] ?>" step="0.01" min="0" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Цена продажи (₽) *</label>
                                <input type="number" class="form-control" name="sale_price" 
                                       value="<?= $part['sale_price'] ?>" step="0.01" min="0" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Поставщик</label>
                                <input type="text" class="form-control" name="supplier" 
                                       value="<?= htmlspecialchars($part['supplier'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($isEdit): ?>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" 
                               id="is_active" <?= $part['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Активная запчасть</label>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <?= $isEdit ? 'Сохранить изменения' : 'Создать запчасть' ?>
    </button>
    <a href="parts.php" class="btn btn-secondary">Отмена</a>
    
    <!-- Ссылка на выдачу этой запчасти -->
    <?php if ($isEdit && hasPermission('parts:issue') && $part['stock_quantity'] > 0): ?>
    <a href="part_issue.php?part_id=<?= $part['id'] ?>" class="btn btn-success">
        <i class="bi bi-person-plus"></i> Выдать мастеру
    </a>
    <?php endif; ?>
</div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>