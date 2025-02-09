<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Kullanıcı bilgilerini getir
$user_query = "SELECT u.*, d.name as department_name 
               FROM users u 
               LEFT JOIN departments d ON u.department_id = d.id 
               WHERE u.id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Profil güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $notification_preferences = isset($_POST['notifications']) ? 1 : 0;

    $update_query = "UPDATE users 
                    SET name = ?, email = ?, phone = ?, notification_preferences = ? 
                    WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sssii", $name, $email, $phone, $notification_preferences, $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Profil başarıyla güncellendi.";
        // Güncel bilgileri al
        $stmt = $conn->prepare($user_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    } else {
        $error_message = "Profil güncellenirken bir hata oluştu.";
    }
}

// Şifre değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Mevcut şifreyi kontrol et
    $check_query = "SELECT password FROM users WHERE id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (password_verify($current_password, $result['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Şifre başarıyla güncellendi.";
            } else {
                $error_message = "Şifre güncellenirken bir hata oluştu.";
            }
        } else {
            $error_message = "Yeni şifreler eşleşmiyor.";
        }
    } else {
        $error_message = "Mevcut şifre yanlış.";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - <?php echo htmlspecialchars($user['name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/profile.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="profile-container">
        <div class="profile-header">
            <div class="profile-avatar">
                <img src="<?php echo $user['avatar_url'] ?? '../assets/images/default-avatar.png'; ?>" 
                     alt="Profil Fotoğrafı">
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['name']); ?></h1>
                <p class="department"><?php echo htmlspecialchars($user['department_name']); ?></p>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="profile-sections">
            <!-- Profil Düzenleme -->
            <section class="profile-section">
                <h2>Profil Bilgileri</h2>
                <form action="" method="POST" class="profile-form">
                    <div class="form-group">
                        <label for="name">Ad Soyad</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">E-posta</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Telefon</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>

                    <div class="form-group checkbox">
                        <input type="checkbox" id="notifications" name="notifications" 
                               <?php echo $user['notification_preferences'] ? 'checked' : ''; ?>>
                        <label for="notifications">E-posta bildirimleri almak istiyorum</label>
                    </div>

                    <button type="submit" name="update_profile" class="btn-primary">Güncelle</button>
                </form>
            </section>

            <!-- Şifre Değiştirme -->
            <section class="profile-section">
                <h2>Şifre Değiştir</h2>
                <form action="" method="POST" class="password-form" id="passwordForm">
                    <div class="form-group">
                        <label for="current_password">Mevcut Şifre</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">Yeni Şifre</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <div class="password-strength"></div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Yeni Şifre (Tekrar)</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" name="change_password" class="btn-primary">Şifreyi Değiştir</button>
                </form>
            </section>

            <!-- Güvenlik Ayarları -->
            <section class="profile-section">
                <h2>Güvenlik</h2>
                <div class="security-settings">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>İki Faktörlü Doğrulama</h3>
                            <p>Hesabınıza ekstra güvenlik katmanı ekleyin</p>
                        </div>
                        <div class="setting-action">
                            <label class="switch">
                                <input type="checkbox" id="twoFactorAuth" 
                                       <?php echo $user['two_factor_enabled'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h3>Hesap Aktivitesi</h3>
                            <p>Son oturum açma bilgilerinizi görüntüleyin</p>
                        </div>
                        <div class="setting-action">
                            <button class="btn-secondary" id="viewActivity">Görüntüle</button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script src="../assets/js/profile.js"></script>
</body>
</html>