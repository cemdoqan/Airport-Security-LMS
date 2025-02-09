<?php
class Simulation {
    private $conn;
    private $table_name = "xray_simulations";

    public $id;
    public $title;
    public $description;
    public $image_path;
    public $difficulty_level;
    public $time_limit;
    public $passing_score;
    public $threat_objects;
    public $created_by;
    public $status;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Simülasyon oluştur
    public function create() {
        try {
            $query = "INSERT INTO " . $this->table_name . "
                    SET
                        title = :title,
                        description = :description,
                        image_path = :image_path,
                        difficulty_level = :difficulty_level,
                        time_limit = :time_limit,
                        passing_score = :passing_score,
                        threat_objects = :threat_objects,
                        created_by = :created_by,
                        status = :status,
                        created_at = NOW()";

            $stmt = $this->conn->prepare($query);

            // Verileri temizle
            $this->title = clean_input($this->title);
            $this->description = clean_input($this->description);

            // Parametreleri bağla
            $params = [
                ":title" => $this->title,
                ":description" => $this->description,
                ":image_path" => $this->image_path,
                ":difficulty_level" => $this->difficulty_level,
                ":time_limit" => $this->time_limit,
                ":passing_score" => $this->passing_score,
                ":threat_objects" => json_encode($this->threat_objects),
                ":created_by" => $this->created_by,
                ":status" => $this->status
            ];

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Simulation creation error: " . $e->getMessage());
            throw $e;
        }
    }

    // Simülasyon güncelle
    public function update() {
        try {
            $query = "UPDATE " . $this->table_name . "
                    SET
                        title = :title,
                        description = :description,
                        image_path = :image_path,
                        difficulty_level = :difficulty_level,
                        time_limit = :time_limit,
                        passing_score = :passing_score,
                        threat_objects = :threat_objects,
                        status = :status
                    WHERE id = :id";

            $stmt = $this->conn->prepare($query);

            // Parametreleri bağla
            $params = [
                ":title" => clean_input($this->title),
                ":description" => clean_input($this->description),
                ":image_path" => $this->image_path,
                ":difficulty_level" => $this->difficulty_level,
                ":time_limit" => $this->time_limit,
                ":passing_score" => $this->passing_score,
                ":threat_objects" => json_encode($this->threat_objects),
                ":status" => $this->status,
                ":id" => $this->id
            ];

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Simulation update error: " . $e->getMessage());
            throw $e;
        }
    }

    // Simülasyon deneme sonucu kaydet
    public function saveAttempt($user_id, $attempt_data) {
        try {
            $query = "INSERT INTO simulation_attempts (
                        user_id, simulation_id, score, time_taken,
                        correct_detections, false_positives, missed_threats,
                        marked_positions, passed, attempted_at
                    ) VALUES (
                        :user_id, :simulation_id, :score, :time_taken,
                        :correct_detections, :false_positives, :missed_threats,
                        :marked_positions, :passed, NOW()
                    )";

            $stmt = $this->conn->prepare($query);

            // Sonucu hesapla
            $passed = ($attempt_data['score'] >= $this->passing_score);

            // Parametreleri bağla
            $params = [
                ":user_id" => $user_id,
                ":simulation_id" => $this->id,
                ":score" => $attempt_data['score'],
                ":time_taken" => $attempt_data['time_taken'],
                ":correct_detections" => $attempt_data['correct_detections'],
                ":false_positives" => $attempt_data['false_positives'],
                ":missed_threats" => $attempt_data['missed_threats'],
                ":marked_positions" => json_encode($attempt_data['marked_positions']),
                ":passed" => $passed
            ];

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Attempt save error: " . $e->getMessage());
            throw $e;
        }
    }

    // Simülasyonu sil
    public function delete() {
        try {
            $this->conn->beginTransaction();

            // Denemeleri sil
            $query = "DELETE FROM simulation_attempts WHERE simulation_id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $this->id);
            $stmt->execute();

            // Simülasyonu sil
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $this->id);
            $result = $stmt->execute();

            // X-ray görüntüsünü sil
            if ($result && !empty($this->image_path)) {
                $image_file = XRAY_PATH . $this->image_path;
                if (file_exists($image_file)) {
                    unlink($image_file);
                }
            }

            $this->conn->commit();
            return $result;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Simulation deletion error: " . $e->getMessage());
            throw $e;
        }
    }

    // Tek simülasyon getir
    public function getById($id) {
        try {
            $query = "SELECT s.*, u.username as created_by_name,
                            COUNT(sa.id) as total_attempts,
                            AVG(sa.score) as avg_score
                     FROM " . $this->table_name . " s
                     LEFT JOIN users u ON s.created_by = u.id
                     LEFT JOIN simulation_attempts sa ON s.id = sa.simulation_id
                     WHERE s.id = :id
                     GROUP BY s.id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Simulation fetch error: " . $e->getMessage());
            throw $e;
        }
    }

    // Rastgele simülasyon getir
    public function getRandom($difficulty_level = null) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE status = 'active'";

            if ($difficulty_level) {
                $query .= " AND difficulty_level = :difficulty_level";
            }

            $query .= " ORDER BY RAND() LIMIT 1";

            $stmt = $this->conn->prepare($query);

            if ($difficulty_level) {
                $stmt->bindParam(":difficulty_level", $difficulty_level);
            }

            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Random simulation fetch error: " . $e->getMessage());
            throw $e;
        }
    }

    // Simülasyon listesi
    public function getAll($start = 0, $limit = 10, $filters = []) {
        try {
            $query = "SELECT s.*, u.username as created_by_name,
                            COUNT(sa.id) as total_attempts,
                            AVG(sa.score) as avg_score,
                            COUNT(CASE WHEN sa.passed = 1 THEN 1 END) as passed_count
                     FROM " . $this->table_name . " s
                     LEFT JOIN users u ON s.created_by = u.id
                     LEFT JOIN simulation_attempts sa ON s.id = sa.simulation_id";

            $conditions = [];
            $params = [];

            if (!empty($filters['difficulty_level'])) {
                $conditions[] = "s.difficulty_level = :difficulty_level";
                $params[':difficulty_level'] = $filters['difficulty_level'];
            }

            if (!empty($filters['status'])) {
                $conditions[] = "s.status = :status";
                $params[':status'] = $filters['status'];
            }

            if (!empty($filters['search'])) {
                $conditions[] = "(s.title LIKE :search OR s.description LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }

            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }

            $query .= " GROUP BY s.id
                       ORDER BY s.created_at DESC
                       LIMIT :start, :limit";

            $stmt = $this->conn->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->bindValue(":start", $start, PDO::PARAM_INT);
            $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Simulation list error: " . $e->getMessage());
            throw $e;
        }
    }

    // Kullanıcının denemeleri
    public function getUserAttempts($user_id, $limit = 10) {
        try {
            $query = "SELECT sa.*, s.title, s.difficulty_level
                     FROM simulation_attempts sa
                     JOIN " . $this->table_name . " s ON sa.simulation_id = s.id
                     WHERE sa.user_id = :user_id
                     ORDER BY sa.attempted_at DESC
                     LIMIT :limit";

            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("User attempts fetch error: " . $e->getMessage());
            throw $e;
        }
    }

    // Kullanıcının performans istatistikleri
    public function getUserStats($user_id) {
        try {
            $query = "SELECT 
                        COUNT(*) as total_attempts,
                        AVG(score) as avg_score,
                        COUNT(CASE WHEN passed = 1 THEN 1 END) as passed_count,
                        AVG(time_taken) as avg_time,
                        MIN(score) as min_score,
                        MAX(score) as max_score
                     FROM simulation_attempts
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
}