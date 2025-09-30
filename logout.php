<?php
require_once 'config.php';
require_once 'inc/auth.php';

logoutUser();
header('Location: login.php');
exit();
?>