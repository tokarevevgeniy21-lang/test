<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('salary_settings');

// Получаем все правила
$rules = getFromTable('salary_rules sr', 
    'sr.*, r.name as role_name, u.full_name as user_name',
    '', [],
    'LEFT JOIN roles r ON sr.role_id = r.id 
     LEFT JOIN users u ON sr.user_id = u.id 
     ORDER BY sr.user_id IS NULL, sr.role_id, u.full_name');

// Получаем список ролей и пользователей для формы
$roles = getFromTable('roles');
$users = getFromTable('users', '*', 'is_active = 1 ORDER BY full_name');

// Обработка удаления правила
if (isset($_GET['delete_id'])) {
    try {
        // Вместо установки is_active = 0, просто удаляем правило
        $stmt = $pdo->prepare("DELETE FROM salary_rules WHERE id = ?");
        $stmt->execute([$_GET['delete_id']]);
        
        $_SESSION['success'] = 'Правило успешно удалено';
        header('Location: salary_rules.php');
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Ошибка при удалении правила: ' . $e->getMessage();
        header('Location: salary_rules.php');
        exit();
    }
}

// Обработка формы добавления/редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'role_id' => !empty($_POST['role_id']) ? $_POST['role_id'] : null,
        'user_id' => !empty($_POST['user_id']) ? $_POST['user_id'] : null,
        'calculation_type' => $_POST['calculation_type'],
        'calculation_value' => (float) $_POST['calculation_value'],
        'parts_deduction_percent' => (float) $_POST['parts_deduction_percent']
    ];
    
    // Валидация: либо роль, либо пользователь
    if (empty($formData['role_id']) && empty($formData['user_id'])) {
        $_SESSION['error'] = 'Необходимо указать либо роль, либо пользователя';
    } else {
        try {
            $ruleId = $_POST['rule_id'] ?? null;
            
            if ($ruleId) {
                // Редактирование существующего правила
                saveToTable('salary_rules', $formData, 'id = ?', [$ruleId]);
                $_SESSION['success'] = 'Правило обновлено';
            } else {
                // Создание нового правила
                // Проверяем, не существует ли уже правило для этой роли/пользователя
                if ($formData['user_id']) {
                    $existingRule = getFromTable('salary_rules', 'id', 
                        'user_id = ?', [$formData['user_id']]);
                } else {
                    $existingRule = getFromTable('salary_rules', 'id', 
                        'role_id = ? AND user_id IS NULL', [$formData['role_id']]);
                }
                
                if ($existingRule) {
                    $_SESSION['error'] = 'Для выбранной роли/пользователя уже существует правило';
                } else {
                    saveToTable('salary_rules', $formData);
                    $_SESSION['success'] = 'Правило добавлено';
                }
            }
            
            header('Location: salary_rules.php');
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Ошибка при сохранении: ' . $e->getMessage();
        }
    }
}

renderHeader('Управление правилами зарплаты');
?>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Добавить/редактировать правило</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="rule_id" id="rule_id" value="">
                    
                    <div class="mb-3">
                        <label class="form-label">Для роли</label>
                        <select class="form-select" name="role_id" id="role_id">
                            <option value="">-- Выберите роль --</option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>"><?= safe($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">ИЛИ</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Для пользователя</label>
                        <select class="form-select" name="user_id" id="user_id">
                            <option value="">-- Выберите пользователя --</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= safe($user['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Тип расчета *</label>
                        <select class="form-select" name="calculation_type" required>
                            <option value="fixed">Фиксированная сумма</option>
                            <option value="percent">Процент от заказа</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Значение *</label>
                        <input type="number" step="0.01" class="form-control" name="calculation_value" required>
                        <div class="form-text">
                            Для фиксированной суммы - сумма в рублях, для процента - значение процента
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Вычет за запчасти (%)</label>
                        <input type="number" step="0.01" class="form-control" name="parts_deduction_percent" value="0">
                        <div class="form-text">
                            Процент от стоимости запчастей, который вычитается из зарплаты
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Сохранить</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Текущие правила</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Для</th>
                                <th>Тип</th>
                                <th>Значение</th>
                                <th>Вычет %</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rules as $rule): ?>
                            <tr>
                                <td>
                                    <?php if ($rule['user_id']): ?>
                                        <span class="badge bg-info">Пользователь:</span> <?= safe($rule['user_name']) ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Роль:</span> <?= safe($rule['role_name']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= $rule['calculation_type'] == 'fixed' ? 'Фиксированная' : 'Процент' ?></td>
                                <td><?= $rule['calculation_value'] ?></td>
                                <td><?= $rule['parts_deduction_percent'] ?>%</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary edit-rule" 
                                                data-rule='<?= json_encode($rule) ?>'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="salary_rules.php?delete_id=<?= $rule['id'] ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('Удалить правило? Это действие нельзя отменить.')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (empty($rules)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-info-circle" style="font-size: 2rem;"></i>
                    <p class="mt-2">Нет настроенных правил зарплаты</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Обработка редактирования правила
    document.querySelectorAll('.edit-rule').forEach(button => {
        button.addEventListener('click', function() {
            const rule = JSON.parse(this.dataset.rule);
            
            document.getElementById('rule_id').value = rule.id;
            document.querySelector('[name="calculation_type"]').value = rule.calculation_type;
            document.querySelector('[name="calculation_value"]').value = rule.calculation_value;
            document.querySelector('[name="parts_deduction_percent"]').value = rule.parts_deduction_percent;
            
            if (rule.user_id) {
                document.getElementById('user_id').value = rule.user_id;
                document.getElementById('role_id').value = '';
            } else {
                document.getElementById('role_id').value = rule.role_id;
                document.getElementById('user_id').value = '';
            }
            
            // Прокрутка к форме
            document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
        });
    });
    
    // Взаимоисключающий выбор роли и пользователя
    document.getElementById('role_id').addEventListener('change', function() {
        if (this.value) {
            document.getElementById('user_id').value = '';
        }
    });
    
    document.getElementById('user_id').addEventListener('change', function() {
        if (this.value) {
            document.getElementById('role_id').value = '';
        }
    });
});
</script>

<?php renderFooter(); ?>