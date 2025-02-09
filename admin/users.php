<?php
require_once '../includes/config.php';
check_auth(['admin']);

$action = $_GET['action'] ?? 'list';

switch($action) {
    case 'add':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $user = new User($conn);
                
                // Gerekli alanları kontrol et
                $required_fields = ['username', 'email', 'password', 'first_name', 'last_name', 'role'];
                foreach ($required_fields as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception($field . ' alanı gereklidir.');
                    }
                }

                // Benzersiz alanları kontrol et
                if ($user->usernameExists($_POST['username'])) {
                    throw new Exception('Bu kullanıcı adı zaten kullanılıyor.');
                }
                if ($user->emailExists($_POST['email'])) {
                    throw new Exception('Bu email adresi zaten kullanılıyor.');
                }

                // Kullanıcı verilerini ata
                $user->username = clean_input($_POST['username']);
                $user->password = $_POST['password'];
                $user->email = clean_input($_POST['email']);
                $user->first_name = clean_input($_POST['first_name']);
                $user->last_name = clean_input($_POST['last_name']);
                $user->role = $_POST['role'];
                $user->status = 'active';

                if ($user->create()) {
                    set_flash_message('success', 'Kullanıcı başarıyla oluşturuldu.');
                    header('Location: users.php');
                    exit;
                } else {
                    throw new Exception('Kullanıcı oluşturulurken bir hata oluştu.');
                }
            } catch (Exception $e) {
                set_flash_message('danger', $e->getMessage());
            }
        }
        
        $page_title = 'Yeni Kullanıcı Ekle';
        require_once 'header.php';
        ?>
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Yeni Kullanıcı Ekle</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kullanıcı Adı</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                        </div>

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

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Şifre</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rol</label>
                                <select name="role" class="form-control" required>
                                    <option value="trainee">Eğitim Alan</option>
                                    <option value="instructor">Eğitmen</option>
                                    <option value="admin">Yönetici</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Kaydet</button>
                        <a href="users.php" class="btn btn-secondary">İptal</a>
                    </form>
                </div>
            </div>
        </div>
        <?php
        break;

    case 'edit':
        $user_id = $_GET['id'] ?? 0;
        $user = new User($conn);
        $user_data = $user->getById($user_id);

        if (!$user_data) {
            set_flash_message('danger', 'Kullanıcı bulunamadı.');
            header('Location: users.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Email kontrolü
                if ($_POST['email'] !== $user_data['email'] && $user->emailExists($_POST['email'])) {
                    throw new Exception('Bu email adresi başka bir kullanıcı tarafından kullanılıyor.');
                }

                // Kullanıcı verilerini güncelle
                $user->id = $user_id;
                $user->email = clean_input($_POST['email']);
                $user->first_name = clean_input($_POST['first_name']);
                $user->last_name = clean_input($_POST['last_name']);
                $user->role = $_POST['role'];
                $user->status = $_POST['status'];

                // Şifre değiştirilecekse
                if (!empty($_POST['password'])) {
                    $user->password = $_POST['password'];
                }

                if ($user->update()) {
                    set_flash_message('success', 'Kullanıcı başarıyla güncellendi.');
                    header('Location: users.php');
                    exit;
                } else {
                    throw new Exception('Kullanıcı güncellenirken bir hata oluştu.');
                }
            } catch (Exception $e) {
                set_flash_message('danger', $e->getMessage());
            }
        }

        $page_title = 'Kullanıcı Düzenle';
        require_once 'header.php';
        ?>
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title">Kullanıcı Düzenle</h5>
                    <a href="users.php" class="btn btn-secondary btn-sm">Geri</a>
                </div>
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kullanıcı Adı</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ad</label>
                                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Soyad</label>
                                <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Yeni Şifre (Değiştirmek için doldurun)</label>
                                <input type="password" name="password" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rol</label>
                                <select name="role" class="form-control" required>
                                    <option value="trainee" <?php echo $user_data['role'] === 'trainee' ? 'selected' : ''; ?>>Eğitim Alan</option>
                                    <option value="instructor" <?php echo $user_data['role'] === 'instructor' ? 'selected' : ''; ?>>Eğitmen</option>
                                    <option value="admin" <?php echo $user_data['role'] === 'admin' ? 'selected' : ''; ?>>Yönetici</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Durum</label>
                            <select name="status" class="form-control" required>
                                <option value="active" <?php echo $user_data['status'] === 'active' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="inactive" <?php echo $user_data['status'] === 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                                <option value="suspended" <?php echo $user_data['status'] === 'suspended' ? 'selected' : ''; ?>>Askıya Alınmış</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Güncelle</button>
                        <a href="users.php" class="btn btn-secondary">İptal</a>
                    </form>

                    <!-- Kullanıcı İstatistikleri -->
                    <hr>
                    <h6>Kullanıcı İstatistikleri</h6>
                    <?php
                    $progress = new Progress($conn);
                    $user_stats = $progress->getUserStats($user_id);
                    ?>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Toplam Eğitim</h6>
                                    <h4><?php echo $user_stats['total_courses']; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Tamamlanan Modül</h6>
                                    <h4><?php echo $user_stats['completed_modules']; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Aktif Sertifika</h6>
                                    <h4><?php echo $user_stats['active_certificates']; ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Son Aktivite</h6>
                                    <small><?php echo time_elapsed_string($user_stats['last_activity']); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        break;

    default:
        // Kullanıcı listesi
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $search = $_GET['search'] ?? '';
        
        $user = new User($conn);
        $users = $user->getAll($offset, $limit, $search);
        $total = $user->getTotal($search);

        $page_title = 'Kullanıcılar';
        require_once 'header.php';
        ?>
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title">Kullanıcılar</h5>
                    <a href="?action=add" class="btn btn-primary">Yeni Kullanıcı</a>
                </div>
                <div class="card-body">
                    <!-- Arama formu -->
                    <form class="mb-4">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Kullanıcı ara..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary">Ara</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Kullanıcı Adı</th>
                                    <th>Ad Soyad</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Durum</th>
                                    <th>Son Giriş</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['role'] === 'admin' ? 'danger' : 
                                                    ($user['role'] === 'instructor' ? 'warning' : 'info');
                                            ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['status'] === 'active' ? 'success' : 
                                                    ($user['status'] === 'inactive' ? 'warning' : 'danger');
                                            ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $user['last_login'] ? format_date($user['last_login']) : '-'; ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="?action=edit&id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-warning" 
                                                   title="Düzenle">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-danger" 
                                                            title="Sil"
                                                            onclick="if(confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?')) adminCrud.delete('users.php', <?php echo $user['id']; ?>)">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sayfalama -->
                    <?php if ($total > $limit): ?>
                        <div class="mt-3">
                            <nav>
                                <ul class="pagination">
                                    <?php
                                    $total_pages = ceil($total / $limit);
                                    $visible_pages = 5;
                                    
                                    // Başlangıç ve bitiş sayfalarını hesapla
                                    $start_page = max(1, $page - floor($visible_pages / 2));
                                    $end_page = min($total_pages, $start_page + $visible_pages - 1);
                                    
                                    // Başlangıç sayfasını güncelle
                                    $start_page = max(1, $end_page - $visible_pages + 1);

                                    // İlk sayfa
                                    if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">İlk</a>
                                        </li>
                                    <?php endif;

                                    // Önceki sayfa
                                    if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Önceki</a>
                                        </li>
                                    <?php endif;

                                    // Sayfa numaraları
                                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor;

                                    // Sonraki sayfa
                                    if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Sonraki</a>
                                        </li>
                                    <?php endif;

                                    // Son sayfa
                                    if ($end_page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Son</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>

                    <!-- Toplam kayıt sayısı -->
                    <div class="mt-3">
                        <small class="text-muted">
                            Toplam <?php echo $total; ?> kayıt bulundu.
                            <?php if ($search): ?>
                                <a href="?">Tüm listeyi göster</a>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <?php
        break;
}

require_once 'footer.php';
?>