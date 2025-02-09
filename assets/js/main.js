// Ana JavaScript dosyası
document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap Tooltip'lerini aktifleştir
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Bootstrap Popover'larını aktifleştir
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // AJAX istekleri için CSRF token'ı ekle
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    }

    // AJAX istekleri için genel ayarlar
    function setupAjax() {
        const token = getCsrfToken();
        if (token) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': token
                }
            });
        }
    }

    // Okunmamış bildirimleri kontrol et
    function checkNotifications() {
        fetch('/api/notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications.length > 0) {
                    updateNotificationBadge(data.notifications.length);
                    updateNotificationDropdown(data.notifications);
                }
            })
            .catch(error => console.error('Bildirim hatası:', error));
    }

    // Bildirim sayısını güncelle
    function updateNotificationBadge(count) {
        const badge = document.getElementById('notification-badge');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'block' : 'none';
        }
    }

    // Bildirim listesini güncelle
    function updateNotificationDropdown(notifications) {
        const container = document.getElementById('notification-list');
        if (container) {
            container.innerHTML = notifications.map(notification => `
                <a class="dropdown-item" href="${notification.link || '#'}">
                    <div class="d-flex align-items-center">
                        <div class="notification-icon ${notification.type}">
                            <i class="bx ${getNotificationIcon(notification.type)}"></i>
                        </div>
                        <div class="ms-3">
                            <p class="mb-1">${notification.message}</p>
                            <small class="text-muted">${formatDate(notification.created_at)}</small>
                        </div>
                    </div>
                </a>
            `).join('');
        }
    }

    // Bildirim ikonu seç
    function getNotificationIcon(type) {
        const icons = {
            'success': 'bx-check-circle',
            'warning': 'bx-error',
            'info': 'bx-info-circle',
            'danger': 'bx-x-circle'
        };
        return icons[type] || 'bx-bell';
    }

    // Tarih formatla
    function formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) { // 1 dakikadan az
            return 'Az önce';
        } else if (diff < 3600000) { // 1 saatten az
            return Math.floor(diff / 60000) + ' dakika önce';
        } else if (diff < 86400000) { // 1 günden az
            return Math.floor(diff / 3600000) + ' saat önce';
        } else {
            return date.toLocaleDateString('tr-TR');
        }
    }

    // Form validasyonu
    function validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                input.classList.add('is-invalid');
            } else {
                input.classList.remove('is-invalid');
            }
        });

        return isValid;
    }

    // Dosya yükleme önizleme
    function setupFilePreview() {
        const fileInputs = document.querySelectorAll('input[type="file"][data-preview]');
        fileInputs.forEach(input => {
            input.addEventListener('change', function(e) {
                const preview = document.getElementById(this.dataset.preview);
                if (preview && this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
    }

    // Dinamik form alanları
    function setupDynamicFields() {
        const addButtons = document.querySelectorAll('[data-add-field]');
        addButtons.forEach(button => {
            button.addEventListener('click', function() {
                const template = document.getElementById(this.dataset.template);
                const container = document.getElementById(this.dataset.container);
                if (template && container) {
                    const newField = template.content.cloneNode(true);
                    container.appendChild(newField);
                }
            });
        });
    }

    // Sayfa yüklendiğinde çalışacak fonksiyonlar
    function init() {
        setupAjax();
        setupFilePreview();
        setupDynamicFields();
        checkNotifications();

        // Her 5 dakikada bir bildirimleri kontrol et
        setInterval(checkNotifications, 300000);

        // Form validasyonlarını etkinleştir
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!validateForm(this)) {
                    e.preventDefault();
                }
            });
        });
    }

    // Sayfa yüklendiğinde init fonksiyonunu çalıştır
    init();
});