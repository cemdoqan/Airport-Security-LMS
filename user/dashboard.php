<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Kullanıcı bilgilerini getir
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Devam eden kursları getir
$active_courses_query = "
    SELECT c.*, 
           COALESCE(completed.total_completed, 0) as completed_lessons,
           COALESCE(all_lessons.total_lessons, 0) as total_lessons,
           COALESCE(ROUND(completed.total_completed * 100.0 / all_lessons.total_lessons, 1), 0) as progress
    FROM courses c
    INNER JOIN user_courses uc ON c.id = uc.course_id
    LEFT JOIN (
        SELECT course_id, COUNT(*) as total_completed 
        FROM completed_lessons 
        WHERE user_id = ? 
        GROUP BY course_id
    ) completed ON c.id = completed.course_id
    LEFT JOIN (
        SELECT course_id, COUNT(*) as total_lessons 
        FROM lessons 
        GROUP BY course_id
    ) all_lessons ON c.id = all_lessons.course_id
    WHERE uc.user_id = ? AND progress < 100
    ORDER BY uc.last_accessed DESC
    LIMIT 3";

$stmt = $conn->prepare($active_courses_query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$active_courses = $stmt->get_result();

// Son aktiviteleri getir
$activities_query = "
    SELECT a.*, c.title as course_title 
    FROM user_activities a
    LEFT JOIN courses c ON a.course_id = c.id
    WHERE user_id = ?
    ORDER BY activity_date DESC
    LIMIT 5";

$stmt = $conn->prepare($activities_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$activities = $stmt->get_result();

// Yaklaşan görevleri getir
$upcoming_tasks_query = "
    SELECT t.*, c.title as course_title
    FROM tasks t
    INNER JOIN courses c ON t.course_id = c.id
    INNER JOIN user_courses uc ON c.id = uc.course_id
    WHERE uc.user_id = ? AND t.due_date > NOW()
    ORDER BY t.due_date ASC
    LIMIT 5";

$stmt = $conn->prepare($upcoming_tasks_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_tasks = $stmt->get_result();

// Genel istatistikleri getir
$stats_query = "
    SELECT 
        COUNT(DISTINCT uc.course_id) as total_courses,
        SUM(cl.time_spent) as total_time,
        COUNT(DISTINCT cl.lesson_id) as completed_lessons,
        (SELECT COUNT(*) FROM badges WHERE user_id = ?) as earned_badges
    FROM user_courses uc
    LEFT JOIN completed_lessons cl ON uc.user_id = cl.user_id AND uc.course_id = cl.course_id
    WHERE uc.user_id = ?";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($user['name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="dashboard-container">
        <!-- Hoşgeldin Bölümü -->
        <section class="welcome-section">
            <div class="user-info">
                <h1>Hoş geldin, <?php echo htmlspecialchars($user['name']); ?>!</h1>
                <p>Öğrenme yolculuğuna devam et.</p>
            </div>
            <div class="quick-stats">
                <div class="stat-card">
                    <span class="stat-value"><?php echo $stats['total_courses']; ?></span>
                    <span class="stat-label">Toplam Kurs</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo round($stats['total_time'] / 60); ?></span>
                    <span class="stat-label">Toplam Saat</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo $stats['completed_lessons']; ?></span>
                    <span class="stat-label">Tamamlanan Ders</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo $stats['earned_badges']; ?></span>
                    <span class="stat-label">Rozet</span>
                </div>
            </div>
        </section>

        <div class="dashboard-grid">
            <!-- Devam Eden Kurslar -->
            <section class="active-courses">
                <h2>Devam Eden Kurslar</h2>
                <div class="courses-list">
                    <?php while ($course = $active_courses->fetch_assoc()): ?>
                        <div class="course-card">
                            <div class="course-info">
                                <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                                <div class="progress-info">
                                    <div class="progress-bar">
                                        <div class="progress" style="width: <?php echo $course['progress']; ?>%"></div>
                                    </div>
                                    <span class="progress-text">%<?php echo $course['progress']; ?> tamamlandı</span>
                                </div>
                                <div class="lesson-count">
                                    <span><?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?> ders</span>
                                </div>
                            </div>
                            <a href="course.php?id=<?php echo $course['id']; ?>" class="btn-primary">Devam Et</a>
                        </div>
                    <?php endwhile; ?>
                    <a href="courses.php" class="see-all-link">Tüm Kursları Gör →</a>
                </div>
            </section>

            <!-- Son Aktiviteler -->
            <section class="recent-activities">
                <h2>Son Aktiviteler</h2>
                <div class="activities-list">
                    <?php while ($activity = $activities->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-<?php echo $activity['activity_type']; ?>"></i>
                            </div>
                            <div class="activity-details">
                                <p>
                                    <strong><?php echo htmlspecialchars($activity['course_title']); ?></strong>
                                    <?php echo htmlspecialchars($activity['description']); ?>
                                </p>
                                <small><?php echo date('d.m.Y H:i', strtotime($activity['activity_date'])); ?></small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </section>

            <!-- Yaklaşan Görevler -->
            <section class="upcoming-tasks">
                <h2>Yaklaşan Görevler</h2>
                <div class="tasks-list">
                    <?php while ($task = $upcoming_tasks->fetch_assoc()): ?>
                        <div class="task-item">
                            <div class="task-status">
                                <input type="checkbox" id="task_<?php echo $task['id']; ?>" 
                                       class="task-checkbox" data-task-id="<?php echo $task['id']; ?>">
                                <label for="task_<?php echo $task['id']; ?>"></label>
                            </div>
                            <div class="task-info">
                                <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                                <p><?php echo htmlspecialchars($task['course_title']); ?></p>
                                <small class="due-date">Teslim: <?php echo date('d.m.Y', strtotime($task['due_date'])); ?></small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </section>

            <!-- Öğrenme İstatistikleri -->
            <section class="learning-stats">
                <h2>Öğrenme İstatistikleri</h2>
                <div class="stats-container">
                    <div id="weeklyProgressChart"></div>
                    <div id="timeSpentChart"></div>
                </div>
            </section>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>