<?php
require_once 'inc/layout.php';

requireAuth();

// Получаем виджеты для текущей роли пользователя
$widgets = DashboardManager::getWidgetsForRole($_SESSION['user_role_id']);

renderHeader('Главная панель');
?>

<div class="row">
    <?php foreach ($widgets as $widget): ?>
    <div class="<?= $widget['size'] ?> mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?= $widget['title'] ?></h5>
            </div>
            <div class="card-body">
                <?= is_callable($widget['content']) ? $widget['content']() : $widget['content'] ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($widgets)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <h5>Добро пожаловать в <?= APP_NAME ?></h5>
                <p>Для вашей роли нет настроенных виджетов дашборда.</p>
                <p>Обратитесь к администратору для настройки.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php renderFooter(); ?>