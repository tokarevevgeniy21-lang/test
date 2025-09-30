<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('employees');

$users = getFromTable(
    'users u', 
    'u.*, r.name as role_name', 
    'u.is_active = 1', 
    [], 
    'LEFT JOIN roles r ON u.role_id = r.id'
);

// Обработка удаления пользователя
if (isset($_GET['delete_id']) && hasPermission('users:delete')) {
    saveToTable('users', ['is_active' => 0], 'id = ?', [$_GET['delete_id']]);
    $_SESSION['success'] = 'Пользователь успешно удален';
    header('Location: users.php');
    exit();
}

renderHeader('Управление пользователями');

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Сотрудники</h1>
    <?php if (hasPermission('users:create')): ?>
        <a href="user_edit.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Добавить сотрудника
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ФИО</th>
                        <th>Логин</th>
                        <th>Роль</th>

                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= safe($user['full_name']) ?></td>
                        <td><?= safe($user['username']) ?></td>
                        <td><span class="badge bg-secondary"><?= safe($user['role_name']) ?></span></td>

                        
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if (hasPermission('users:edit')): ?>
                                <a href="user_edit.php?id=<?= $user['id'] ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('users:delete')): ?>
                                <a href="users.php?delete_id=<?= $user['id'] ?>" 
                                   class="btn btn-outline-danger" 
                                   onclick="return confirm('Уволить сотрудника?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php renderFooter(); ?>