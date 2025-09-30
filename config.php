<?php
// config.php
session_start();

// Базовые настройки приложения
define('APP_NAME', 'ServiceCRM');
define('DB_HOST', 'localhost');
define('DB_NAME', 'service_center_crm');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Настройки безопасности
define('PEPPER', 'your-secret-pepper-string'); // Для хеширования паролей

// Пути для загрузки файлов
define('UPLOAD_PATH', dirname(__FILE__) . '/uploads/');
define('AVATAR_PATH', UPLOAD_PATH . 'avatars/');

// Режим разработки (в продакшене установить в false)
define('DEBUG_MODE', true);

// Создаем директории для загрузок, если их нет
if (!file_exists(AVATAR_PATH)) {
    mkdir(AVATAR_PATH, 0777, true);
}

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
?>