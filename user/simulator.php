<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

// Mevcut simülasyonları getir
$simulations_query = "
    SELECT s.*, 
           c.title as course_title,
           us.completion_status,
           us.last_attempt_date,
           us.best_score
    FROM simulations s
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN user_simulations us ON s.id = us.simulation_id AND us.user_id = ?
    WHERE s.status = 'active'
    ORDER BY s.sort_order ASC";

$stmt = $conn->prepare($simulations_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$simulations = $stmt->get_result();

// Kullanıcının erişebileceği simülasyon kategorilerini getir
$categories_query = "
    SELECT DISTINCT s.category 
    FROM simulations s
    INNER JOIN course_simulations cs ON s.id = cs.simulation_id
    INNER JOIN user_courses uc ON cs.course_id = uc.course_id
    WHERE uc.user_id = ?
    ORDER BY s.category";

$stmt = $conn->prepare($categories_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simülasyonlar</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/simulator.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="simulator-container">
        <section class="simulator-header">
            <h1>Eğitim Simülasyonları</h1>
            
            <!-- Filtreleme -->
            <div class="simulator-filters">
                <div class="filter-group">
                    <label for="categoryFilter">Kategori:</label>
                    <select id="categoryFilter">
                        <option value="">Tümü</option>
                        <?php while ($category = $categories->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($category['category']); ?>">
                                <?php echo htmlspecialchars($category['category']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="statusFilter">Durum:</label>
                    <select id="statusFilter">
                        <option value="">Tümü</option>
                        <option value="completed">Tamamlanan</option>
                        <option value="in_progress">Devam Eden</option>
                        <option value="not_started">Başlanmamış</option>
                    </select>
                </div>
                
                <div class="search-group">
                    <input type="text" id="searchSimulation" placeholder="Simülasyon ara...">
                </div>
            </div>
        </section>

        <!-- Simülasyon Listesi -->
        <section class="simulations-grid">
            <?php while ($sim = $simulations->fetch_assoc()): ?>
                <div class="simulation-card" 
                     data-category="<?php echo htmlspecialchars($sim['category']); ?>"
                     data-status="<?php echo $sim['completion_status'] ?? 'not_started'; ?>">
                    
                    <div class="simulation-preview">
                        <img src="<?php echo htmlspecialchars($sim['preview_image']); ?>" 
                             alt="<?php echo htmlspecialchars($sim['title']); ?>">
                        <?php if ($sim['completion_status'] == 'completed'): ?>
                            <div class="completion-badge">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="simulation-info">
                        <div class="simulation-header">
                            <h3><?php echo htmlspecialchars($sim['title']); ?></h3>
                            <span class="difficulty-badge <?php echo strtolower($sim['difficulty']); ?>">
                                <?php echo htmlspecialchars($sim['difficulty']); ?>
                            </span>
                        </div>
                        
                        <p class="simulation-description">
                            <?php echo htmlspecialchars($sim['description']); ?>
                        </p>
                        
                        <div class="simulation-meta">
                            <span>
                                <i class="fas fa-book"></i>
                                <?php echo htmlspecialchars($sim['course_title']); ?>
                            </span>
                            <span>
                                <i class="fas fa-clock"></i>
                                <?php echo $sim['estimated_duration']; ?> dakika
                            </span>
                        </div>
                        
                        <?php if ($sim['completion_status'] == 'completed'): ?>
                            <div class="score-info">
                                <span class="best-score">En İyi Skor: %<?php echo $sim['best_score']; ?></span>
                                <small>Son Deneme: <?php echo date('d.m.Y', strtotime($sim['last_attempt_date'])); ?></small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="simulation-actions">
                            <button class="btn-primary start-simulation" 
                                    data-simulation-id="<?php echo $sim['id']; ?>">
                                <?php echo $sim['completion_status'] == 'completed' ? 'Tekrar Dene' : 'Başla'; ?>
                            </button>
                            
                            <?php if ($sim['has_tutorial']): ?>
                                <button class="btn-secondary view-tutorial" 
                                        data-simulation-id="<?php echo $sim['id']; ?>">
                                    <i class="fas fa-play-circle"></i> Eğitim
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </section>

        <!-- Simülasyon Modal -->
        <div id="simulationModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="simulationTitle"></h2>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="simulationContainer"></div>
                </div>
            </div>
        </div>

        <!-- Tutorial Modal -->
        <div id="tutorialModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Simülasyon Eğitimi</h2>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="tutorialContainer"></div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script src="../assets/js/simulator.js"></script>
</body>
</html>