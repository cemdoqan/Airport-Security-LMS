<?php
require_once 'includes/config.php';

// Giriş yapmış kullanıcıyı yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/index.php' : 'user/index.php'));
    exit;
}

// Giriş yapmamış kullanıcıyı login sayfasına yönlendir
header('Location: login.php');
exit;
?>