<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $username;
    public $password;
    public $email;
    public $role;
    public $created;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    username=:username,
                    password=:password,
                    email=:email,
                    role=:role,
                    created=:created";

        $stmt = $this->conn->prepare($query);

        // Değerleri temizleyelim, mikroplar gitsin! 🧼
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->password = htmlspecialchars(strip_tags($this->password));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->role = htmlspecialchars(strip_tags($this->role));

        // Şifreyi güvenli hale getirelim, hackerlar ağlasın! 😈
        $password_hash = password_hash($this->password, PASSWORD_BCRYPT);

        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":password", $password_hash);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":created", $this->created);

        try {
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("User Creation Error: " . $e->getMessage());
            throw new Exception("Kullanıcı oluşturulurken bir hata oldu! Belki de uzaylılar karışmıştır! 👽");
        }
    }

    public function login($username, $password) {
        $query = "SELECT id, username, password, role, email
                FROM " . $this->table_name . "
                WHERE username = ?
                LIMIT 0,1";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $username);
            $stmt->execute();
            
            if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if(password_verify($password, $row['password'])) {
                    return $row;
                }
            }
            return false;
        } catch(PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            throw new Exception("Giriş yaparken bir sorun oluştu! Şifrenizi unutmadınız, değil mi? 🤔");
        }
    }

    // Kullanıcı bilgilerini güncelleme - çünkü insanlar değişir! 🦋
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET
                    email = :email,
                    role = :role
                WHERE id = :id";

        try {
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':role', $this->role);
            $stmt->bindParam(':id', $this->id);

            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("User Update Error: " . $e->getMessage());
            throw new Exception("Güncelleme yapılamadı! Sunucu biraz utangaç bugün! 🙈");
        }
    }
}
?>