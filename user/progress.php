<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Genel ilerleme istatistiklerini getir
$stats_query = "
    SELECT 
        COUNT(DISTINCT c.id) as total_courses,
        COUNT(DISTINCT cl.lesson_id) as completed_lessons,
        SUM(cl.time_spent) as total_time,
        (
            SELECT COUNT(*) 
            FROM completed_lessons cl2 
            WHERE cl2.user_id = ? 
            AND DATE(cl2.completion_date) = CURDATE()
        ) as today_completed
    FROM user_courses uc
    LEFT JOIN courses c ON uc.course_id = c.id
    LEFT JOIN completed_lessons cl ON c.id = cl.course_id AND cl.user_id = uc.user_id
    WHERE uc.user_id = ?";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Kurs bazlı ilerleme
$courses_progress_query = "
    SELECT 
        c.id,
        c.title,
        c.image_url,
        COUNT(DISTINCT cl.lesson_id) as completed_lessons,
        (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as total_lessons,
        COALESCE(
            ROUND(
                COUNT(DISTINCT cl.lesson_id) * 100.0 / 
                NULLIF((SELECT COUNT(*) FROM lessons WHERE course_id = c.id), 0),
                1
            ),
            0
        ) as progress,
        MAX(cl.completion_date) as last_activity
    FROM user_courses uc
    JOIN courses c ON uc.course_id = c.id
    LEFT JOIN completed_lessons cl ON c.id = cl.course_id AND cl.user_id = uc.user_id
    WHERE uc.user_id = ?
    GROUP BY c.id
    ORDER BY last_activity DESC NULLS LAST";

$stmt = $conn->prepare($courses_progress_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$courses_progress = $stmt->get_result();

// Son 7 günlük aktivite verilerini getir
$weekly_activity_query = "
    SELECT 
        DATE(completion_date) as date,
        COUNT(*) as completed_count,
        SUM(time_spent) as time_spent
    FROM completed_lessons
    WHERE user_id = ?
    AND completion_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(completion_date)
    ORDER BY date ASC";

$stmt = $conn->prepare($weekly_activity_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$weekly_activity = $stmt->get_result();

$activity_data = [];
for($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $activity_data[$date] = [
        'completed' => 0,
        'time_spent' => 0
    ];
}

while($row = $weekly_activity->fetch_assoc()) {
    $activity_data[$row['date']] = [
        'completed' => $row['completed_count'],
        'time_spent' => $row['time_spent']
    ];
}

// JSON formatına çevir
$activity_json = json_encode($activity_data);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İlerleme Durumu</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/progress.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="progress-container">
        <h1>İlerleme Durumu</h1>

        <!-- Genel İstatistikler -->
        <section class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo $stats['total_courses']; ?></span>
                    <span class="stat-label">Toplam Kurs</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo $stats['completed_lessons']; ?></span>
                    <span class="stat-label">Tamamlanan Ders</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo round($stats['total_time'] / 60); ?></span>
                    <span class="stat-label">Toplam Saat</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo $stats['today_completed']; ?></span>
                    <span class="stat-label">Bugün Tamamlanan</span>
                </div>
            </div>
        </section>

        <!-- Aktivite Grafikleri -->
        <section class="activity-charts">
            <div class="chart-container">
                <h2>7 Günlük Aktivite</h2>
                <canvas id="weeklyActivityChart"></canvas>
            </div>
        </section>

        <!-- Kurs İlerlemeleri -->
        <section class="course-progress">
            <h2>Kurs İlerlemeleri</h2>
            <div class="courses-grid">
                <?php while ($course = $courses_progress->fetch_assoc()): ?>
                    <div class="course-card">
                        <div class="course-info">
                            <img src="<?php echo htmlspecialchars($course['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($course['title']); ?>">
                            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                            <div class="progress-stats">
                                <div class="progress-bar">
                                    <div class="progress" style="width: <?php echo $course['progress']; ?>%"></div>
                                </div>
                                <span class="progress-text">
                                    %<?php echo $course['progress']; ?> tamamlandı
                                </span>
                                <span class="lesson-count">
                                    <?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?> ders
                                </span>
                            </div>
                            <?php if ($course['last_activity']): ?>
                                <small class="last-activity">
                                    Son aktivite: <?php echo date('d.m.Y', strtotime($course['last_activity'])); ?>
                                </small>
                            <?php endif; ?>
                            <a href="course.php?id=<?php echo $course['id']; ?>" class="btn-secondary">
                                Kursa Git
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Weekly activity data from PHP
        const activityData = <?php echo $activity_json; ?>;
    </script>
    <script src="../assets/js/progress.js"></script>
</body>
</html>