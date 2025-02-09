<?php
require_once '../includes/config.php';
check_auth(['admin', 'instructor']);

$action = $_GET['action'] ?? 'list';
$page_title = '';

switch($action) {
    case 'add':
        $page_title = 'Yeni Simülasyon Ekle';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $simulation = new Simulation($conn);

                // X-ray görüntüsünü yükle
                if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('X-Ray görüntüsü gereklidir.');
                }

                $image_name = upload_file(
                    $_FILES['image'],
                    ['jpg', 'jpeg', 'png'],
                    10485760, // 10MB
                    XRAY_PATH
                );

                // Tehlikeli nesneleri JSON olarak kaydet
                $threat_objects = [];
                if (isset($_POST['threats']) && !empty($_POST['threats'])) {
                    $threats = json_decode($_POST['threats'], true);
                    foreach ($threats as $threat) {
                        $threat_objects[] = [
                            'x' => (int)$threat['x'],
                            'y' => (int)$threat['y'],
                            'type' => $threat['type'],
                            'size' => (int)$threat['size']
                        ];
                    }
                }

                // Simülasyon verilerini ata
                $simulation->title = clean_input($_POST['title']);
                $simulation->description = clean_input($_POST['description']);
                $simulation->image_path = $image_name;
                $simulation->difficulty_level = $_POST['difficulty_level'];
                $simulation->time_limit = (int)$_POST['time_limit'];
                $simulation->passing_score = (int)$_POST['passing_score'];
                $simulation->threat_objects = json_encode($threat_objects);
                $simulation->status = 'active';
                $simulation->created_by = $_SESSION['user_id'];

                if ($simulation->create()) {
                    set_flash_message('success', 'Simülasyon başarıyla oluşturuldu.');
                    header('Location: simulations.php');
                    exit;
                } else {
                    throw new Exception('Simülasyon oluşturulurken bir hata oluştu.');
                }
            } catch (Exception $e) {
                set_flash_message('danger', $e->getMessage());
            }
        }

        require_once 'header.php';
        ?>
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title"><?php echo $page_title; ?></h5>
                    <a href="simulations.php" class="btn btn-secondary btn-sm">Geri</a>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate id="simulationForm">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Simülasyon Başlığı</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Zorluk Seviyesi</label>
                                <select name="difficulty_level" class="form-control" required>
                                    <option value="beginner">Başlangıç</option>
                                    <option value="intermediate">Orta</option>
                                    <option value="advanced">İleri</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Süre Limiti (saniye)</label>
                                <input type="number" name="time_limit" class="form-control" value="300" required min="30">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Geçme Puanı</label>
                                <input type="number" name="passing_score" class="form-control" value="70" required min="0" max="100">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">X-Ray Görüntüsü</label>
                            <input type="file" name="image" class="form-control" accept="image/*" required 
                                   data-preview="imagePreview">
                        </div>

                        <div class="mb-3">
                            <img id="imagePreview" src="#" alt="Görüntü önizleme" style="max-width: 100%; display: none;">
                        </div>

                        <!-- Tehlikeli Nesneler Canvas -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Tehlikeli Nesneler</h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="bx bx-info-circle"></i>
                                    Görüntü üzerinde tehlikeli nesneleri işaretlemek için önce tür seçin, 
                                    sonra görüntü üzerine tıklayın.
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <select id="markerType" class="form-control">
                                            <option value="weapon">Silah</option>
                                            <option value="explosive">Patlayıcı</option>
                                            <option value="knife">Bıçak</option>
                                            <option value="liquid">Sıvı</option>
                                            <option value="other">Diğer</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-warning w-100" id="undoMarker">
                                            <i class="bx bx-undo"></i> Son İşareti Geri Al
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-danger w-100" id="clearMarkers">
                                            <i class="bx bx-trash"></i> Tüm İşaretleri Temizle
                                        </button>
                                    </div>
                                </div>

                                <!-- Canvas konteyner -->
                                <div class="position-relative" style="border: 1px solid #ddd; background: #f8f9fa;">
                                    <canvas id="xrayCanvas" style="cursor: crosshair;"></canvas>
                                    <div class="position-absolute top-0 end-0 m-2">
                                        <button type="button" class="btn btn-sm btn-light" id="zoomIn">
                                            <i class="bx bx-zoom-in"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light" id="zoomOut">
                                            <i class="bx bx-zoom-out"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light" id="resetZoom">
                                            <i class="bx bx-reset"></i>
                                        </button>
                                    </div>
                                </div>

                                <input type="hidden" name="threats" id="markersData">
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save"></i> Kaydet
                            </button>
                            <a href="simulations.php" class="btn btn-secondary">
                                <i class="bx bx-x"></i> İptal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        // Görüntü önizleme ve marker yönetimi
        const markers = [];
        let canvas, ctx, image;
        let scale = 1;
        let offsetX = 0, offsetY = 0;
        let isDragging = false;
        let lastX, lastY;

        // Form değişiklik kontrolü
        let formChanged = false;
        document.getElementById('simulationForm').addEventListener('change', () => formChanged = true);

        // Sayfa yönlendirmesinde uyarı
        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Görüntü yükleme
        document.querySelector('input[type="file"]').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            const file = e.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    
                    // Canvas'ı hazırla
                    image = new Image();
                    image.onload = function() {
                        initCanvas(this);
                    };
                    image.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        function initCanvas(img) {
            canvas = document.getElementById('xrayCanvas');
            ctx = canvas.getContext('2d');

            // Canvas boyutunu ayarla
            canvas.width = img.width;
            canvas.height = img.height;

            // Görüntüyü çiz
            drawImage();

            // Event listener'ları ekle
            canvas.addEventListener('mousedown', startDragging);
            canvas.addEventListener('mousemove', drag);
            canvas.addEventListener('mouseup', stopDragging);
            canvas.addEventListener('click', addMarker);

            // Zoom kontrolleri
            document.getElementById('zoomIn').addEventListener('click', () => zoom(0.1));
            document.getElementById('zoomOut').addEventListener('click', () => zoom(-0.1));
            document.getElementById('resetZoom').addEventListener('click', resetView);
        }

        function drawImage() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Görüntüyü çiz
            ctx.save();
            ctx.translate(offsetX, offsetY);
            ctx.scale(scale, scale);
            ctx.drawImage(image, 0, 0);
            ctx.restore();

            // Markerları çiz
            drawMarkers();
        }

        function addMarker(e) {
            if (isDragging) return;

            const rect = canvas.getBoundingClientRect();
            const x = (e.clientX - rect.left - offsetX) / scale;
            const y = (e.clientY - rect.top - offsetY) / scale;
            const type = document.getElementById('markerType').value;

            markers.push({ x, y, type, size: 20 });
            drawImage();
            updateMarkersInput();
            formChanged = true;
        }

        function drawMarkers() {
            markers.forEach((marker, index) => {
                const x = marker.x * scale + offsetX;
                const y = marker.y * scale + offsetY;

                ctx.beginPath();
                ctx.arc(x, y, 10 * scale, 0, 2 * Math.PI);
                ctx.strokeStyle = getMarkerColor(marker.type);
                ctx.lineWidth = 2 * scale;
                ctx.stroke();

                // Etiket
                ctx.font = `${12 * scale}px Arial`;
                ctx.fillStyle = getMarkerColor(marker.type);
                ctx.fillText(`${getMarkerLabel(marker.type)} #${index + 1}`, x + (15 * scale), y);
            });
        }

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

        function getMarkerLabel(type) {
            const labels = {
                'weapon': 'Silah',
                'explosive': 'Patlayıcı',
                'knife': 'Bıçak',
                'liquid': 'Sıvı',
                'other': 'Diğer'
            };
            return labels[type] || type;
        }

        function updateMarkersInput() {
            document.getElementById('markersData').value = JSON.stringify(markers);
        }

        // Zoom ve pan kontrolleri
        function zoom(delta) {
            const newScale = scale + delta;
            if (newScale >= 0.5 && newScale <= 3) {
                scale = newScale;
                drawImage();
            }
        }

        function resetView() {
            scale = 1;
            offsetX = 0;
            offsetY = 0;
            drawImage();
        }

        function startDragging(e) {
            isDragging = true;
            lastX = e.clientX - offsetX;
            lastY = e.clientY - offsetY;
        }

        function drag(e) {
            if (!isDragging) return;

            offsetX = e.clientX - lastX;
            offsetY = e.clientY - lastY;
            drawImage();
        }

        function stopDragging() {
            isDragging = false;
        }

        // Marker silme kontrolleri
        document.getElementById('undoMarker').addEventListener('click', () => {
            markers.pop();
            drawImage();
            updateMarkersInput();
            formChanged = true;
        });

        document.getElementById('clearMarkers').addEventListener('click', () => {
            if (confirm('Tüm işaretleri silmek istediğinizden emin misiniz?')) {
                markers.length = 0;
                drawImage();
                updateMarkersInput();
                formChanged = true;
            }
        });
        </script>
        <?php
        break;

    case 'edit':
        $simulation_id = $_GET['id'] ?? 0;
        $simulation = new Simulation($conn);
        $simulation_data = $simulation->getById($simulation_id);
        if (!$simulation_data) {
            set_flash_message('danger', 'Simülasyon bulunamadı.');
            header('Location: simulations.php');
            exit;
        }

        // Yetki kontrolü
        if ($_SESSION['role'] !== 'admin' && $simulation_data['created_by'] !== $_SESSION['user_id']) {
            set_flash_message('danger', 'Bu simülasyonu düzenleme yetkiniz yok.');
            header('Location: simulations.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $simulation->id = $simulation_id;
                $simulation->title = clean_input($_POST['title']);
                $simulation->description = clean_input($_POST['description']);
                $simulation->difficulty_level = $_POST['difficulty_level'];
                $simulation->time_limit = (int)$_POST['time_limit'];
                $simulation->passing_score = (int)$_POST['passing_score'];
                $simulation->status = $_POST['status'];

                // Yeni görüntü yüklendiyse
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $image_name = upload_file(
                        $_FILES['image'],
                        ['jpg', 'jpeg', 'png'],
                        10485760,
                        XRAY_PATH
                    );
                    $simulation->image_path = $image_name;

                    // Eski görüntüyü sil
                    if (!empty($simulation_data['image_path'])) {
                        $old_image = XRAY_PATH . '/' . $simulation_data['image_path'];
                        if (file_exists($old_image)) {
                            unlink($old_image);
                        }
                    }
                }

                // Tehlikeli nesneleri güncelle
                if (isset($_POST['threats'])) {
                    $threats = json_decode($_POST['threats'], true);
                    $threat_objects = [];
                    foreach ($threats as $threat) {
                        $threat_objects[] = [
                            'x' => (int)$threat['x'],
                            'y' => (int)$threat['y'],
                            'type' => $threat['type'],
                            'size' => (int)$threat['size']
                        ];
                    }
                    $simulation->threat_objects = json_encode($threat_objects);
                }

                if ($simulation->update()) {
                    set_flash_message('success', 'Simülasyon başarıyla güncellendi.');
                    header('Location: simulations.php');
                    exit;
                } else {
                    throw new Exception('Simülasyon güncellenirken bir hata oluştu.');
                }
            } catch (Exception $e) {
                set_flash_message('danger', $e->getMessage());
            }
        }

        $page_title = 'Simülasyon Düzenle: ' . htmlspecialchars($simulation_data['title']);
        require_once 'header.php';
        ?>
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title"><?php echo $page_title; ?></h5>
                    <a href="simulations.php" class="btn btn-secondary btn-sm">Geri</a>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate id="simulationForm">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Simülasyon Başlığı</label>
                                <input type="text" name="title" class="form-control" 
                                       value="<?php echo htmlspecialchars($simulation_data['title']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Zorluk Seviyesi</label>
                                <select name="difficulty_level" class="form-control" required>
                                    <option value="beginner" <?php echo $simulation_data['difficulty_level'] === 'beginner' ? 'selected' : ''; ?>>Başlangıç</option>
                                    <option value="intermediate" <?php echo $simulation_data['difficulty_level'] === 'intermediate' ? 'selected' : ''; ?>>Orta</option>
                                    <option value="advanced" <?php echo $simulation_data['difficulty_level'] === 'advanced' ? 'selected' : ''; ?>>İleri</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($simulation_data['description']); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Süre Limiti (saniye)</label>
                                <input type="number" name="time_limit" class="form-control" 
                                       value="<?php echo $simulation_data['time_limit']; ?>" required min="30">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Geçme Puanı</label>
                                <input type="number" name="passing_score" class="form-control" 
                                       value="<?php echo $simulation_data['passing_score']; ?>" required min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Durum</label>
                                <select name="status" class="form-control" required>
                                    <option value="active" <?php echo $simulation_data['status'] === 'active' ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="inactive" <?php echo $simulation_data['status'] === 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">X-Ray Görüntüsü</label>
                            <input type="file" name="image" class="form-control" accept="image/*" data-preview="imagePreview">
                            <small class="text-muted">Yeni görüntü yüklemezseniz mevcut görüntü kullanılmaya devam edecektir.</small>
                        </div>

                        <div class="mb-3">
                            <img id="imagePreview" 
                                 src="<?php echo SITE_URL . '/assets/images/xray/' . $simulation_data['image_path']; ?>" 
                                 alt="Görüntü önizleme" style="max-width: 100%;">
                        </div>

                        <!-- Tehlikeli Nesneler Canvas -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Tehlikeli Nesneler</h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="bx bx-info-circle"></i>
                                    Görüntü üzerinde tehlikeli nesneleri işaretlemek için önce tür seçin, 
                                    sonra görüntü üzerine tıklayın.
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <select id="markerType" class="form-control">
                                            <option value="weapon">Silah</option>
                                            <option value="explosive">Patlayıcı</option>
                                            <option value="knife">Bıçak</option>
                                            <option value="liquid">Sıvı</option>
                                            <option value="other">Diğer</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-warning w-100" id="undoMarker">
                                            <i class="bx bx-undo"></i> Son İşareti Geri Al
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-danger w-100" id="clearMarkers">
                                            <i class="bx bx-trash"></i> Tüm İşaretleri Temizle
                                        </button>
                                    </div>
                                </div>

                                <!-- Canvas konteyner -->
                                <div class="position-relative" style="border: 1px solid #ddd; background: #f8f9fa;">
                                    <canvas id="xrayCanvas" style="cursor: crosshair;"></canvas>
                                    <div class="position-absolute top-0 end-0 m-2">
                                        <button type="button" class="btn btn-sm btn-light" id="zoomIn">
                                            <i class="bx bx-zoom-in"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light" id="zoomOut">
                                            <i class="bx bx-zoom-out"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light" id="resetZoom">
                                            <i class="bx bx-reset"></i>
                                        </button>
                                    </div>
                                </div>

                                <input type="hidden" name="threats" id="markersData" 
                                       value='<?php echo htmlspecialchars($simulation_data['threat_objects']); ?>'>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save"></i> Kaydet
                            </button>
                            <a href="simulations.php" class="btn btn-secondary">
                                <i class="bx bx-x"></i> İptal
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- İstatistikler -->
            <?php if ($simulation_data['total_attempts'] > 0): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Simülasyon İstatistikleri</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Toplam Deneme</h6>
                                    <h4><?php echo $simulation_data['total_attempts']; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Ortalama Puan</h6>
                                    <h4><?php echo number_format($simulation_data['avg_score'], 1); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Başarı Oranı</h6>
                                    <h4><?php 
                                        $success_rate = ($simulation_data['passed_count'] / $simulation_data['total_attempts']) * 100;
                                        echo number_format($success_rate, 1) . '%'; 
                                    ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Ort. Süre</h6>
                                    <h4><?php echo gmdate('i:s', $simulation_data['avg_time']); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grafik -->
                    <div class="mt-4">
                        <canvas id="statsChart"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <script>
        // Form değişiklik kontrolü
        let formChanged = false;
        document.getElementById('simulationForm').addEventListener('change', () => formChanged = true);

        // Sayfa yönlendirmesinde uyarı
        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Marker yönetimi için değişkenler
        let markers = <?php echo $simulation_data['threat_objects'] ?? '[]'; ?>;
        let canvas, ctx, image;
        let scale = 1;
        let offsetX = 0, offsetY = 0;
        let isDragging = false;
        let lastX, lastY;

        // Görüntü yükleme
        const preview = document.getElementById('imagePreview');
        image = new Image();
        image.onload = function() {
            initCanvas(this);
        };
        image.src = preview.src;

        // Dosya seçildiğinde
        document.querySelector('input[type="file"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    image = new Image();
                    image.onload = function() {
                        initCanvas(this);
                    };
                    image.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        function initCanvas(img) {
            canvas = document.getElementById('xrayCanvas');
            ctx = canvas.getContext('2d');

            // Canvas boyutunu ayarla
            canvas.width = img.width;
            canvas.height = img.height;

            // Event listener'ları ekle
            canvas.addEventListener('mousedown', startDragging);
            canvas.addEventListener('mousemove', drag);
            canvas.addEventListener('mouseup', stopDragging);
            canvas.addEventListener('click', addMarker);

           // İlk çizim
            drawImage();
        }

        // Diğer fonksiyonlar (önceki edit kısmında tanımlanan fonksiyonlarla aynı)
        function drawImage() { /* ... */ }
        function addMarker(e) { /* ... */ }
        function drawMarkers() { /* ... */ }
        function getMarkerColor(type) { /* ... */ }
        function getMarkerLabel(type) { /* ... */ }
        function updateMarkersInput() { /* ... */ }
        function zoom(delta) { /* ... */ }
        function resetView() { /* ... */ }
        function startDragging(e) { /* ... */ }
        function drag(e) { /* ... */ }
        function stopDragging() { /* ... */ }

        // Marker silme kontrolleri
        document.getElementById('undoMarker').addEventListener('click', () => { /* ... */ });
        document.getElementById('clearMarkers').addEventListener('click', () => { /* ... */ });

        <?php if ($simulation_data['total_attempts'] > 0): ?>
        // İstatistik grafiği
        const ctx = document.getElementById('statsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($simulation_data['stats_labels']); ?>,
                datasets: [{
                    label: 'Ortalama Puan',
                    data: <?php echo json_encode($simulation_data['stats_scores']); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
        <?php endif; ?>
        </script>
        <?php
        break;

    default:
        // Liste görünümü
        require_once 'simulations.list.php';
        break;
}

require_once 'footer.php';
?>