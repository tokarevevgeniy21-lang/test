<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('salary_settings');

$userId = $_GET['user_id'] ?? null;
if (!$userId) {
    $_SESSION['error'] = 'Не указан пользователь';
    header('Location: users.php');
    exit();
}

// Получаем данные пользователя
$user = getFromTable('users u', 'u.*, r.name as role_name', 'u.id = ?', [$userId], 'LEFT JOIN roles r ON u.role_id = r.id');
if (!$user) {
    $_SESSION['error'] = 'Пользователь не найден';
    header('Location: users.php');
    exit();
}
$user = $user[0];

// Получаем текущее правило
$currentRule = getFromTable('salary_rules', '*', 'user_id = ? AND is_active = 1', [$userId]);
$currentRule = $currentRule[0] ?? null;

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'user_id' => $userId,
        'calculation_type' => $_POST['calculation_type'],
        'calculation_value' => (float) $_POST['calculation_value'],
        'parts_deduction_percent' => (float) $_POST['parts_deduction_percent'],
        'is_active' => 1
    ];
    
    try {
        if ($currentRule) {
            // Деактивируем старое правило
            saveToTable('salary_rules', ['is_active' => 0], 'id = ?', [$currentRule['id']]);
        }
        
        // Создаем новое правило
        saveToTable('salary_rules', $formData);
        
        $_SESSION['success'] = 'Персональное правило зарплаты сохранено';
        header('Location: user_edit.php?id=' . $userId);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Ошибка при сохранении: ' . $e->getMessage();
    }
}

renderHeader('Настройка зарплаты для ' . $user['full_name']);
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Настройка зарплаты для <?= safe($user['full_name']) ?></h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Тип расчета *</label>
                        <select class="form-select" name="calculation_type" required>
                            <option value="fixed" <?= ($currentRule['calculation_type'] ?? '') == 'fixed' ? 'selected' : '' ?>>Фиксированная сумма</option>
                            <option value="percent" <?= ($currentRule['calculation_type'] ?? '') == 'percent' ? 'selected' : '' ?>>Процент от заказа</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Значение *</label>
                        <input type="number" step="0.01" class="form-control" name="calculation_value" 
                               value="<?= $currentRule['calculation_value'] ?? '' ?>" required>
                        <div class="form-text">
                            Для фиксированной суммы - сумма в рублях, для процента - значение процента (например, 40 для 40%)
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Вычет за запчасти (%)</label>
                        <input type="number" step="0.01" class="form-control" name="parts_deduction_percent" 
                               value="<?= $currentRule['parts_deduction_percent'] ?? 0 ?>">
                        <div class="form-text">
                            Процент от стоимости запчастей, который вычитается из зарплаты мастера
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="user_edit.php?id=<?= $userId ?>" class="btn btn-secondary">Назад</a>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($currentRule): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Текущее правило</h6>
            </div>
            <div class="card-body">
                <p><strong>Тип:</strong> <?= $currentRule['calculation_type'] == 'fixed' ? 'Фиксированная сумма' : 'Процент' ?></p>
                <p><strong>Значение:</strong> <?= $currentRule['calculation_value'] ?></p>
                <p><strong>Вычет за запчасти:</strong> <?= $currentRule['parts_deduction_percent'] ?>%</p>
                <p><strong>Создано:</strong> <?= formatDateTime($currentRule['created_at']) ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php renderFooter(); ?>