<?php
require_once 'inc/layout.php';
require_once 'inc/auth.php';

requireAuth();

// Получаем данные текущего пользователя
$userId = $_SESSION['user_id'];
$user = getFromTable('users', '*', 'id = ?', [$userId]);
if (!$user) {
    $_SESSION['error'] = 'Пользователь не найден';
    header('Location: index.php');
    exit;
}
$user = $user[0];

// Обработка формы изменения профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        // Валидация
        if (empty($fullName)) {
            $_SESSION['error'] = 'ФИО обязательно для заполнения';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($email)) {
            $_SESSION['error'] = 'Некорректный email';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$fullName, $email, $phone, $userId]);
                
                $_SESSION['success'] = 'Профиль успешно обновлен';
                header('Location: profile.php');
                exit;
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Ошибка при обновлении профиля: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Валидация
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['error'] = 'Все поля пароля обязательны для заполнения';
        } elseif ($newPassword !== $confirmPassword) {
            $_SESSION['error'] = 'Новые пароли не совпадают';
        } elseif (strlen($newPassword) < 6) {
            $_SESSION['error'] = 'Пароль должен содержать минимум 6 символов';
        } else {
            // Проверяем текущий пароль
            if (password_verify($currentPassword, $user['password'])) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                
                $_SESSION['success'] = 'Пароль успешно изменен';
                header('Location: profile.php');
                exit;
            } else {
                $_SESSION['error'] = 'Текущий пароль неверен';
            }
        }
    }
}

// Получаем роль пользователя
$role = getFromTable('roles', 'name', 'id = ?', [$user['role_id']]);
$roleName = $role ? $role[0]['name'] : 'Неизвестно';

renderHeader('Мой профиль');
?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Информация профиля</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-3">
                        <label class="form-label">ФИО *</label>
                        <input type="text" class="form-control" name="full_name" 
                               value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Телефон</label>
                        <input type="tel" class="form-control" name="phone" 
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Роль</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($roleName) ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Дата регистрации</label>
                        <input type="text" class="form-control" 
                               value="<?= date('d.m.Y H:i', strtotime($user['created_at'])) ?>" disabled>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Смена пароля</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label class="form-label">Текущий пароль *</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Новый пароль *</label>
                        <input type="password" class="form-control" name="new_password" 
                               placeholder="Минимум 6 символов" required minlength="6">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Подтвердите новый пароль *</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">Сменить пароль</button>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Статистика</h5>
            </div>
            <div class="card-body">
                <?php
                // Статистика пользователя
                $ordersCount = getFromTable('orders', 'COUNT(*) as count', 'manager_id = ? OR master_id = ?', [$userId, $userId]);
                $ordersCount = $ordersCount[0]['count'] ?? 0;
                
                $completedOrders = getFromTable('orders', 'COUNT(*) as count', 
                    '(manager_id = ? OR master_id = ?) AND status_id IN (SELECT id FROM statuses WHERE is_completed = 1)', 
                    [$userId, $userId]);
                $completedOrders = $completedOrders[0]['count'] ?? 0;
                ?>
                
                <div class="d-flex justify-content-between mb-2">
                    <span>Всего заказов:</span>
                    <strong><?= $ordersCount ?></strong>
                </div>
                
                <div class="d-flex justify-content-between mb-2">
                    <span>Завершенных заказов:</span>
                    <strong><?= $completedOrders ?></strong>
                </div>
                
                <div class="d-flex justify-content-between">
                    <span>Эффективность:</span>
                    <strong><?= $ordersCount > 0 ? round(($completedOrders / $ordersCount) * 100) : 0 ?>%</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>