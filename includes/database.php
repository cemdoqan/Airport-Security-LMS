<?php
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    // Veritabanı bağlantısını al
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            );
        } catch(PDOException $e) {
            $error_message = "Veritabanı bağlantı hatası: " . $e->getMessage();
            error_log($error_message);
            
            if (ENVIRONMENT === 'development') {
                echo $error_message;
            } else {
                echo "Bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
            }
            exit;
        }

        return $this->conn;
    }

    // Veritabanı bağlantısını kapat
    public function closeConnection() {
        $this->conn = null;
    }

    // Basit sorgu çalıştırma
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Sorgu hatası: " . $e->getMessage());
            throw $e;
        }
    }

    // Tek satır döndürme
    public function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Veri çekme hatası: " . $e->getMessage());
            throw $e;
        }
    }

    // Tüm sonuçları döndürme
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Veri çekme hatası: " . $e->getMessage());
            throw $e;
        }
    }

    // Tek değer döndürme
    public function fetchColumn($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetchColumn();
        } catch(PDOException $e) {
            error_log("Veri çekme hatası: " . $e->getMessage());
            throw $e;
        }
    }

    // Insert işlemi
    public function insert($table, $data) {
        try {
            $fields = array_keys($data);
            $values = array_values($data);
            $placeholders = str_repeat('?,', count($fields) - 1) . '?';
            
            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)",
                $table,
                implode(', ', $fields),
                $placeholders
            );
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($values);
            
            return $this->conn->lastInsertId();
        } catch(PDOException $e) {
            error_log("Insert hatası: " . $e->getMessage());
            throw $e;
        }
    }

    // Update işlemi
    public function update($table, $data, $where, $whereParams = []) {
        try {
            $setParts = array_map(function($field) {
                return "$field = ?";
            }, array_keys($data));
            
            $sql = sprintf(
                "UPDATE %s SET %s WHERE %s",
                $table,
                implode(', ', $setParts),
                $where
            );
            
            $params = array_merge(array_values($data), $whereParams);
            $stmt = $this->conn->prepare($sql);
            
            return $stmt->execute($params);
        } catch(PDOException $e) {
            error_log("Update hatası: " . $e->getMessage());
            throw $e;
        }
    }

    // Delete işlemi
    public function delete($table, $where, $params = []) {
        try {
            $sql = sprintf("DELETE FROM %s WHERE %s", $table, $where);
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
        } catch(PDOException $e) {
            error_log("Delete hatası: " . $e->getMessage());
            throw $e;
        }
    }

    // Transaction başlat
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    // Transaction onayla
    public function commit() {
        return $this->conn->commit();
    }

    // Transaction geri al
    public function rollback() {
        return $this->conn->rollBack();
    }
}