<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('company_settings');

// Получаем текущие настройки
$configDir = dirname(__FILE__) . '/config';
$configFile = $configDir . '/telegram.php';
// Диагностика прав
echo "<div style='display:none;'>";
echo "Config dir: " . $configDir . "<br>";
echo "Config file: " . $configFile . "<br>";
echo "Dir exists: " . (is_dir($configDir) ? 'Yes' : 'No') . "<br>";
echo "Dir writable: " . (is_writable($configDir) ? 'Yes' : 'No') . "<br>";
echo "File exists: " . (file_exists($configFile) ? 'Yes' : 'No') . "<br>";
if (file_exists($configFile)) {
    echo "File writable: " . (is_writable($configFile) ? 'Yes' : 'No') . "<br>";
}
echo "</div>";
// Создаем директорию config если нет
if (!is_dir($configDir)) {
    if (!mkdir($configDir, 0755, true)) {
        $_SESSION['error'] = 'Не удалось создать директорию config!';
    }
}

$config = file_exists($configFile) ? require $configFile : [];
// Создаем базовый конфиг если файла нет
if (!file_exists($configFile)) {
    $defaultConfig = [
        'bot_token' => '',
        'webhook_url' => '',
        'admin_chat_id' => '',
        'notifications' => [
            'new_orders' => true,
            'status_changes' => true,
            'comments' => true,
            'warranty' => true
        ]
    ];
    
    $configContent = "<?php\n\nreturn " . var_export($defaultConfig, true) . ";\n";
    file_put_contents($configFile, $configContent);
    $config = $defaultConfig;
}
// Получаем статистику - УПРОЩАЕМ запрос
$telegramUsers = getFromTable('users', 'COUNT(*) as count', 
    'telegram_chat_id IS NOT NULL AND is_active = 1');
$totalUsers = getFromTable('users', 'COUNT(*) as count', 'is_active = 1');

// Обработка действий
$action = $_GET['action'] ?? '';
$message = '';

if ($action === 'set_webhook') {
    try {
        require_once 'telegram/BotCore.php';
        $bot = new TelegramBot($config['bot_token']);
        $result = $bot->setWebhook($config['webhook_url']);
        
        if ($result['ok']) {
            $_SESSION['success'] = 'Webhook успешно установлен!';
        } else {
            $_SESSION['error'] = 'Ошибка: ' . $result['description'];
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
    }
    header('Location: settings_telegram.php');
    exit;
}

if ($action === 'delete_webhook') {
    try {
        require_once 'telegram/BotCore.php';
        $bot = new TelegramBot($config['bot_token']);
        $result = $bot->setWebhook('');
        
        if ($result['ok']) {
            $_SESSION['success'] = 'Webhook удален!';
        } else {
            $_SESSION['error'] = 'Ошибка: ' . $result['description'];
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
    }
    header('Location: settings_telegram.php');
    exit;
}

if ($action === 'test_message') {
    try {
        require_once 'telegram/BotCore.php';
        $bot = new TelegramBot($config['bot_token']);
        
        $testMessage = "✅ Тестовое сообщение из CRM\n";
        $testMessage .= "Время: " . date('Y-m-d H:i:s') . "\n";
        $testMessage .= "Пользователь: " . ($_SESSION['user_name'] ?? 'Unknown');
        
        $result = $bot->sendMessage($config['admin_chat_id'], $testMessage);
        
        if ($result['ok']) {
            $_SESSION['success'] = 'Тестовое сообщение отправлено!';
        } else {
            $_SESSION['error'] = 'Ошибка отправки: ' . $result['description'];
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
    }
    header('Location: settings_telegram.php');
    exit;
}

if ($action === 'broadcast_test') {
    try {
        require_once 'telegram/TelegramUtils.php';
        
        $testMessage = "📢 Тестовое сообщение для всех\n";
        $testMessage .= "Время: " . date('Y-m-d H:i:s') . "\n";
        $testMessage .= "Это тестовая рассылка от администратора";
        
        $successCount = TelegramUtils::broadcastToRole(4, $testMessage); // Мастерам
        
        $_SESSION['success'] = "Тестовая рассылка отправлена! Успешно: $successCount";
    } catch (Exception $e) {
        $_SESSION['error'] = 'Ошибка рассылки: ' . $e->getMessage();
    }
    header('Location: settings_telegram.php');
    exit;
}

// Обработка формы настроек
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newConfig = [
        'bot_token' => $_POST['bot_token'],
        'webhook_url' => $_POST['webhook_url'],
        'admin_chat_id' => $_POST['admin_chat_id'],
        'notifications' => [
            'new_orders' => isset($_POST['notify_new_orders']),
            'status_changes' => isset($_POST['notify_status_changes']),
            'comments' => isset($_POST['notify_comments']),
            'warranty' => isset($_POST['notify_warranty'])
        ]
    ];
    
    // Сохраняем конфиг
    $configContent = "<?php\n\nreturn " . var_export($newConfig, true) . ";\n";
    
    // Создаем директорию config если нет
    if (!is_dir($configDir)) {
        if (!mkdir($configDir, 0755, true)) {
            $_SESSION['error'] = 'Не удалось создать директорию config! Проверьте права на запись.';
            header('Location: settings_telegram.php');
            exit;
        }
    }
    
    // Проверяем права записи
    if (!is_writable($configDir)) {
        $_SESSION['error'] = 'Директория config не доступна для записи!';
        header('Location: settings_telegram.php');
        exit;
    }
    
    // Пытаемся сохранить файл
    if (file_put_contents($configFile, $configContent)) {
        $_SESSION['success'] = 'Настройки Telegram сохранены!';
    } else {
        // Детальная диагностика ошибки
        $error = error_get_last();
        $_SESSION['error'] = 'Ошибка сохранения настроек! ' . ($error['message'] ?? 'Неизвестная ошибка');
        
        // Попробуем альтернативный метод
        if (alternativeConfigSave($configFile, $configContent)) {
            $_SESSION['success'] = 'Настройки сохранены (альтернативным методом)!';
        }
    }
    
    header('Location: settings_telegram.php');
    exit;
}

// Добавляем функцию для альтернативного сохранения
function alternativeConfigSave($filePath, $content) {
    try {
        // Пробуем через fopen
        if ($handle = fopen($filePath, 'w')) {
            if (fwrite($handle, $content)) {
                fclose($handle);
                return true;
            }
            fclose($handle);
        }
        return false;
    } catch (Exception $e) {
        error_log("Alternative config save failed: " . $e->getMessage());
        return false;
    }
}

// Получаем информацию о боте
$botStatus = 'Не настроен';
$webhookInfo = [];
$botInfo = [];

if (!empty($config['bot_token'])) {
    try {
        require_once 'telegram/BotCore.php';
        $bot = new TelegramBot($config['bot_token']);
        
        // Информация о боте
        $botInfo = $bot->getWebhookInfo('getMe', []);
        $webhookInfo = $bot->getWebhookInfo();
        
        $botStatus = $webhookInfo['ok'] ? 'Активен' : 'Ошибка: ' . ($webhookInfo['description'] ?? 'Unknown error');
        
    } catch (Exception $e) {
        $botStatus = 'Ошибка: ' . $e->getMessage();
    }
}

renderHeader('Настройки Telegram бота');
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Настройки Telegram бота</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Токен бота *</label>
                                <input type="password" class="form-control" name="bot_token" 
                                       value="<?= safe($config['bot_token'] ?? '') ?>" required>
                                <div class="form-text">
                                    Получить у @BotFather в Telegram
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Webhook URL *</label>
                                <input type="url" class="form-control" name="webhook_url" 
                                       value="<?= safe($config['webhook_url'] ?? '') ?>" required>
                                <div class="form-text">
                                    HTTPS URL для получения updates
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Chat ID администратора</label>
                                <input type="text" class="form-control" name="admin_chat_id" 
                                       value="<?= safe($config['admin_chat_id'] ?? '') ?>">
                                <div class="form-text">
                                    Для уведомлений об ошибках
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Имя бота</label>
                                <input type="text" class="form-control" 
                                       value="<?= safe($botInfo['result']['username'] ?? 'Не доступно') ?>" 
                                       disabled>
                                <div class="form-text">
                                    @<?= safe($botInfo['result']['username'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6>Уведомления:</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notify_new_orders" 
                                       <?= isset($config['notifications']['new_orders']) && $config['notifications']['new_orders'] ? 'checked' : '' ?>>
                                <label class="form-check-label">Новые заказы</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notify_status_changes" 
                                       <?= isset($config['notifications']['status_changes']) && $config['notifications']['status_changes'] ? 'checked' : '' ?>>
                                <label class="form-check-label">Смена статусов</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notify_comments" 
                                       <?= isset($config['notifications']['comments']) && $config['notifications']['comments'] ? 'checked' : '' ?>>
                                <label class="form-check-label">Комментарии</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notify_warranty" 
                                       <?= isset($config['notifications']['warranty']) && $config['notifications']['warranty'] ? 'checked' : '' ?>>
                                <label class="form-check-label">Гарантийные</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Сохранить настройки
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Управление вебхуком -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Управление Webhook</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Текущий URL:</strong><br>
                    <code><?= safe($webhookInfo['result']['url'] ?? 'Не установлен') ?></code>
                </div>
                
                <div class="mb-3">
                    <strong>Статус:</strong><br>
                    <span class="badge bg-<?= ($webhookInfo['ok'] ?? false) ? 'success' : 'danger' ?>">
                        <?= ($webhookInfo['ok'] ?? false) ? 'Активен' : 'Не активен' ?>
                    </span>
                </div>
                
                <div class="btn-group">
                    <a href="settings_telegram.php?action=set_webhook" class="btn btn-success">
                        <i class="bi bi-plug"></i> Установить Webhook
                    </a>
                    <a href="settings_telegram.php?action=delete_webhook" class="btn btn-danger">
                        <i class="bi bi-plug-fill"></i> Удалить Webhook
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Статистика -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Статистика бота</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="card bg-primary text-white text-center p-2 mb-2">
                            <h6><?= $telegramUsers[0]['count'] ?? 0 ?></h6>
                            <small>Подключено</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card bg-info text-white text-center p-2 mb-2">
                            <h6><?= $totalUsers[0]['count'] ?? 0 ?></h6>
                            <small>Всего users</small>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <strong>Статус бота:</strong><br>
                    <span class="badge bg-<?= $botStatus === 'Активен' ? 'success' : 'danger' ?>">
                        <?= $botStatus ?>
                    </span>
                </div>
                
                <hr>
                
                <h6>Быстрые действия:</h6>
                <div class="d-grid gap-2">
                    <a href="settings_telegram.php?action=test_message" class="btn btn-outline-primary">
                        <i class="bi bi-chat-dots"></i> Тест сообщения
                    </a>
                    <a href="settings_telegram.php?action=broadcast_test" class="btn btn-outline-info">
                        <i class="bi bi-megaphone"></i> Тест рассылки
                    </a>
                    <a href="user_telegram_list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-people"></i> Список пользователей
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Информация -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Информация</h5>
            </div>
            <div class="card-body">
                <h6>Команды бота:</h6>
                <ul class="small">
                    <li><code>/start</code> - Авторизация</li>
                    <li><code>/menu</code> - Главное меню</li>
                    <li><code>/orders</code> - Мои заказы</li>
                    <li><code>/status</code> - Изменить статус</li>
                    <li><code>/comment</code> - Добавить комментарий</li>
                </ul>
                
                <h6>Требования:</h6>
                <ul class="small">
                    <li>SSL сертификат</li>
                    <li>Постоянный URL</li>
                    <li>Доступный порт 443</li>
                </ul>
                
                <div class="alert alert-info">
                    <small>
                        <strong>Текущий сервер:</strong><br>
                        <?= $_SERVER['SERVER_ADDR'] ?? 'Не определен' ?><br>
                        <strong>Домен:</strong><br>
                        <?= $_SERVER['HTTP_HOST'] ?? 'Не определен' ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>