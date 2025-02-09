// Admin Panel JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const contentWrapper = document.querySelector('.content-wrapper');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            contentWrapper.classList.toggle('expanded');
        });
    }

    // Datatables başlat
    const tables = document.querySelectorAll('.datatable');
    tables.forEach(table => {
        new DataTable(table, {
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/tr.json'
            },
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']]
        });
    });

    // Chart.js grafikleri
    function initCharts() {
        // Kullanıcı istatistikleri grafiği
        const userStatsCtx = document.getElementById('userStatsChart');
        if (userStatsCtx) {
            fetch('/api/admin/stats.php?type=users')
                .then(response => response.json())
                .then(data => {
                    new Chart(userStatsCtx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Aktif Kullanıcılar',
                                data: data.active_users,
                                borderColor: '#3498db',
                                fill: false
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                });
        }

        // Simülasyon istatistikleri grafiği
        const simStatsCtx = document.getElementById('simulationStatsChart');
        if (simStatsCtx) {
            fetch('/api/admin/stats.php?type=simulations')
                .then(response => response.json())
                .then(data => {
                    new Chart(simStatsCtx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Başarılı Denemeler',
                                data: data.success_rate,
                                backgroundColor: '#2ecc71'
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 100,
                                    ticks: {
                                        callback: function(value) {
                                            return value + '%';
                                        }
                                    }
                                }
                            }
                        }
                    });
                });
        }
    }

    // CRUD işlemleri için fonksiyonlar
    const adminCrud = {
        delete: function(url, id) {
            if (confirm('Bu öğeyi silmek istediğinizden emin misiniz?')) {
                fetch(`${url}?id=${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        },

        changeStatus: function(url, id, status) {
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ id, status })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error);
                }
            })
            .catch(error => console.error('Error:', error));
        }
    };

    // X-Ray görüntü yönetimi
    function initXrayImageManager() {
        const canvas = document.getElementById('xrayCanvas');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const markers = [];
        let isDrawing = false;

        // Görüntü yükleme
        const image = new Image();
        image.onload = function() {
            canvas.width = image.width;
            canvas.height = image.height;
            ctx.drawImage(image, 0, 0);
            drawMarkers();
        };
        image.src = document.getElementById('xraySource').value;

        // Marker ekleme
        canvas.addEventListener('click', function(e) {
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const type = document.getElementById('markerType').value;

            markers.push({ x, y, type });
            drawMarkers();
            updateMarkersInput();
        });

        // Markerları çiz
        function drawMarkers() {
            ctx.drawImage(image, 0, 0);
            markers.forEach(marker => {
                ctx.beginPath();
                ctx.arc(marker.x, marker.y, 10, 0, 2 * Math.PI);
                ctx.strokeStyle = getMarkerColor(marker.type);
                ctx.stroke();
            });
        }

        // Marker rengini belirle
        function getMarkerColor(type) {
            const colors = {
                'weapon': '#ff0000',
                'explosive': '#ff6600',
                'knife': '#ff3300',
                'liquid': '#0066ff',
                'other': '#666666'
            };
            return colors[type] || '#000000';
        }

        // Hidden input'u güncelle
        function updateMarkersInput() {
            document.getElementById('markersData').value = JSON.stringify(markers);
        }

        // Son markerı sil
        document.getElementById('undoMarker')?.addEventListener('click', function() {
            markers.pop();
            drawMarkers();
            updateMarkersInput();
        });
    }

    // Dosya yükleme önizleme
    function initFilePreview() {
        const fileInput = document.querySelector('input[type="file"][data-preview]');
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const preview = document.getElementById(this.dataset.preview);
                if (preview && this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    }

    // Tüm başlangıç fonksiyonlarını çalıştır
    function init() {
        initCharts();
        initXrayImageManager();
        initFilePreview();

        // Global AJAX hata yakalayıcı
        window.addEventListener('unhandledrejection', function(event) {
            console.error('Unhandled promise rejection:', event.reason);
            alert('Bir hata oluştu. Lütfen sayfayı yenileyip tekrar deneyin.');
        });
    }

    init();

    // Global olarak adminCrud'u erişilebilir yap
    window.adminCrud = adminCrud;
});