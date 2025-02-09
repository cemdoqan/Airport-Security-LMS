<?php
// Veritabanı yapılandırma bilgileri
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'xray_db');

// MySQLi bağlantısı
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Bağlantı kontrolü
if ($conn->connect_error) {
    error_log("Veritabanı bağlantı hatası: " . $conn->connect_error);
    die("Veritabanına bağlanılamadı. Lütfen daha sonra tekrar deneyin.");
}

// Karakter seti ayarı
$conn->set_charset("utf8mb4");

// Zaman dilimi ayarı
date_default_timezone_set('Europe/Istanbul');

// Genel hata işleme
function handleDatabaseError($error) {
    error_log("Veritabanı hatası: " . $error);
    return "İşlem sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
}

// Güvenli sorgu fonksiyonu
function secureQuery($conn, $query, $params = [], $types = "") {
    try {
        $stmt = $conn->prepare($query);
        
        if ($stmt === false) {
            throw new Exception($conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        
        return $stmt;
    } catch (Exception $e) {
        return handleDatabaseError($e->getMessage());
    }
}

// Bağlantıyı otomatik kapat
register_shutdown_function(function() use ($conn) {
    if ($conn) {
        $conn->close();
    }
});