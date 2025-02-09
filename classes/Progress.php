<?php
class Progress {
    private $conn;
    private $table_name = "user_progress";

    public $id;
    public $user_id;
    public $course_id;
    public $module_id;
    public $status;
    public $progress_percentage;
    public $last_accessed;
    public $completed_at;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // İlerleme kaydı oluştur veya güncelle
    public function saveProgress() {
        try {
            $query = "INSERT INTO " . $this->table_name . " (
                        user_id, course_id, module_id, status,
                        progress_percentage, last_accessed
                    ) VALUES (
                        :user_id, :course_id, :module_id, :status,
                        :progress_percentage, NOW()
                    ) ON DUPLICATE KEY UPDATE
                        status = :status,
                        progress_percentage = :progress_percentage,
                        last_accessed = NOW(),
                        completed_at = CASE 
                            WHEN :status = 'completed' AND status != 'completed'
                            THEN NOW()
                            ELSE completed_at
                        END";

            $stmt = $this->conn->prepare($query);

            // Parametreleri bağla
            $params = [
                ":user_id" => $this->user_id,
                ":course_id" => $this->course_id,
                ":module_id" => $this->module_id,
                ":status" => $this->status,
                ":progress_percentage" => $this->progress_percentage
            ];

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Progress save error: " . $e->getMessage());
            throw $e;
        }
    }

    // Kullanıcının kurs ilerlemesini getir
    public function getCourseProgress($user_id, $course_id) {
        try {
            $query = "SELECT up.*, cm.title as module_title, cm.type as module_type
                     FROM " . $this->table_name . " up
                     JOIN course_modules cm ON up.module_id = cm.id
                     WHERE up.user_id = :user_id 
                     AND up.course_id = :course_id
                     ORDER BY cm.order_number";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":course_id", $course_id);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Course progress fetch error: " . $e->getMessage());
            throw $e;
        }
    }

    // Kullanıcının tüm ilerlemelerini getir
    public function getUserProgress($user_id) {
        try {
            $query = "SELECT 
                        c.title as course_title,
                        c.category,
                        COUNT(DISTINCT cm.id) as total_modules,
                        COUNT(DISTINCT CASE WHEN up.status = 'completed' THEN up.module_id END) as completed_modules,
                        MAX(up.last_accessed) as last_accessed,
                        CASE 
                            WHEN COUNT(DISTINCT cm.id) = COUNT(DISTINCT CASE WHEN up.status = 'completed' THEN up.module_id END)
                            THEN 'completed'
                            WHEN COUNT(DISTINCT CASE WHEN up.status IN ('in_progress', 'completed') THEN up.module_id END) > 0
                            THEN 'in_progress'
                            ELSE 'not_started'
                        END as overall_status,
                        ROUND(
                            COUNT(DISTINCT CASE WHEN up.status = 'completed' THEN up.module_id END) * 100.0 / 
                            COUNT(DISTINCT cm.id)
                        ) as overall_percentage
                     FROM courses c
                     JOIN course_modules cm ON c.id = cm.course_id
                     LEFT JOIN " . $this->table_name . " up ON c.id = up.course_id 
                        AND up.user_id = :user_id
                     GROUP BY c.id
                     ORDER BY MAX(up.last_accessed) DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("User progress fetch error: " . $e->getMessage());
            throw $e;
        }
    }

    // Kullanıcının eğitim istatistiklerini getir
    public function getUserStats($user_id) {
        try {
            $query = "SELECT
                        COUNT(DISTINCT course_id) as total_courses,
                        COUNT(DISTINCT CASE WHEN status = 'completed' THEN module_id END) as completed_modules,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as total_completed_activities,
                        MAX(last_accessed) as last_activity,
                        (
                            SELECT COUNT(*)
                            FROM certificates 
                            WHERE user_id = :user_id AND status = 'active'
                        ) as active_certificates
                     FROM " . $this->table_name . "
                     WHERE user_id = :user_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("User stats fetch error: " . $e->getMessage());
            throw $e;
        }
    }

    // Eğitim tamamlama durumunu kontrol et
    public function checkCourseCompletion($user_id, $course_id) {
        try {
            $query = "SELECT 
                        COUNT(DISTINCT cm.id) as total_modules,
                        COUNT(DISTINCT CASE WHEN up.status = 'completed' THEN up.module_id END) as completed_modules
                     FROM course_modules cm
                     LEFT JOIN " . $this->table_name . " up ON cm.id = up.module_id 
                        AND up.user_id = :user_id
                     WHERE cm.course_id = :course_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":course_id", $course_id);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'completed' => $result['total_modules'] == $result['completed_modules'],
                'total_modules' => $result['total_modules'],
                'completed_modules' => $result['completed_modules'],
                'percentage' => $result['total_modules'] > 0 ? 
                    ($result['completed_modules'] / $result['total_modules'] * 100) : 0
            ];
        } catch (Exception $e) {
            error_log("Course completion check error: " . $e->getMessage());
            throw $e;
        }
    }

    // İlerleme raporunu getir
    public function getProgressReport($user_id, $course_id) {
        try {
            $query = "SELECT 
                        up.*,
                        cm.title as module_title,
                        cm.type as module_type,
                        TIMESTAMPDIFF(MINUTE, up.created_at, up.completed_at) as completion_time
                     FROM " . $this->table_name . " up
                     JOIN course_modules cm ON up.module_id = cm.id
                     WHERE up.user_id = :user_id 
                     AND up.course_id = :course_id
                     ORDER BY cm.order_number";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":course_id", $course_id);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Progress report fetch error: " . $e->getMessage());
            throw $e;
        }
    }

    // Son aktiviteleri getir
    public function getRecentActivities($user_id, $limit = 10) {
        try {
            $query = "SELECT up.*, c.title as course_title, cm.title as module_title
                     FROM " . $this->table_name . " up
                     JOIN courses c ON up.course_id = c.id
                     JOIN course_modules cm ON up.module_id = cm.id
                     WHERE up.user_id = :user_id
                     ORDER BY up.last_accessed DESC
                     LIMIT :limit";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Recent activities fetch error: " . $e->getMessage());
            throw $e;
        }
    }

    // İlerleme kaydını sıfırla
    public function resetProgress($user_id, $course_id = null) {
        try {
            $query = "DELETE FROM " . $this->table_name . " 
                     WHERE user_id = :user_id";
            
            if ($course_id) {
                $query .= " AND course_id = :course_id";
            }

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            
            if ($course_id) {
                $stmt->bindParam(":course_id", $course_id);
            }

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Progress reset error: " . $e->getMessage());
            throw $e;
        }
    }
}