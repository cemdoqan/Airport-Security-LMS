<?php
// Güvenlik fonksiyonları
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// CSRF token oluştur
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF token kontrolü
function check_csrf_token() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token doğrulaması başarısız.');
    }
}

// Yetki kontrolü
function check_auth($allowed_roles = ['admin']) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
    
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        header('Location: ' . SITE_URL . '/403.php');
        exit;
    }
}

// Dosya yükleme
function upload_file($file, $allowed_types = ['jpg', 'jpeg', 'png'], $max_size = 5242880) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        throw new Exception('Dosya yüklenemedi.');
    }

    $file_tmp = $file['tmp_name'];
    $file_name = $file['name'];
    $file_size = $file['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Kontroller
    if (!in_array($file_ext, $allowed_types)) {
        throw new Exception('Desteklenmeyen dosya türü.');
    }

    if ($file_size > $max_size) {
        throw new Exception('Dosya boyutu çok büyük.');
    }

    // Benzersiz dosya adı oluştur
    $new_file_name = uniqid() . '.' . $file_ext;
    $upload_path = UPLOAD_PATH . $new_file_name;

    if (!move_uploaded_file($file_tmp, $upload_path)) {
        throw new Exception('Dosya yüklenirken bir hata oluştu.');
    }

    return $new_file_name;
}

// Flash mesaj oluştur
function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Flash mesaj göster
function show_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return sprintf(
            '<div class="alert alert-%s alert-dismissible fade show" role="alert">
                %s
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>',
            $flash['type'],
            $flash['message']
        );
    }
    return '';
}

// Sayfalama
function paginate($total, $per_page, $current_page) {
    $total_pages = ceil($total / $per_page);
    $current_page = max(1, min($current_page, $total_pages));
    
    return [
        'total' => $total,
        'per_page' => $per_page,
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'offset' => ($current_page - 1) * $per_page
    ];
}

// Sayfalama HTML oluştur
function create_pagination_html($pagination, $url) {
    if ($pagination['total_pages'] <= 1) return '';

    $html = '<nav aria-label="Sayfalama"><ul class="pagination justify-content-center">';
    
    // Önceki sayfa linki
    if ($pagination['current_page'] > 1) {
        $html .= sprintf(
            '<li class="page-item"><a class="page-link" href="%s?page=%d">Önceki</a></li>',
            $url,
            $pagination['current_page'] - 1
        );
    }

    // Sayfa numaraları
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        $html .= sprintf(
            '<li class="page-item %s"><a class="page-link" href="%s?page=%d">%d</a></li>',
            $i === $pagination['current_page'] ? 'active' : '',
            $url,
            $i,
            $i
        );
    }

    // Sonraki sayfa linki
    if ($pagination['current_page'] < $pagination['total_pages']) {
        $html .= sprintf(
            '<li class="page-item"><a class="page-link" href="%s?page=%d">Sonraki</a></li>',
            $url,
            $pagination['current_page'] + 1
        );
    }

    $html .= '</ul></nav>';
    return $html;
}

// Tarih formatla
function format_date($date, $format = 'd.m.Y H:i') {
    return date($format, strtotime($date));
}

// Geçen süreyi hesapla
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = [
        'y' => 'yıl',
        'm' => 'ay',
        'w' => 'hafta',
        'd' => 'gün',
        'h' => 'saat',
        'i' => 'dakika',
        's' => 'saniye',
    ];

    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' önce' : 'az önce';
}

// Dosya boyutunu formatla
function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Para birimini formatla
function format_money($amount, $currency = '₺') {
    return $currency . number_format($amount, 2, ',', '.');
}

// Şifre karmaşıklığını kontrol et
function check_password_strength($password) {
    $uppercase = preg_match('@[A-Z]@', $password);
    $lowercase = preg_match('@[a-z]@', $password);
    $number    = preg_match('@[0-9]@', $password);
    $special   = preg_match('@[^\w]@', $password);

    if (!$uppercase || !$lowercase || !$number || !$special || strlen($password) < 8) {
        return false;
    }
    return true;
}

// Active menü class'ı
function is_menu_active($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page == $page ? 'active' : '';
}

// JSON response
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Yetki kontrolü
function has_permission($permission) {
    if (!isset($_SESSION['permissions'])) {
        return false;
    }
    return in_array($permission, $_SESSION['permissions']);
}

// Kullanıcı adını getir
function get_user_name($user_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT CONCAT(first_name, ' ', last_name) as full_name 
        FROM users WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 'Bilinmeyen Kullanıcı';
}

// Profil fotoğrafı URL'i
function get_profile_image($user_id) {
    $default = SITE_URL . '/assets/images/default-avatar.png';
    $custom = UPLOAD_PATH . 'profile_' . $user_id . '.jpg';
    
    return file_exists($custom) ? $custom : $default;
}