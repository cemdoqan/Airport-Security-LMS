document.addEventListener('DOMContentLoaded', function() {
    // Form elementlerini seç
    const passwordForm = document.getElementById('passwordForm');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const strengthIndicator = document.querySelector('.password-strength');
    const profileForm = document.querySelector('.profile-form');
    const twoFactorToggle = document.getElementById('twoFactorAuth');
    const fileInput = document.querySelector('.avatar-upload input[type="file"]');
    const avatarPreview = document.querySelector('.profile-avatar img');
    const notificationToggles = document.querySelectorAll('.notification-toggle');
    let formChanged = false;

    // Şifre gücü kontrolü
    if (newPassword) {
        newPassword.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let message = '';

            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            switch (strength) {
                case 0:
                case 1:
                    message = 'Zayıf';
                    strengthIndicator.style.backgroundColor = '#ff4444';
                    break;
                case 2:
                case 3:
                    message = 'Orta';
                    strengthIndicator.style.backgroundColor = '#ffbb33';
                    break;
                case 4:
                case 5:
                    message = 'Güçlü';
                    strengthIndicator.style.backgroundColor = '#00C851';
                    break;
            }

            strengthIndicator.style.width = (strength * 20) + '%';
            strengthIndicator.textContent = message;
        });
    }

    // Şifre formu doğrulama
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            if (newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Yeni şifreler eşleşmiyor!');
                return false;
            }

            if (newPassword.value.length < 8) {
                e.preventDefault();
                alert('Şifre en az 8 karakter olmalıdır!');
                return false;
            }
        });
    }

    // İki faktörlü doğrulama
    if (twoFactorToggle) {
        twoFactorToggle.addEventListener('change', function() {
            fetch('../api/update_2fa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled: this.checked })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    this.checked = !this.checked;
                    alert(data.message || 'İşlem başarısız oldu.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.checked = !this.checked;
                alert('Bir hata oluştu. Lütfen tekrar deneyin.');
            });
        });
    }

    // Profil fotoğrafı yükleme
    if (fileInput && avatarPreview) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (!file.type.startsWith('image/')) {
                    alert('Lütfen geçerli bir resim dosyası seçin.');
                    return;
                }

                if (file.size > 2 * 1024 * 1024) {
                    alert('Dosya boyutu 2MB\'dan küçük olmalıdır.');
                    return;
                }

                const formData = new FormData();
                formData.append('avatar', file);

                fetch('../api/upload_avatar.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        avatarPreview.src = data.avatar_url;
                    } else {
                        alert(data.message || 'Fotoğraf yüklenemedi.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Fotoğraf yüklenirken bir hata oluştu.');
                });
            }
        });
    }

    // Bildirim ayarları
    if (notificationToggles) {
        notificationToggles.forEach(toggle => {
            toggle.addEventListener('change', function() {
                const type = this.dataset.type;
                fetch('../api/update_notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        type: type,
                        enabled: this.checked
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        this.checked = !this.checked;
                        alert(data.message || 'Ayar güncellenemedi.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.checked = !this.checked;
                    alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                });
            });
        });
    }

    // Form değişiklik kontrolü
    if (profileForm) {
        profileForm.addEventListener('input', function() {
            formChanged = true;
        });
    }

    // Sayfadan ayrılma uyarısı
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Başarı mesajlarını otomatik gizle
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 3000);
    });
});