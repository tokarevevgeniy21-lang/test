<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('company_settings');

// Проверяем конфиг
$configFile = dirname(__FILE__) . '/config/telegram.php';
if (!file_exists($configFile)) {
    $_SESSION['error'] = 'Сначала настройте Telegram бота в настройках';
    header('Location: settings_telegram.php');
    exit;
}

$config = require $configFile;

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
    header('Location: telegram_setup.php');
    exit;
}

if ($action === 'delete_webhook') {
    try {
        require_once 'telegram/BotCore.php';
        $bot = new TelegramBot($config['bot_token']);
        $result = $bot->setWebhook(''); // Пустой URL удаляет webhook
        
        if ($result['ok']) {
            $_SESSION['success'] = 'Webhook удален!';
        } else {
            $_SESSION['error'] = 'Ошибка: ' . $result['description'];
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
    }
    header('Location: telegram_setup.php');
    exit;
}

// Получаем информацию о вебхуке
try {
    require_once 'telegram/BotCore.php';
    $bot = new TelegramBot($config['bot_token']);
    $webhookInfo = $bot->getWebhookInfo();
} catch (Exception $e) {
    $webhookInfo = ['ok' => false, 'description' => $e->getMessage()];
}

renderHeader('Настройка Telegram Webhook');
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
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
                    <span class="badge bg-<?= $webhookInfo['ok'] ? 'success' : 'danger' ?>">
                        <?= $webhookInfo['ok'] ? 'Активен' : 'Ошибка' ?>
                    </span>
                </div>
                
                <?php if (!$webhookInfo['ok'] && isset($webhookInfo['description'])): ?>
                <div class="alert alert-danger">
                    <strong>Ошибка:</strong> <?= safe($webhookInfo['description']) ?>
                </div>
                <?php endif; ?>
                
                <div class="btn-group">
                    <a href="telegram_setup.php?action=set_webhook" class="btn btn-primary">
                        <i class="bi bi-plug"></i> Установить Webhook
                    </a>
                    <a href="telegram_setup.php?action=delete_webhook" class="btn btn-danger">
                        <i class="bi bi-plug-fill"></i> Удалить Webhook
                    </a>
                    <a href="telegram_test.php" class="btn btn-outline-secondary">
                        <i class="bi bi-chat-dots"></i> Тест бота
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Информация</h5>
            </div>
            <div class="card-body">
                <h6>Для работы необходимо:</h6>
                <ul class="small">
                    <li>SSL сертификат на домене/IP</li>
                    <li>Доступный порт 443</li>
                    <li>Постоянный IP адрес</li>
                </ul>
                
                <h6>Альтернативы:</h6>
                <ul class="small">
                    <li>Использовать домен с SSL</li>
                    <li>Настроить reverse proxy</li>
                    <li>Использовать ngrok для разработки</li>
                </ul>
                
                <div class="alert alert-info">
                    <small>
                        <strong>Ваш IP:</strong> <?= $_SERVER['SERVER_ADDR'] ?? 'Не определен' ?><br>
                        <strong>Домен:</strong> <?= $_SERVER['HTTP_HOST'] ?? 'Не определен' ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>