<?php
require_once __DIR__ . '/../config/database.php';

class Course {
    private $conn;
    private $table_name = "courses";

    public $id;
    public $title;
    public $description;
    public $content;
    public $created;
    public $image_url;
    public $duration;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    title=:title,
                    description=:description,
                    content=:content,
                    image_url=:image_url,
                    duration=:duration,
                    created=:created";

        try {
            $stmt = $this->conn->prepare($query);

            // Temizlik zamanı! 🧹
            $this->title = htmlspecialchars(strip_tags($this->title));
            $this->description = htmlspecialchars(strip_tags($this->description));
            $this->content = htmlspecialchars(strip_tags($this->content));
            $this->image_url = htmlspecialchars(strip_tags($this->image_url));
            $this->duration = htmlspecialchars(strip_tags($this->duration));

            $stmt->bindParam(":title", $this->title);
            $stmt->bindParam(":description", $this->description);
            $stmt->bindParam(":content", $this->content);
            $stmt->bindParam(":image_url", $this->image_url);
            $stmt->bindParam(":duration", $this->duration);
            $stmt->bindParam(":created", $this->created);

            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Course Creation Error: " . $e->getMessage());
            throw new Exception("Kurs oluştururken bir hata oldu! Belki de kurs çok heyecanlandı! 🎢");
        }
    }

    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY created DESC";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt;
        } catch(PDOException $e) {
            error_log("Course Reading Error: " . $e->getMessage());
            throw new Exception("Kursları okurken bir sorun oluştu! Kurslar tatile mi çıktı? 🏖️");
        }
    }

    // Tek bir kursu getir - çünkü bazen özel ilgi isteriz! 🎯
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $this->id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Single Course Reading Error: " . $e->getMessage());
            throw new Exception("Bu kursu bulmakta zorluk çekiyoruz! Saklambaç mı oynuyor? 🙈");
        }
    }
}
?>