<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Havalimanı Güvenlik Eğitim</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <link href="<?php echo SITE_URL; ?>/assets/css/admin.css" rel="stylesheet">
    <?php endif; ?>

    <!-- Custom CSS -->
    <?php if (isset($custom_css)): ?>
        <link href="<?php echo SITE_URL; ?>/assets/css/<?php echo $custom_css; ?>" rel="stylesheet">
    <?php endif; ?>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $_SESSION['role'] === 'admin' ? ADMIN_URL : USER_URL; ?>">
                Havalimanı Güvenlik
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <!-- Admin Menüsü -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_menu_active('dashboard.php'); ?>" href="<?php echo ADMIN_URL; ?>/dashboard.php">
                                <i class="bx bx-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_menu_active('users.php'); ?>" href="<?php echo ADMIN_URL; ?>/users.php">
                                <i class="bx bx-user"></i> Kullanıcılar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_menu_active('courses.php'); ?>" href="<?php echo ADMIN_URL; ?>/courses.php">
                                <i class="bx bx-book"></i> Eğitimler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_menu_active('simulations.php'); ?>" href="<?php echo ADMIN_URL; ?>/simulations.php">
                                <i class="bx bx-scan"></i> Simülasyonlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_menu_active('reports.php'); ?>" href="<?php echo ADMIN_URL; ?>/reports.php">
                                <i class="bx bx-chart"></i> Raporlar
                            </a>
                        </li>
                    </ul>
                <?php else: ?>
                    <!-- Kullanıcı Menüsü -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_menu_active('dashboard.php'); ?>" href="<?php echo USER_URL; ?>/dashboard.php">
                                <i class="bx bx-home"></i> Ana Sayfa
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_menu_active('courses.php'); ?>" href="<?php echo USER_URL; ?>/courses.php">
                                <i class="bx bx-book"></i> Eğitimlerim
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_menu_active('simulator.php'); ?>" href="<?php echo USER_URL; ?>/simulator.php">
                                <i class="bx bx-scan"></i> X-Ray Simülatörü
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_menu_active('progress.php'); ?>" href="<?php echo USER_URL; ?>/progress.php">
                                <i class="bx bx-line-chart"></i> İlerlemem
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>

                <!-- Sağ Menü -->
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bx bx-user-circle"></i> <?php echo $_SESSION['username']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?php echo USER_URL; ?>/profile.php">
                                    <i class="bx bx-user"></i> Profil
                                </a>
                            </li>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo ADMIN_URL; ?>/settings.php">
                                        <i class="bx bx-cog"></i> Ayarlar
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php">
                                    <i class="bx bx-log-out"></i> Çıkış
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Mesajlar -->
    <div class="container mt-3">
        <?php echo show_flash_message(); ?>
    </div>

    <!-- Ana İçerik Başlangıcı -->
    <main class="py-4">