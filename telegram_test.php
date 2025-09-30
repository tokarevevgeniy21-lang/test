<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('company_settings');

$configFile = dirname(__FILE__) . '/config/telegram.php';
if (!file_exists($configFile)) {
    $_SESSION['error'] = 'Сначала настройте Telegram бота';
    header('Location: settings_telegram.php');
    exit;
}

$config = require $configFile;

// Тестируем отправку сообщения
$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once 'telegram/BotCore.php';
        $bot = new TelegramBot($config['bot_token']);
        
        $message = "✅ Тестовое сообщение из CRM\n";
        $message .= "Время: " . date('Y-m-d H:i:s') . "\n";
        $message .= "Пользователь: " . ($_SESSION['user_name'] ?? 'Unknown');
        
        $testResult = $bot->sendMessage($config['admin_chat_id'], $message);
        
        $_SESSION['success'] = 'Тестовое сообщение отправлено!';
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Ошибка отправки: ' . $e->getMessage();
    }
}

renderHeader('Тест Telegram бота');
?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Тестирование бота</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Chat ID для теста</label>
                        <input type="text" class="form-control" name="chat_id" 
                               value="<?= safe($config['admin_chat_id'] ?? '') ?>">
                        <div class="form-text">
                            Оставьте пустым для отправки администратору
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Отправить тестовое сообщение
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Результат теста</h5>
            </div>
            <div class="card-body">
                <?php if ($testResult): ?>
                <pre><?= print_r($testResult, true) ?></pre>
                <?php else: ?>
                <p class="text-muted">Отправьте тестовое сообщение для проверки</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>