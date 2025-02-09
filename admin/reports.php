<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/admin_auth_check.php';

// Tarih filtresi için varsayılan değerler
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Rapor tipi seçimi
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'user_progress';

// Rapor verilerini alma fonksiyonu
function getReportData($conn, $report_type, $start_date, $end_date) {
    switch($report_type) {
        case 'user_progress':
            $query = "SELECT 
                        u.name,
                        COUNT(DISTINCT cl.lesson_id) as completed_lessons,
                        SUM(cl.time_spent) as total_time,
                        AVG(cl.score) as average_score
                    FROM users u
                    LEFT JOIN completed_lessons cl ON u.id = cl.user_id
                    WHERE cl.completion_date BETWEEN ? AND ?
                    GROUP BY u.id
                    ORDER BY completed_lessons DESC";
            break;
            
        case 'course_engagement':
            $query = "SELECT 
                        c.title as course_name,
                        COUNT(DISTINCT uc.user_id) as total_students,
                        COUNT(DISTINCT cl.lesson_id) as completed_lessons,
                        AVG(cl.score) as average_score
                    FROM courses c
                    LEFT JOIN user_courses uc ON c.id = uc.course_id
                    LEFT JOIN completed_lessons cl ON c.id = cl.course_id
                    WHERE cl.completion_date BETWEEN ? AND ?
                    GROUP BY c.id
                    ORDER BY total_students DESC";
            break;
            
        case 'assessment_results':
            $query = "SELECT 
                        a.title as assessment_name,
                        COUNT(ar.id) as total_attempts,
                        AVG(ar.score) as average_score,
                        MIN(ar.score) as lowest_score,
                        MAX(ar.score) as highest_score
                    FROM assessments a
                    LEFT JOIN assessment_results ar ON a.id = ar.assessment_id
                    WHERE ar.completion_date BETWEEN ? AND ?
                    GROUP BY a.id
                    ORDER BY average_score DESC";
            break;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result();
}

// Rapor verilerini al
$report_data = getReportData($conn, $report_type, $start_date, $end_date);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.css">
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    
    <main class="admin-main">
        <div class="report-container">
            <div class="report-header">
                <h1>Raporlar</h1>
                
                <!-- Filtre formu -->
                <form class="report-filters">
                    <div class="filter-group">
                        <label for="report_type">Rapor Tipi:</label>
                        <select name="report_type" id="report_type">
                            <option value="user_progress" <?php echo $report_type == 'user_progress' ? 'selected' : ''; ?>>Kullanıcı İlerlemesi</option>
                            <option value="course_engagement" <?php echo $report_type == 'course_engagement' ? 'selected' : ''; ?>>Kurs Katılımı</option>
                            <option value="assessment_results" <?php echo $report_type == 'assessment_results' ? 'selected' : ''; ?>>Değerlendirme Sonuçları</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="start_date">Başlangıç:</label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="end_date">Bitiş:</label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    
                    <button type="submit" class="btn-primary">Filtrele</button>
                    <button type="button" id="exportReport" class="btn-secondary">Dışa Aktar</button>
                </form>
            </div>
            
            <!-- Rapor içeriği -->
            <div class="report-content">
                <!-- Grafik alanı -->
                <div class="report-chart">
                    <canvas id="reportChart"></canvas>
                </div>
                
                <!-- Tablo alanı -->
                <div class="report-table">
                    <table>
                        <thead>
                            <?php if ($report_type == 'user_progress'): ?>
                                <tr>
                                    <th>Kullanıcı</th>
                                    <th>Tamamlanan Dersler</th>
                                    <th>Toplam Süre</th>
                                    <th>Ortalama Puan</th>
                                </tr>
                            <?php elseif ($report_type == 'course_engagement'): ?>
                                <tr>
                                    <th>Kurs</th>
                                    <th>Toplam Öğrenci</th>
                                    <th>Tamamlanan Dersler</th>
                                    <th>Ortalama Puan</th>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <th>Değerlendirme</th>
                                    <th>Toplam Deneme</th>
                                    <th>Ortalama Puan</th>
                                    <th>En Düşük/En Yüksek</th>
                                </tr>
                            <?php endif; ?>
                        </thead>
                        <tbody>
                            <?php while ($row = $report_data->fetch_assoc()): ?>
                                <?php if ($report_type == 'user_progress'): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo $row['completed_lessons']; ?></td>
                                        <td><?php echo round($row['total_time'] / 60, 1); ?> saat</td>
                                        <td><?php echo round($row['average_score'], 1); ?></td>
                                    </tr>
                                <?php elseif ($report_type == 'course_engagement'): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                                        <td><?php echo $row['total_students']; ?></td>
                                        <td><?php echo $row['completed_lessons']; ?></td>
                                        <td><?php echo round($row['average_score'], 1); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['assessment_name']); ?></td>
                                        <td><?php echo $row['total_attempts']; ?></td>
                                        <td><?php echo round($row['average_score'], 1); ?></td>
                                        <td><?php echo round($row['lowest_score'], 1); ?> / <?php echo round($row['highest_score'], 1); ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/admin_footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script src="../assets/js/admin/reports.js"></script>
</body>
</html>