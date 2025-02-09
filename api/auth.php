<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $user = new User($conn);

        switch($action) {
            case 'login':
                // Kullanıcı girişi
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';

                // Giriş bilgilerini kontrol et
                if (empty($username) || empty($password)) {
                    throw new Exception('Kullanıcı adı ve şifre gereklidir.');
                }

                // Giriş yap
                if ($user->login($username, $password)) {
                    json_response([
                        'success' => true,
                        'redirect' => $_SESSION['role'] === 'admin' ? ADMIN_URL : USER_URL
                    ]);
                } else {
                    throw new Exception('Geçersiz kullanıcı adı veya şifre.');
                }
                break;

            case 'register':
                // Kullanıcı kaydı
                $required_fields = ['username', 'password', 'email', 'first_name', 'last_name'];
                foreach ($required_fields as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception($field . ' alanı gereklidir.');
                    }
                }

                // Email formatını kontrol et
                if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Geçersiz email adresi.');
                }

                // Şifre karmaşıklığını kontrol et
                if (!check_password_strength($_POST['password'])) {
                    throw new Exception('Şifre en az 8 karakter uzunluğunda olmalı ve büyük/küçük harf, rakam ve özel karakter içermelidir.');
                }

                // Kullanıcı adı ve email kontrolü
                if ($user->usernameExists($_POST['username'])) {
                    throw new Exception('Bu kullanıcı adı zaten kullanılıyor.');
                }
                if ($user->emailExists($_POST['email'])) {
                    throw new Exception('Bu email adresi zaten kullanılıyor.');
                }

                // Kullanıcı verilerini ata
                $user->username = $_POST['username'];
                $user->password = $_POST['password'];
                $user->email = $_POST['email'];
                $user->first_name = $_POST['first_name'];
                $user->last_name = $_POST['last_name'];
                $user->role = 'trainee';
                $user->status = 'active';

                // Kullanıcıyı oluştur
                if ($user->create()) {
                    // Email bildirimi gönder
                    $notification = new Notification($conn);
                    $notification->sendEmail(
                        $_POST['email'],
                        'Hoş Geldiniz - Havalimanı Güvenlik Eğitim',
                        sprintf(
                            'Sayın %s %s,<br><br>
                            Havalimanı Güvenlik Eğitim Platformuna hoş geldiniz.<br>
                            Kayıt işleminiz başarıyla tamamlanmıştır.<br><br>
                            Kullanıcı adınız: %s<br><br>
                            Giriş yapmak için: <a href="%s">tıklayın</a>',
                            $_POST['first_name'],
                            $_POST['last_name'],
                            $_POST['username'],
                            SITE_URL . '/login.php'
                        )
                    );

                    json_response([
                        'success' => true,
                        'message' => 'Kayıt başarıyla tamamlandı.'
                    ]);
                } else {
                    throw new Exception('Kayıt oluşturulurken bir hata oluştu.');
                }
                break;

            case 'reset_password':
                // Şifre sıfırlama
                $email = $_POST['email'] ?? '';
                
                if (empty($email)) {
                    throw new Exception('Email adresi gereklidir.');
                }

                if (!$user->emailExists($email)) {
                    throw new Exception('Bu email adresiyle kayıtlı kullanıcı bulunamadı.');
                }

                // Şifre sıfırlama token'ı oluştur
                $token = $user->resetPassword($email);

                if ($token) {
                    // Email gönder
                    $notification = new Notification($conn);
                    $notification->sendEmail(
                        $email,
                        'Şifre Sıfırlama - Havalimanı Güvenlik Eğitim',
                        sprintf(
                            'Şifrenizi sıfırlamak için aşağıdaki linke tıklayın:<br><br>
                            <a href="%s/reset_password.php?token=%s">Şifremi Sıfırla</a><br><br>
                            Bu link 1 saat geçerlidir.',
                            SITE_URL,
                            $token
                        )
                    );

                    json_response([
                        'success' => true,
                        'message' => 'Şifre sıfırlama linki email adresinize gönderildi.'
                    ]);
                } else {
                    throw new Exception('Şifre sıfırlama işlemi başarısız oldu.');
                }
                break;

            case 'change_password':
                // Şifre değiştirme
                if (!isset($_SESSION['user_id'])) {
                    throw new Exception('Oturum açmanız gerekiyor.');
                }

                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    throw new Exception('Tüm alanları doldurun.');
                }

                if ($new_password !== $confirm_password) {
                    throw new Exception('Yeni şifreler eşleşmiyor.');
                }

                // Şifre karmaşıklığını kontrol et
                if (!check_password_strength($new_password)) {
                    throw new Exception('Şifre en az 8 karakter uzunluğunda olmalı ve büyük/küçük harf, rakam ve özel karakter içermelidir.');
                }

                // Mevcut şifreyi doğrula
                $user_data = $user->getById($_SESSION['user_id']);
                if (!password_verify($current_password, $user_data['password'])) {
                    throw new Exception('Mevcut şifre yanlış.');
                }

                // Şifreyi güncelle
                $user->id = $_SESSION['user_id'];
                $user->password = $new_password;
                if ($user->update()) {
                    json_response([
                        'success' => true,
                        'message' => 'Şifreniz başarıyla güncellendi.'
                    ]);
                } else {
                    throw new Exception('Şifre güncellenirken bir hata oluştu.');
                }
                break;

            default:
                throw new Exception('Geçersiz işlem.');
        }
    } catch (Exception $e) {
        json_response([
            'success' => false,
            'error' => $e->getMessage()
        ], 400);
    }
} else {
    json_response([
        'success' => false,
        'error' => 'Geçersiz istek methodu.'
    ], 405);
}