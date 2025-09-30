<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('company_settings');

// Упрощаем запрос - убираем JOIN
$users = getFromTable('users', 
    'id, username, full_name, role_id, telegram_chat_id', 
    'telegram_chat_id IS NOT NULL AND is_active = 1');

// Получаем roles отдельно для отображения
$roles = getFromTable('roles');
$roleNames = [];
foreach ($roles as $role) {
    $roleNames[$role['id']] = $role['name'];
}

// Обработка отвязки Telegram
if (isset($_GET['disconnect'])) {
    $userId = (int)$_GET['disconnect'];
    
    $stmt = $pdo->prepare("UPDATE users SET telegram_chat_id = NULL WHERE id = ?");
    if ($stmt->execute([$userId])) {
        $_SESSION['success'] = 'Telegram отвязан от пользователя';
    } else {
        $_SESSION['error'] = 'Ошибка отвязки Telegram';
    }
    
    header('Location: user_telegram_list.php');
    exit;
}

renderHeader('Пользователи с Telegram');
?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Пользователи подключенные к Telegram</h5>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-robot" style="font-size: 3rem;"></i>
                <p class="mt-2">Нет пользователей подключенных к Telegram</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Пользователь</th>
                            <th>Роль</th>
                            <th>Chat ID</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong><?= safe($user['full_name']) ?></strong><br>
                                <small class="text-muted"><?= safe($user['username']) ?></small>
                            </td>
                            <td>
    <span class="badge bg-secondary"><?= safe($roleNames[$user['role_id']] ?? 'Unknown') ?></span>
</td>
                            <td>
                                <code><?= safe($user['telegram_chat_id']) ?></code>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="user_edit.php?id=<?= $user['id'] ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="user_telegram_list.php?disconnect=<?= $user['id'] ?>" 
                                       class="btn btn-outline-danger"
                                       onclick="return confirm('Отвязать Telegram от пользователя?')">
                                        <i class="bi bi-unlink"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-3">
    <a href="settings_telegram.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Назад к настройкам
    </a>
</div>

<?php renderFooter(); ?>