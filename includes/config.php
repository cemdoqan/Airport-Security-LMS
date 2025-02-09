<?php
// Veritabanı bağlantı bilgileri
define('DB_HOST', 'localhost');
define('DB_NAME', 'xray_db');  // Yerel veritabanı adı
define('DB_USER', 'root');     // Yerel MySQL kullanıcı adı
define('DB_PASS', 'root');         // Yerel MySQL şifresi (XAMPP/MAMP varsayılan boş)

// URL tanımlamaları
define('SITE_URL', 'http://localhost:8888/xray/'); // Yerel URL
define('ADMIN_URL', SITE_URL . '/admin');
define('USER_URL', SITE_URL . '/user');

// Dosya yolu tanımlamaları
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/');
define('XRAY_PATH', BASE_PATH . '/assets/images/xray/');

// Email ayarları (şimdilik geliştirme için)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'test@gmail.com');
define('SMTP_PASS', 'test123');
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'noreply@localhost');
define('SMTP_FROM_NAME', 'Havalimanı Güvenlik Eğitim');

// Admin bilgileri
define('ADMIN_EMAIL', 'admin@localhost');

// Log ayarları
define('LOG_PATH', BASE_PATH . '/logs');
define('LOG_LEVEL', 'DEBUG'); // Development için DEBUG moduna aldık

// Uygulama ayarları
define('APP_NAME', 'Havalimanı Güvenlik Eğitim Platformu');
define('APP_VERSION', '1.0.0');
define('ENVIRONMENT', 'development'); // Development modu açık

// Session ayarları
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Local'de SSL olmadığı için 0

// Session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hata raporlama (Development için full açık)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Zaman dilimi ayarı
date_default_timezone_set('Europe/Istanbul');

// Veritabanı bağlantısı
require_once __DIR__ . '/database.php';
// Yardımcı fonksiyonlar
require_once __DIR__ . '/functions.php';
// Logger sınıfını yükle
require_once BASE_PATH . '/classes/Logger.php';

// Sınıfları otomatik yükle
spl_autoload_register(function ($class_name) {
    $file = BASE_PATH . '/classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Global değişkenler
$db = new Database();
$conn = $db->getConnection();

// Logger başlat
$logger = new Logger($conn);

// Hata yakalayıcıyı ayarla
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logger) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    $logger->error($errstr, [
        'errno' => $errno,
        'file' => $errfile,
        'line' => $errline
    ]);
    return true;
});

// Exception yakalayıcıyı ayarla
set_exception_handler(function($exception) use ($logger) {
    $logger->error($exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
});