<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once 'inc/layout.php';

requireAuth();
requirePermission('employees');

$userId = $_GET['id'] ?? null;
$userData = [];
$salaryData = []; // Данные о зарплате из salary_rules
$pageTitle = "Добавление сотрудника";

// Если передан ID, загружаем данные пользователя
if ($userId) {
    requirePermission('users:edit');
    $userData = getFromTable('users', '*', 'id = ? AND is_active = 1', [$userId]);
    $userData = $userData[0] ?? [];
    $pageTitle = "Редактирование: " . ($userData['full_name'] ?? '');
    
    // ЗАГРУЖАЕМ ДАННЫЕ О ЗАРПЛАТЕ ИЗ ТАБЛИЦЫ salary_rules
    $salaryData = getFromTable('salary_rules', '*', 'user_id = ?', [$userId]);
    $salaryData = $salaryData[0] ?? [];
} else {
    requirePermission('users:create');
}

// Получаем список ролей для выпадающего списка
$roles = getFromTable('roles');

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Валидация CSRF токена
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Ошибка безопасности. Попробуйте еще раз.';
        header('Location: user_edit.php' . ($userId ? "?id=$userId" : ''));
        exit();
    }
    
    // ОТЛАДКА: что приходит в POST
    error_log("=== DEBUG user_edit.php ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("User ID: " . $userId);
    
    // ДАННЫЕ ДЛЯ ТАБЛИЦЫ users
    $formData = [
        'username' => $_POST['username'],
        'full_name' => $_POST['full_name'],
        'role_id' => $_POST['role_id'],
        'telegram_chat_id' => $_POST['telegram_chat_id'] ?: null,
        'schedule_color' => $_POST['schedule_color'],
        'is_active' => 1
    ];
    
    error_log("FormData: " . print_r($formData, true));
    
    // Обработка аватара
    if (!empty($_FILES['avatar']['name'])) {
        error_log("Avatar file detected");
        $avatarPath = uploadAvatar($_FILES['avatar'], $userId ?? 0);
        if ($avatarPath) {
            $formData['avatar'] = $avatarPath;
            error_log("Avatar saved to: " . $avatarPath);
        }
    }

    // Если это обновление пароля
    if (!empty($_POST['password'])) {
        $formData['password'] = password_hash($_POST['password'] . PEPPER, PASSWORD_DEFAULT);
        error_log("Password updated");
    }

    // Сохраняем данные
    try {
        if ($userId) {
            // Режим обновления пользователя
            error_log("Updating user ID: " . $userId);
            saveToTable('users', $formData, 'id = ?', [$userId]);
            
            // СОХРАНЯЕМ ДАННЫЕ О ЗАРПЛАТЕ В ТАБЛИЦУ salary_rules
            $salaryFormData = [
                'user_id' => $userId,
                'role_id' => $_POST['role_id'],
                'calculation_type' => $_POST['salary_type'],
                'calculation_value' => (float) $_POST['salary_value'],
                'parts_deduction_percent' => 0 // или добавьте поле в форму если нужно
            ];
            
            if (!empty($salaryData)) {
                // Обновляем существующее правило
                saveToTable('salary_rules', $salaryFormData, 'user_id = ?', [$userId]);
                error_log("Salary rule updated");
            } else {
                // Создаем новое правило
                saveToTable('salary_rules', $salaryFormData);
                error_log("Salary rule created");
            }
            
            $_SESSION['success'] = "Данные пользователя обновлены!";
            error_log("Update successful");
            
        } else {
            // Режим создания нового пользователя
            error_log("Creating new user");
            if (empty($_POST['password'])) {
                $_SESSION['error'] = "Пароль обязателен для нового пользователя!";
                error_log("Password required error");
            } else {
                $formData['password'] = password_hash($_POST['password'] . PEPPER, PASSWORD_DEFAULT);
                $newUserId = saveToTable('users', $formData); // нужно чтобы функция возвращала ID
                
                // СОЗДАЕМ ПРАВИЛО ЗАРПЛАТЫ ДЛЯ НОВОГО ПОЛЬЗОВАТЕЛЯ
                $salaryFormData = [
                    'user_id' => $newUserId,
                    'role_id' => $_POST['role_id'],
                    'calculation_type' => $_POST['salary_type'],
                    'calculation_value' => (float) $_POST['salary_value'],
                    'parts_deduction_percent' => 0
                ];
                saveToTable('salary_rules', $salaryFormData);
                
                $_SESSION['success'] = "Пользователь добавлен!";
                error_log("User created successfully");
                header('Location: users.php');
                exit();
            }
        }
    } catch (Exception $e) {
        error_log("ERROR: " . $e->getMessage());
        $_SESSION['error'] = "Ошибка при сохранении: " . $e->getMessage();
    }
    
    error_log("=== END DEBUG ===");
}

renderHeader($pageTitle);
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?= $pageTitle ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Логин *</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= safe($userData['username'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Пароль<?= $userId ? '' : ' *' ?></label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="<?= $userId ? 'Оставьте пустым, если не меняется' : '' ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">ФИО *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?= safe($userData['full_name'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="role_id" class="form-label">Роль *</label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>" 
                                        <?= ($userData['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>>
                                    <?= safe($role['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- ПОЛЯ ЗАРПЛАТЫ - ДАННЫЕ БЕРУТСЯ ИЗ salary_rules -->
                        <div class="col-md-6 mb-3">
                            <label for="salary_type" class="form-label">Тип зарплаты *</label>
                            <select class="form-select" id="salary_type" name="salary_type" required>
                                <option value="fixed" <?= ($salaryData['calculation_type'] ?? '') == 'fixed' ? 'selected' : '' ?>>Фиксированная</option>
                                <option value="percent" <?= ($salaryData['calculation_type'] ?? '') == 'percent' ? 'selected' : '' ?>>Процент от заказа</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="salary_value" class="form-label">Значение зарплаты *</label>
                            <input type="number" step="0.01" class="form-control" id="salary_value" 
                                   name="salary_value" value="<?= $salaryData['calculation_value'] ?? '' ?>" required>
                        </div>

                        <?php if (hasPermission('salary_settings') && ($userData['role_id'] ?? 0) == 4): ?>
                        <div class="mb-3">
                            <a href="user_salary_rules.php?user_id=<?= $userData['id'] ?>" class="btn btn-outline-info">
                                <i class="bi bi-currency-exchange"></i> Настроить персональные правила зарплаты
                            </a>
                        </div>
                        <?php endif; ?>                
                        
                        <div class="col-md-6 mb-3">
                            <label for="telegram_chat_id" class="form-label">Telegram Chat ID</label>
                            <input type="text" class="form-control" id="telegram_chat_id" name="telegram_chat_id" 
                                   value="<?= safe($userData['telegram_chat_id'] ?? '') ?>">
                        </div>
                        
                        <?php if ($userData): ?>
                        <div class="mb-3">
                            <label class="form-label">Telegram Chat ID</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?= safe($userData['telegram_chat_id'] ?? 'Не привязан') ?>" disabled>
                                <?php if (empty($userData['telegram_chat_id'])): ?>
                                    <button type="button" class="btn btn-outline-primary" onclick="generateTelegramCode()">
                                        <i class="bi bi-telegram"></i> Сгенерировать код
                                    </button>
                                <?php else: ?>
                                    <a href="user_telegram_list.php?disconnect=<?= $userData['id'] ?>" 
                                       class="btn btn-outline-danger"
                                       onclick="return confirm('Отвязать Telegram?')">
                                        <i class="bi bi-unlink"></i> Отвязать
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="form-text" id="telegramCodeInfo">
                                <?php if (empty($userData['telegram_chat_id'])): ?>
                                    Пользователь должен написать боту: <code>/start <?= $userData['id'] ?></code>
                                <?php else: ?>
                                    Telegram привязан: <?= safe($userData['telegram_chat_id']) ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <script>
                        function generateTelegramCode() {
                            document.getElementById('telegramCodeInfo').innerHTML = 
                                'Пользователь должен написать боту: <code>/start <?= $userData['id'] ?></code>';
                        }
                        </script>
                        <?php endif; ?>
                        
                        <div class="col-md-6 mb-3">
                            <label for="schedule_color" class="form-label">Цвет в расписании</label>
                            <input type="color" class="form-control form-control-color" id="schedule_color" 
                                   name="schedule_color" value="<?= safe($userData['schedule_color'] ?? '#3b7ddd') ?>">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="avatar" class="form-label">Аватар</label>
                            <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                            <?php if (!empty($userData['avatar'])): ?>
                                <div class="mt-2">
                                    <img src="<?= safe($userData['avatar']) ?>" alt="Аватар" class="img-thumbnail" style="max-height: 100px;">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="users.php" class="btn btn-secondary">Назад</a>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>