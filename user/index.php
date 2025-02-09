<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Son girilen kursları getir
$recent_courses_query = "
    SELECT c.*, 
           COALESCE(completed.total_completed, 0) as completed_lessons,
           COALESCE(all_lessons.total_lessons, 0) as total_lessons,
           COALESCE(ROUND(completed.total_completed * 100.0 / all_lessons.total_lessons, 1), 0) as progress,
           uc.last_accessed
    FROM user_courses uc
    INNER JOIN courses c ON uc.course_id = c.id
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
    WHERE uc.user_id = ?
    ORDER BY uc.last_accessed DESC
    LIMIT 4";

$stmt = $conn->prepare($recent_courses_query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$recent_courses = $stmt->get_result();

// Duyuruları getir
$announcements_query = "
    SELECT * FROM announcements 
    WHERE (department_id IN (SELECT department_id FROM user_departments WHERE user_id = ?)
    OR department_id IS NULL)
    AND expiry_date > NOW()
    ORDER BY created_at DESC 
    LIMIT 5";

$stmt = $conn->prepare($announcements_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$announcements = $stmt->get_result();

// Yaklaşan eğitimleri getir
$upcoming_trainings_query = "
    SELECT t.*, c.title as course_title
    FROM scheduled_trainings t
    INNER JOIN courses c ON t.course_id = c.id
    WHERE t.training_date > NOW()
    AND t.department_id IN (SELECT department_id FROM user_departments WHERE user_id = ?)
    ORDER BY t.training_date ASC
    LIMIT 3";

$stmt = $conn->prepare($upcoming_trainings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_trainings = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eğitim Platformu</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/index.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main>
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-content">
                <h1>Kurum İçi Eğitim Platformu</h1>
                <p>Profesyonel gelişiminiz için özelleştirilmiş eğitim içerikleri</p>
                <a href="courses.php" class="btn-primary">Kurslara Göz At</a>
            </div>
        </section>

        <div class="main-container">
            <!-- Son Girilen Kurslar -->
            <section class="recent-courses">
                <div class="section-header">
                    <h2>Son Girilen Kurslar</h2>
                    <a href="courses.php" class="btn-text">Tümünü Gör →</a>
                </div>
                <div class="courses-grid">
                    <?php while ($course = $recent_courses->fetch_assoc()): ?>
                        <div class="course-card">
                            <div class="course-image">
                                <img src="<?php echo htmlspecialchars($course['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($course['title']); ?>">
                                <div class="progress-overlay">
                                    <div class="progress-circle" data-progress="<?php echo $course['progress']; ?>">
                                        <span class="progress-text">%<?php echo $course['progress']; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="course-info">
                                <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                                <div class="course-stats">
                                    <span>
                                        <i class="fas fa-book-open"></i>
                                        <?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?> ders
                                    </span>
                                </div>
                                <a href="course.php?id=<?php echo $course['id']; ?>" class="btn-secondary">
                                    Devam Et
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </section>

            <div class="sidebar-content">
                <!-- Duyurular -->
                <section class="announcements">
                    <h2>Duyurular</h2>
                    <div class="announcements-list">
                        <?php while ($announcement = $announcements->fetch_assoc()): ?>
                            <div class="announcement-card">
                                <span class="announcement-date">
                                    <?php echo date('d.m.Y', strtotime($announcement['created_at'])); ?>
                                </span>
                                <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                <p><?php echo htmlspecialchars($announcement['content']); ?></p>
                                <?php if ($announcement['link']): ?>
                                    <a href="<?php echo htmlspecialchars($announcement['link']); ?>" class="btn-text">
                                        Detaylar →
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </section>

                <!-- Yaklaşan Eğitimler -->
                <section class="upcoming-trainings">
                    <h2>Yaklaşan Eğitimler</h2>
                    <div class="trainings-list">
                        <?php while ($training = $upcoming_trainings->fetch_assoc()): ?>
                            <div class="training-card">
                                <div class="training-date">
                                    <span class="date">
                                        <?php echo date('d', strtotime($training['training_date'])); ?>
                                    </span>
                                    <span class="month">
                                        <?php echo strftime('%b', strtotime($training['training_date'])); ?>
                                    </span>
                                </div>
                                <div class="training-info">
                                    <h3><?php echo htmlspecialchars($training['course_title']); ?></h3>
                                    <p>
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('H:i', strtotime($training['training_date'])); ?>
                                    </p>
                                    <p>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($training['location']); ?>
                                    </p>
                                </div>
                                <?php if ($training['registration_required']): ?>
                                    <button class="btn-register" data-training-id="<?php echo $training['id']; ?>">
                                        Kayıt Ol
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script src="../assets/js/index.js"></script>
</body>
</html>