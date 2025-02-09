<?php
// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    // Kullanıcı girişi yapmamış, login sayfasına yönlendir
    header('Location: ../login.php');
    exit();
}

// Kullanıcı tipine göre erişim kontrolü
if (isset($_SESSION['user_type'])) {
    $current_path = $_SERVER['PHP_SELF'];
    $user_type = $_SESSION['user_type'];

    // Admin kontrolü
    if (strpos($current_path, '/admin/') !== false && $user_type !== 'admin') {
        // Admin değilse ana sayfaya yönlendir
        header('Location: ../user/index.php');
        exit();
    }

    // Eğitmen kontrolü
    if (strpos($current_path, '/instructor/') !== false && $user_type !== 'instructor' && $user_type !== 'admin') {
        // Eğitmen değilse ana sayfaya yönlendir
        header('Location: ../user/index.php');
        exit();
    }
}

// Session timeout kontrolü
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    // 30 dakika inaktif kaldıysa session'ı sonlandır
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}

// Son aktivite zamanını güncelle
$_SESSION['last_activity'] = time();

// Hesap durumu kontrolü
require_once __DIR__ . '/../config/db_connection.php';

$user_id = $_SESSION['user_id'];
$check_status_query = "SELECT status FROM users WHERE id = ?";
$stmt = $conn->prepare($check_status_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user['status'] !== 'active') {
    // Hesap aktif değilse oturumu sonlandır
    session_unset();
    session_destroy();
    header('Location: ../login.php?inactive=1');
    exit();
}