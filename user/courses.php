<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/auth_check.php';

// Kullanıcı ID'sini al
$user_id = $_SESSION['user_id'];

// Kullanıcının kayıtlı olduğu kursları getir
$enrolled_courses_query = "
    SELECT c.*, 
           COALESCE(completed.total_completed, 0) as completed_lessons,
           COALESCE(all_lessons.total_lessons, 0) as total_lessons,
           COALESCE(ROUND(completed.total_completed * 100.0 / all_lessons.total_lessons, 1), 0) as progress,
           uc.enrollment_date,
           uc.last_accessed
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
    WHERE uc.user_id = ?
    ORDER BY uc.last_accessed DESC";

$stmt = $conn->prepare($enrolled_courses_query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$enrolled_courses = $stmt->get_result();

// Diğer mevcut kursları getir
$other_courses_query = "
    SELECT c.*, COUNT(l.id) as total_lessons
    FROM courses c
    LEFT JOIN lessons l ON c.id = l.course_id
    WHERE c.id NOT IN (
        SELECT course_id 
        FROM user_courses 
        WHERE user_id = ?
    )
    AND c.status = 'active'
    GROUP BY c.id
    ORDER BY c.created_at DESC";

$stmt = $conn->prepare($other_courses_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$other_courses = $stmt->get_result();

// Departman filtreleme için departmanları getir
$departments_query = "SELECT DISTINCT department FROM courses WHERE status = 'active'";
$departments = $conn->query($departments_query);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurslarım</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/courses.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="courses-container">
        <section class="my-courses">
            <h1>Kurslarım</h1>
            
            <!-- Arama ve Filtreleme -->
            <div class="course-filters">
                <input type="text" id="courseSearch" placeholder="Kurs ara...">
                <select id="departmentFilter">
                    <option value="">Tüm Departmanlar</option>
                    <?php while ($department = $departments->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($department['department']); ?>">
                            <?php echo htmlspecialchars($department['department']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <select id="progressFilter">
                    <option value="">Tüm İlerlemeler</option>
                    <option value="not_started">Başlanmamış</option>
                    <option value="in_progress">Devam Eden</option>
                    <option value="completed">Tamamlanmış</option>
                </select>
            </div>

            <!-- Kayıtlı Kurslar -->
            <div class="courses-grid">
                <?php while ($course = $enrolled_courses->fetch_assoc()): ?>
                    <div class="course-card" 
                         data-department="<?php echo htmlspecialchars($course['department']); ?>"
                         data-progress="<?php echo $course['progress'] == 100 ? 'completed' : ($course['progress'] > 0 ? 'in_progress' : 'not_started'); ?>">
                        
                        <div class="course-image">
                            <img src="<?php echo htmlspecialchars($course['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($course['title']); ?>">
                            <div class="course-progress">
                                <div class="progress-bar">
                                    <div class="progress" style="width: <?php echo $course['progress']; ?>%"></div>
                                </div>
                                <span>%<?php echo $course['progress']; ?> tamamlandı</span>
                            </div>
                        </div>
                        
                        <div class="course-info">
                            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                            <p class="course-description"><?php echo htmlspecialchars($course['description']); ?></p>
                            
                            <div class="course-stats">
                                <div class="stat">
                                    <i class="fas fa-book-open"></i>
                                    <span><?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?> ders</span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo $course['duration']; ?> saat</span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-building"></i>
                                    <span><?php echo htmlspecialchars($course['department']); ?></span>
                                </div>
                            </div>
                            
                            <div class="course-footer">
                                <a href="course_detail.php?id=<?php echo $course['id']; ?>" class="btn-primary">
                                    <?php echo $course['progress'] == 0 ? 'Başla' : 'Devam Et'; ?>
                                </a>
                                <small>Son erişim: <?php echo date('d.m.Y', strtotime($course['last_accessed'])); ?></small>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>

        <!-- Diğer Kurslar -->
        <section class="other-courses">
            <h2>Diğer Kurslar</h2>
            <div class="courses-grid">
                <?php while ($course = $other_courses->fetch_assoc()): ?>
                    <div class="course-card">
                        <div class="course-image">
                            <img src="<?php echo htmlspecialchars($course['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($course['title']); ?>">
                        </div>
                        
                        <div class="course-info">
                            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                            <p class="course-description"><?php echo htmlspecialchars($course['description']); ?></p>
                            
                            <div class="course-stats">
                                <div class="stat">
                                    <i class="fas fa-book-open"></i>
                                    <span><?php echo $course['total_lessons']; ?> ders</span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo $course['duration']; ?> saat</span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-building"></i>
                                    <span><?php echo htmlspecialchars($course['department']); ?></span>
                                </div>
                            </div>
                            
                            <div class="course-footer">
                                <a href="course_detail.php?id=<?php echo $course['id']; ?>" class="btn-secondary">
                                    Detayları Gör
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/courses.js"></script>
</body>
</html>