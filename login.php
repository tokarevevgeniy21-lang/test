<?php
require_once 'config.php';
require_once 'db.php';
require_once 'inc/utils.php'; // Добавляем подключение утилит

// Функция для рендеринга заголовка страницы логина
function renderLoginHeader($title = '') {
    require_once 'config.php';
    $pageTitle = $title ? ($title . ' - ' . APP_NAME) : APP_NAME;
    $safe = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
    ?>
    <!DOCTYPE html>
    <html lang="ru-RU">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $safe($pageTitle) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            body { background-color: #f8f9fa; }
            .login-container { max-width: 400px; margin: 100px auto; }
            .card { border: none; border-radius: 10px; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        </style>
    </head>
    <body>
    <?php
}

// Проверяем, авторизован ли пользователь
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

function loginUser($username, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role_id'] = $user['role_id'];
        return true;
    }
    
    return false;
}

// Если пользователь уже авторизован, перенаправляем на главную
if (isAuthenticated()) {
    header('Location: index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (loginUser($username, $password)) {
        $redirectUrl = $_SESSION['redirect_url'] ?? 'index.php';
        unset($_SESSION['redirect_url']);
        header('Location: ' . $redirectUrl);
        exit();
    } else {
        $error = 'Неверный логин или пароль';
    }
}

renderLoginHeader('Вход в систему');
?>
<div class="login-container">
    <div class="card p-4">
        <h2 class="text-center mb-4">Вход в <?= APP_NAME ?></h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= safe($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Логин</label>
                <input type="text" class="form-control" id="username" name="username" required autofocus>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Пароль</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Войти</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>