<?php
require_once 'includes/config.php';

// Zaten giriş yapmışsa yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/index.php' : 'user/index.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - Havalimanı Güvenlik Eğitim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .register-container {
            max-width: 500px;
            margin: 50px auto;
        }
        .error-message {
            display: none;
            margin-top: 10px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="register-container">
            <div class="card shadow">
                <div class="card-header text-center bg-primary text-white">
                    <h4 class="mb-0">Kayıt Ol</h4>
                </div>
                <div class="card-body">
                    <form id="registerForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ad</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Soyad</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kullanıcı Adı</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Şifre</label>
                            <input type="password" name="password" class="form-control" minlength="6" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Şifre (Tekrar)</label>
                            <input type="password" name="password_confirm" class="form-control" required>
                        </div>

                        <div class="alert alert-danger error-message" id="errorMessage"></div>
                        
                        <button type="submit" class="btn btn-primary w-100">Kayıt Ol</button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="login.php">Giriş Yap</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const password = this.querySelector('input[name="password"]').value;
        const passwordConfirm = this.querySelector('input[name="password_confirm"]').value;
        
        if (password !== passwordConfirm) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = 'Şifreler eşleşmiyor.';
            errorDiv.style.display = 'block';
            return;
        }
        
        const formData = new FormData(this);
        formData.append('action', 'register');
        
        fetch('api/auth.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'login.php?registered=1';
            } else {
                const errorDiv = document.getElementById('errorMessage');
                errorDiv.textContent = data.error;
                errorDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = 'Bir hata oluştu. Lütfen tekrar deneyin.';
            errorDiv.style.display = 'block';
        });
    });
    </script>
</body>
</html>