<?php
require_once '../includes/config.php';
check_auth(['admin']);

// Genel istatistikleri al
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => $conn->query("
        SELECT COUNT(DISTINCT user_id) 
        FROM user_progress 
        WHERE last_accessed >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ")->fetchColumn(),
    'total_courses' => $conn->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
    'total_simulations' => $conn->query("SELECT COUNT(*) FROM xray_simulations")->fetchColumn(),
    'total_certificates' => $conn->query("
        SELECT COUNT(*) FROM certificates WHERE status = 'active'
    ")->fetchColumn(),
    'avg_score' => $conn->query("
        SELECT ROUND(AVG(score), 1) FROM simulation_attempts
    ")->fetchColumn()
];

// Son kullanıcı aktiviteleri
$recent_activities = $conn->query("
    (SELECT 
        u.username,
        'course' as type,
        c.title as target,
        up.created_at as date
    FROM user_progress up
    JOIN users u ON up.user_id = u.id
    JOIN courses c ON up.course_id = c.id
    WHERE up.status = 'completed')
    UNION ALL
    (SELECT 
        u.username,
        'simulation' as type,
        xs.title as target,
        sa.attempted_at as date
    FROM simulation_attempts sa
    JOIN users u ON sa.user_id = u.id
    JOIN xray_simulations xs ON sa.simulation_id = xs.id)
    ORDER BY date DESC
    LIMIT 10
")->fetchAll();

// Son başarılı/başarısız simülasyonlar
$recent_simulations = $conn->query("
    SELECT 
        u.username,
        xs.title,
        sa.score,
        sa.passed,
        sa.attempted_at
    FROM simulation_attempts sa
    JOIN users u ON sa.user_id = u.id
    JOIN xray_simulations xs ON sa.simulation_id = xs.id
    ORDER BY sa.attempted_at DESC
    LIMIT 5
")->fetchAll();

$page_title = 'Dashboard';
require_once 'header.php';
?>

<div class="container-fluid">
    <!-- İstatistik Kartları -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5>Toplam Kullanıcı</h5>
                    <h3><?php echo number_format($stats['total_users']); ?></h3>
                    <small>Aktif: <?php echo number_format($stats['active_users']); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Toplam Eğitim</h5>
                    <h3><?php echo number_format($stats['total_courses']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5>Simülasyonlar</h5>
                    <h3><?php echo number_format($stats['total_simulations']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5>Sertifikalar</h5>
                    <h3><?php echo number_format($stats['total_certificates']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5>Ort. Puan</h5>
                    <h3><?php echo $stats['avg_score']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Son Aktiviteler -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Son Aktiviteler</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-<?php 
                                    echo $activity['type'] === 'course' ? 'success' : 'info'; 
                                ?>"></div>
                                <div class="timeline-content">
                                    <p class="mb-1">
                                        <strong><?php echo htmlspecialchars($activity['username']); ?></strong>
                                        <?php 
                                        echo $activity['type'] === 'course' ? 
                                            'eğitimi tamamladı: ' : 
                                            'simülasyonu denedi: ';
                                        echo htmlspecialchars($activity['target']); 
                                        ?>
                                    </p>
                                    <small class="text-muted">
                                        <?php echo time_elapsed_string($activity['date']); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Son Simülasyon Sonuçları -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Son Simülasyon Sonuçları</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Kullanıcı</th>
                                    <th>Simülasyon</th>
                                    <th>Puan</th>
                                    <th>Sonuç</th>
                                    <th>Tarih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_simulations as $sim): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sim['username']); ?></td>
                                        <td><?php echo htmlspecialchars($sim['title']); ?></td>
                                        <td><?php echo $sim['score']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $sim['passed'] ? 'success' : 'danger'; 
                                            ?>">
                                                <?php echo $sim['passed'] ? 'Başarılı' : 'Başarısız'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_date($sim['attempted_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- İstatistik Grafikleri -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Kullanıcı İstatistikleri</h5>
                </div>
                <div class="card-body">
                    <canvas id="userStatsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Simülasyon Başarı Oranları</h5>
                </div>
                <div class="card-body">
                    <canvas id="simulationStatsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>