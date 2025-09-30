<?php
require_once 'inc/layout.php';


requireAuth();
requirePermission('settings:manage');

$table = $_GET['table'] ?? 'statuses';
$tables = [
    'statuses' => 'Статусы заказов',
    'brands' => 'Бренды',
    'device_categories' => 'Категории устройств',
    'device_models' => 'Модели устройств',
    'age_groups' => 'Возрастные группы',
    'client_sources' => 'Источники клиентов'
];

// Активация записи
if (isset($_GET['activate'])) {
    $id = (int)$_GET['activate'];
    saveToTable($table, ['is_active' => 1], 'id = ?', [$id]);
    $_SESSION['success'] = 'Запись активирована';
    header('Location: settings_inactive.php?table=' . $table);
    exit;
}

// Получаем неактивные записи
$inactiveItems = getFromTable($table, '*', 'is_active = 0', [], 'ORDER BY name');

renderHeader('Неактивные записи - ' . ($tables[$table] ?? $table));
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Управление неактивными записями</h5>
            </div>
            <div class="card-body">
                <!-- Выбор таблицы -->
                <div class="mb-3">
                    <label class="form-label">Выберите таблицу:</label>
                    <select class="form-select" onchange="location = 'settings_inactive.php?table=' + this.value">
                        <?php foreach ($tables as $key => $name): ?>
                        <option value="<?= $key ?>" <?= $table === $key ? 'selected' : '' ?>>
                            <?= $name ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Список неактивных записей -->
                <?php if (empty($inactiveItems)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-2">Нет неактивных записей</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Тип</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inactiveItems as $item): ?>
                                <tr>
                                    <td><?= safe($item['name'] ?? $item['title'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-secondary">Неактивен</span>
                                    </td>
                                    <td>
                                        <a href="settings_inactive.php?table=<?= $table ?>&activate=<?= $item['id'] ?>" 
                                           class="btn btn-sm btn-success"
                                           onclick="return confirm('Активировать запись?')">
                                            <i class="bi bi-arrow-clockwise"></i> Активировать
                                        </a>
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
            <a href="settings.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Назад к настройкам
            </a>
        </div>
    </div>
</div>

<?php renderFooter(); ?>