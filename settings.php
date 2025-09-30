<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('settings:view');

renderHeader('Системные настройки');
?>

<div class="row">
    <!-- Статусы заказов -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-list-check" style="font-size: 2rem;"></i>
                <h5 class="card-title">Статусы заказов</h5>
                <p class="card-text">Управление статусами заказов</p>
                <a href="settings_statuses.php" class="btn btn-primary">Управление</a>
            </div>
        </div>
    </div>

    <!-- Бренды -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-tag" style="font-size: 2rem;"></i>
                <h5 class="card-title">Бренды</h5>
                <p class="card-text">Управление брендами устройств</p>
                <a href="settings_brands.php" class="btn btn-primary">Управление</a>
            </div>
        </div>
    </div>

    <!-- Категории устройств -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-grid" style="font-size: 2rem;"></i>
                <h5 class="card-title">Категории</h5>
                <p class="card-text">Категории устройств</p>
                <a href="settings_categories.php" class="btn btn-primary">Управление</a>
            </div>
        </div>
    </div>

    <!-- Модели устройств -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-phone" style="font-size: 2rem;"></i>
                <h5 class="card-title">Модели</h5>
                <p class="card-text">Модели устройств</p>
                <a href="settings_models.php" class="btn btn-primary">Управление</a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
    <div class="card h-100">
        <div class="card-body text-center">
            <i class="bi bi-archive" style="font-size: 2rem;"></i>
            <h5 class="card-title">Неактивные записи</h5>
            <p class="card-text">Управление неактивными записями</p>
            <a href="settings_inactive.php" class="btn btn-warning">Управление</a>
        </div>
    </div>
</div>
    <!-- Реквизиты компании -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-building" style="font-size: 2rem;"></i>
                <h5 class="card-title">Компания</h5>
                <p class="card-text">Реквизиты компании</p>
                <a href="settings_company.php" class="btn btn-primary">Управление</a>
            </div>
        </div>
    </div>

    <!-- Настройки кассы -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-cash" style="font-size: 2rem;"></i>
                <h5 class="card-title">Касса</h5>
                <p class="card-text">Настройки кассы</p>
                <a href="settings_cash.php" class="btn btn-primary">Управление</a>
            </div>
        </div>
    </div>

    <!-- Источники клиентов -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-people" style="font-size: 2rem;"></i>
                <h5 class="card-title">Источники</h5>
                <p class="card-text">Источники клиентов</p>
                <a href="settings_sources.php" class="btn btn-primary">Управление</a>
            </div>
        </div>
    </div>

    <!-- Возрастные группы -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-person" style="font-size: 2rem;"></i>
                <h5 class="card-title">Возрастные группы</h5>
                <p class="card-text">Управление возрастными группами</p>
                <a href="settings_age_groups.php" class="btn btn-primary">Управление</a>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>