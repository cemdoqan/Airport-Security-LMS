</main>
    <!-- Ana İçerik Sonu -->

    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">© <?php echo date('Y'); ?> Havalimanı Güvenlik Eğitim Platformu. Tüm hakları saklıdır.</span>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>

    <!-- Custom JavaScript -->
    <?php if (isset($custom_js)): ?>
        <script src="<?php echo SITE_URL; ?>/assets/js/<?php echo $custom_js; ?>"></script>
    <?php endif; ?>

    <!-- Bildirim sistemi için JavaScript -->
    <script>
    // Okunmamış bildirimleri kontrol et
    function checkNotifications() {
        fetch('<?php echo SITE_URL; ?>/api/notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications.length > 0) {
                    updateNotificationBadge(data.notifications.length);
                }
            });
    }

    // Bildirim sayısını güncelle
    function updateNotificationBadge(count) {
        const badge = document.getElementById('notification-badge');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline' : 'none';
        }
    }

    // Bildirimleri okundu olarak işaretle
    function markNotificationsAsRead() {
        fetch('<?php echo SITE_URL; ?>/api/notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'mark_read'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(0);
            }
        });
    }

    // Her 5 dakikada bir bildirimleri kontrol et
    setInterval(checkNotifications, 300000);

    // Sayfa yüklendiğinde bildirimleri kontrol et
    document.addEventListener('DOMContentLoaded', checkNotifications);

    // Bootstrap tooltip'lerini aktifleştir
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Ajax istekleri için CSRF token ekle
    function getCsrfToken() {
        return '<?php echo generate_csrf_token(); ?>';
    }

    // Tüm form gönderimlerinde CSRF token ekle
    document.addEventListener('submit', function(e) {
        if (e.target.tagName === 'FORM') {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = getCsrfToken();
            e.target.appendChild(input);
        }
    });

    // Ajax istekleri için genel hata yakalayıcı
    window.addEventListener('unhandledrejection', function(event) {
        console.error('Unhandled promise rejection:', event.reason);
        // Kullanıcıya hata mesajı göster
        alert('Bir hata oluştu. Lütfen sayfayı yenileyip tekrar deneyin.');
    });
    </script>

    <?php if (ENVIRONMENT === 'development'): ?>
    <!-- Debug Bilgileri -->
    <div class="debug-info" style="display: none;">
        <pre>
            <?php
            $debug_info = [
                'PHP Version' => PHP_VERSION,
                'Server Software' => $_SERVER['SERVER_SOFTWARE'],
                'Memory Usage' => memory_get_usage(true),
                'Execution Time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                'Included Files' => get_included_files()
            ];
            print_r($debug_info);
            ?>
        </pre>
    </div>
    <?php endif; ?>

</body>
</html>