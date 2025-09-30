<?php
// set_theme.php
session_start();

if (isset($_GET['theme'])) {
    $theme = $_GET['theme'] === 'dark' ? 'dark' : 'light';
    $_SESSION['theme'] = $theme;
    
    // Можно также сохранить в куки для запоминания
    setcookie('theme', $theme, time() + (365 * 24 * 60 * 60), '/');
}

echo 'OK';