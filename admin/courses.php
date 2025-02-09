<?php
require_once '../includes/config.php';
check_auth(['admin', 'instructor']);

$action = $_GET['action'] ?? 'list';

switch($action) {
    case 'add':
        $page_title = 'Yeni Eğitim Ekle';
        require_once 'header.php';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $course = new Course($conn);
                
                // Gerekli alanları kontrol et
                $required_fields = ['title', 'description', 'category', 'duration'];
                foreach ($required_fields as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception($field . ' alanı gereklidir.');
                    }
                }

                // Kurs verilerini ata
                $course->title = clean_input($_POST['title']);
                $course->description = clean_input($_POST['description']);
                $course->content = $_POST['content'];
                $course->duration = (int)$_POST['duration'];
                $course->category = $_POST['category'];
                $course->status = $_POST['status'];
                $course->created_by = $_SESSION['user_id'];

                if ($course->create()) {
                    $course_id = $conn->lastInsertId();

                    // Modülleri ekle
                    if (!empty($_POST['modules'])) {
                        foreach ($_POST['modules'] as $order => $module) {
                            $course->addModule([
                                'title' => clean_input($module['title']),
                                'content' => $module['content'],
                                'type' => $module['type'],
                                'order_number' => $order + 1
                            ]);
                        }
                    }

                    set_flash_message('success', 'Eğitim başarıyla oluşturuldu.');
                    header('Location: courses.php');
                    exit;
                } else {
                    throw new Exception('Eğitim oluşturulurken bir hata oluştu.');
                }
            } catch (Exception $e) {
                set_flash_message('danger', $e->getMessage());
            }
        }
        ?>
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title"><?php echo $page_title; ?></h5>
                    <a href="courses.php" class="btn btn-secondary btn-sm">Geri</a>
                </div>
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate>
                        <!-- Temel Bilgiler -->
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Eğitim Başlığı</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Kategori</label>
                                <select name="category" class="form-control" required>
                                    <option value="security">Güvenlik Prosedürleri</option>
                                    <option value="xray">X-Ray Tarama</option>
                                    <option value="emergency">Acil Durumlar</option>
                                    <option value="communication">İletişim</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Açıklama</label>
                                <textarea name="description" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Süre (Dakika)</label>
                                    <input type="number" name="duration" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Durum</label>
                                    <select name="status" class="form-control" required>
                                        <option value="draft">Taslak</option>
                                        <option value="published">Yayında</option>
                                        <option value="archived">Arşivlenmiş</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">İçerik</label>
                            <textarea name="content" class="form-control" rows="5"></textarea>
                        </div>

                        <!-- Modüller -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Eğitim Modülleri</h6>
                            </div>
                            <div class="card-body">
                                <div id="modulesList"></div>
                                <button type="button" class="btn btn-secondary" onclick="addModule()">
                                    <i class="bx bx-plus"></i> Modül Ekle
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Kaydet</button>
                        <a href="courses.php" class="btn btn-secondary">İptal</a>
                    </form>
                </div>
            </div>
        </div>

        <script>
        let moduleCount = 0;

        function addModule() {
            const modulesList = document.getElementById('modulesList');
            const moduleDiv = document.createElement('div');
            moduleDiv.className = 'module-item card mb-3';
            moduleDiv.innerHTML = `
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h6>Modül #${moduleCount + 1}</h6>
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.module-item').remove()">
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Modül Başlığı</label>
                            <input type="text" name="modules[${moduleCount}][title]" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Modül Türü</label>
                            <select name="modules[${moduleCount}][type]" class="form-control" required onchange="handleModuleTypeChange(this, ${moduleCount})">
                                <option value="video">Video</option>
                                <option value="document">Doküman</option>
                                <option value="quiz">Quiz</option>
                                <option value="simulation">Simülasyon</option>
                            </select>
                        </div>
                    </div>
                    <div class="module-content-area">
                        <label class="form-label">İçerik</label>
                        <textarea name="modules[${moduleCount}][content]" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
            `;
            modulesList.appendChild(moduleDiv);
            moduleCount++;
        }

        function handleModuleTypeChange(select, index) {
            const contentArea = select.closest('.card-body').querySelector('.module-content-area');
            const type = select.value;

            switch(type) {
                case 'video':
                    contentArea.innerHTML = `
                        <label class="form-label">Video URL</label>
                        <input type="url" name="modules[${index}][content]" class="form-control" required>
                        <small class="text-muted">YouTube veya Vimeo video URL'si girin</small>
                    `;
                    break;
                case 'quiz':
                    contentArea.innerHTML = `
                        <label class="form-label">Quiz Soruları (JSON)</label>
                        <textarea name="modules[${index}][content]" class="form-control" rows="5" required></textarea>
                        <small class="text-muted">Quiz sorularını JSON formatında girin</small>
                    `;
                    break;
                case 'simulation':
                    contentArea.innerHTML = `
                        <label class="form-label">Simülasyon ID</label>
                        <input type="number" name="modules[${index}][content]" class="form-control" required>
                        <small class="text-muted">X-Ray simülasyon ID'sini girin</small>
                    `;
                    break;
                default:
                    contentArea.innerHTML = `
                        <label class="form-label">İçerik</label>
                        <textarea name="modules[${index}][content]" class="form-control" rows="3" required></textarea>
                    `;
            }
        }
        </script>
        <?php
        break;

    case 'edit':
        $course_id = $_GET['id'] ?? 0;
        $course = new Course($conn);
        $course_data = $course->getById($course_id);

        if (!$course_data) {
            set_flash_message('danger', 'Eğitim bulunamadı.');
            header('Location: courses.php');
            exit;
        }

        // Yetki kontrolü
        if ($_SESSION['role'] !== 'admin' && $course_data['created_by'] !== $_SESSION['user_id']) {
            set_flash_message('danger', 'Bu eğitimi düzenleme yetkiniz yok.');
            header('Location: courses.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $course->id = $course_id;
                $course->title = clean_input($_POST['title']);
                $course->description = clean_input($_POST['description']);
                $course->content = $_POST['content'];
                $course->duration = (int)$_POST['duration'];
                $course->category = $_POST['category'];
                $course->status = $_POST['status'];

                if ($course->update()) {
                    // Mevcut modülleri temizle
                    $conn->query("DELETE FROM course_modules WHERE course_id = " . $course_id);

                    // Yeni modülleri ekle
                    if (!empty($_POST['modules'])) {
                        foreach ($_POST['modules'] as $order => $module) {
                            $course->addModule([
                                'title' => clean_input($module['title']),
                                'content' => $module['content'],
                                'type' => $module['type'],
                                'order_number' => $order + 1
                            ]);
                        }
                    }

                    set_flash_message('success', 'Eğitim başarıyla güncellendi.');
                    header('Location: courses.php');
                    exit;
                } else {
                    throw new Exception('Eğitim güncellenirken bir hata oluştu.');
                }
            } catch (Exception $e) {
                set_flash_message('danger', $e->getMessage());
            }
        }

        // Mevcut modülleri al
        $modules = $course->getModules();

        $page_title = 'Eğitim Düzenle';
        require_once 'header.php';
        ?>
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title">Eğitim Düzenle: <?php echo htmlspecialchars($course_data['title']); ?></h5>
                    <a href="courses.php" class="btn btn-secondary btn-sm">Geri</a>
                </div>
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate>
                        <!-- Temel Bilgiler -->
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Eğitim Başlığı</label>
                                <input type="text" name="title" class="form-control" 
                                       value="<?php echo htmlspecialchars($course_data['title']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Kategori</label>
                                <select name="category" class="form-control" required>
                                    <option value="security" <?php echo $course_data['category'] === 'security' ? 'selected' : ''; ?>>Güvenlik Prosedürleri</option>
                                    <option value="xray" <?php echo $course_data['category'] === 'xray' ? 'selected' : ''; ?>>X-Ray Tarama</option>
                                    <option value="emergency" <?php echo $course_data['category'] === 'emergency' ? 'selected' : ''; ?>>Acil Durumlar</option>
                                    <option value="communication" <?php echo $course_data['category'] === 'communication' ? 'selected' : ''; ?>>İletişim</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Açıklama</label>
                                <textarea name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($course_data['description']); ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Süre (Dakika)</label>
                                    <input type="number" name="duration" class="form-control" 
                                           value="<?php echo $course_data['duration']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Durum</label>
                                    <select name="status" class="form-control" required>
                                        <option value="draft" <?php echo $course_data['status'] === 'draft' ? 'selected' : ''; ?>>Taslak</option>
                                        <option value="published" <?php echo $course_data['status'] === 'published' ? 'selected' : ''; ?>>Yayında</option>
                                        <option value="archived" <?php echo $course_data['status'] === 'archived' ? 'selected' : ''; ?>>Arşivlenmiş</option>
                                        </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">İçerik</label>
                            <textarea name="content" class="form-control" rows="5"><?php echo htmlspecialchars($course_data['content']); ?></textarea>
                        </div>

                        <!-- Modüller -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Eğitim Modülleri</h6>
                            </div>
                            <div class="card-body">
                                <div id="modulesList">
                                    <?php foreach ($modules as $index => $module): ?>
                                        <div class="module-item card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <h6>Modül #<?php echo $index + 1; ?></h6>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.module-item').remove()">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-8 mb-3">
                                                        <label class="form-label">Modül Başlığı</label>
                                                        <input type="text" name="modules[<?php echo $index; ?>][title]" 
                                                               class="form-control" 
                                                               value="<?php echo htmlspecialchars($module['title']); ?>" required>
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label class="form-label">Modül Türü</label>
                                                        <select name="modules[<?php echo $index; ?>][type]" 
                                                                class="form-control" required 
                                                                onchange="handleModuleTypeChange(this, <?php echo $index; ?>)">
                                                            <option value="video" <?php echo $module['type'] === 'video' ? 'selected' : ''; ?>>Video</option>
                                                            <option value="document" <?php echo $module['type'] === 'document' ? 'selected' : ''; ?>>Doküman</option>
                                                            <option value="quiz" <?php echo $module['type'] === 'quiz' ? 'selected' : ''; ?>>Quiz</option>
                                                            <option value="simulation" <?php echo $module['type'] === 'simulation' ? 'selected' : ''; ?>>Simülasyon</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="module-content-area">
                                                    <?php switch($module['type']):
                                                        case 'video': ?>
                                                            <label class="form-label">Video URL</label>
                                                            <input type="url" name="modules[<?php echo $index; ?>][content]" 
                                                                   class="form-control" 
                                                                   value="<?php echo htmlspecialchars($module['content']); ?>" required>
                                                            <small class="text-muted">YouTube veya Vimeo video URL'si girin</small>
                                                            <?php break;
                                                        case 'quiz': ?>
                                                            <label class="form-label">Quiz Soruları (JSON)</label>
                                                            <textarea name="modules[<?php echo $index; ?>][content]" 
                                                                      class="form-control" rows="5" required><?php echo htmlspecialchars($module['content']); ?></textarea>
                                                            <small class="text-muted">Quiz sorularını JSON formatında girin</small>
                                                            <?php break;
                                                        case 'simulation': ?>
                                                            <label class="form-label">Simülasyon ID</label>
                                                            <input type="number" name="modules[<?php echo $index; ?>][content]" 
                                                                   class="form-control" 
                                                                   value="<?php echo htmlspecialchars($module['content']); ?>" required>
                                                            <small class="text-muted">X-Ray simülasyon ID'sini girin</small>
                                                            <?php break;
                                                        default: ?>
                                                            <label class="form-label">İçerik</label>
                                                            <textarea name="modules[<?php echo $index; ?>][content]" 
                                                                      class="form-control" rows="3" required><?php echo htmlspecialchars($module['content']); ?></textarea>
                                                    <?php endswitch; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-secondary" onclick="addModule()">
                                    <i class="bx bx-plus"></i> Modül Ekle
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Kaydet</button>
                        <a href="courses.php" class="btn btn-secondary">İptal</a>
                    </form>
                </div>
            </div>
        </div>

        <script>
        let moduleCount = <?php echo count($modules); ?>;

        // addModule ve handleModuleTypeChange fonksiyonları yukarıdakiyle aynı
        </script>
        <?php
        break;

    default:
        // Liste görünümü
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $filters = compact('search', 'category', 'status');
        
        $course = new Course($conn);
        $courses = $course->getAll($offset, $limit, $filters);
        $total = $course->getTotal($filters);

        $page_title = 'Eğitimler';
        require_once 'header.php';
        ?>
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title"><?php echo $page_title; ?></h5>
                    <a href="?action=add" class="btn btn-primary">Yeni Eğitim</a>
                </div>
                <div class="card-body">
                    <!-- Filtreler -->
                    <form class="mb-4">
                        <div class="row">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Eğitim ara..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="category" class="form-control">
                                    <option value="">Tüm Kategoriler</option>
                                    <option value="security" <?php echo $category === 'security' ? 'selected' : ''; ?>>Güvenlik Prosedürleri</option>
                                    <option value="xray" <?php echo $category === 'xray' ? 'selected' : ''; ?>>X-Ray Tarama</option>
                                    <option value="emergency" <?php echo $category === 'emergency' ? 'selected' : ''; ?>>Acil Durumlar</option>
                                    <option value="communication" <?php echo $category === 'communication' ? 'selected' : ''; ?>>İletişim</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-control">
                                    <option value="">Tüm Durumlar</option>
                                    <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Yayında</option>
                                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Taslak</option>
                                    <option value="archived" <?php echo $status === 'archived' ? 'selected' : ''; ?>>Arşivlenmiş</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filtrele</button>
                            </div>
                        </div>
                    </form>

                    <!-- Eğitim Listesi -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Başlık</th>
                                    <th>Kategori</th>
                                    <th>Süre</th>
                                    <th>Modül</th>
                                    <th>Katılımcı</th>
                                    <th>Durum</th>
                                    <th>Oluşturan</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><?php echo $course['id']; ?></td>
                                        <td><?php echo htmlspecialchars($course['title']); ?></td>
                                        <td><?php 
                                            $categories = [
                                                'security' => 'Güvenlik Prosedürleri',
                                                'xray' => 'X-Ray Tarama',
                                                'emergency' => 'Acil Durumlar',
                                                'communication' => 'İletişim'
                                            ];
                                            echo $categories[$course['category']] ?? $course['category'];
                                        ?></td>
                                        <td><?php echo $course['duration']; ?> dk</td>
                                        <td><?php echo $course['module_count']; ?></td>
                                        <td><?php echo $course['enrolled_users']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $course['status'] === 'published' ? 'success' : 
                                                    ($course['status'] === 'draft' ? 'warning' : 'secondary');
                                            ?>">
                                                <?php 
                                                $statuses = [
                                                    'published' => 'Yayında',
                                                    'draft' => 'Taslak',
                                                    'archived' => 'Arşivlenmiş'
                                                ];
                                                echo $statuses[$course['status']] ?? $course['status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($course['created_by_name']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="?action=edit&id=<?php echo $course['id']; ?>" 
                                                   class="btn btn-sm btn-warning" title="Düzenle">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                                <?php if ($_SESSION['role'] === 'admin' || $course['created_by'] === $_SESSION['user_id']): ?>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            title="Sil" onclick="deleteCourse(<?php echo $course['id']; ?>)">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="?action=preview&id=<?php echo $course['id']; ?>" 
                                                   class="btn btn-sm btn-info" title="Önizle" target="_blank">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sayfalama -->
                    <?php if ($total > $limit): ?>
                        <nav class="mt-3">
                            <ul class="pagination justify-content-center">
                                <?php
                                $total_pages = ceil($total / $limit);
                                $visible_pages = 5;
                                
                                $start_page = max(1, $page - floor($visible_pages / 2));
                                $end_page = min($total_pages, $start_page + $visible_pages - 1);
                                $start_page = max(1, $end_page - $visible_pages + 1);

                                // İlk sayfa
                                if ($start_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&<?php echo http_build_query($filters); ?>">İlk</a>
                                    </li>
                                <?php endif;

                                // Önceki sayfa
                                if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>">Önceki</a>
                                    </li>
                                <?php endif;

                                // Sayfa numaraları
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor;

                                // Sonraki sayfa
                                if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>">Sonraki</a>
                                    </li>
                                <?php endif;

                                // Son sayfa
                                if ($end_page < $total_pages): ?>
                                    <li class="page-item">
                                      <a class="page-link" href="?page=<?php echo $total_pages; ?>&<?php echo http_build_query($filters); ?>">Son</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>

                    <!-- Toplam kayıt sayısı -->
                    <div class="mt-3">
                        <small class="text-muted">
                            Toplam <?php echo $total; ?> kayıt bulundu.
                            <?php if ($search || $category || $status): ?>
                                <a href="?">Tüm listeyi göster</a>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function deleteCourse(id) {
            if (confirm('Bu eğitimi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
                fetch('api/courses.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || 'Bir hata oluştu');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bir hata oluştu');
                });
            }
        }

        // DataTable başlatma
        $(document).ready(function() {
            $('.table').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/tr.json'
                },
                pageLength: 20,
                order: [[0, 'desc']],
                stateSave: true
            });
        });
        </script>
        <?php
        break;
}

require_once 'footer.php';
?>