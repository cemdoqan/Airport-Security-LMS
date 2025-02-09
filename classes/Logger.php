<?php
class Logger {
    private $conn;
    private $logFile;
    private $logLevel;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->logFile = LOG_PATH . '/app.log';
        $this->logLevel = LOG_LEVEL;
        
        // Log dizinini oluştur
        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0777, true);
        }
    }

    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }

    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }

    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }

    public function debug($message, $context = []) {
        $this->log('DEBUG', $message, $context);
    }

    private function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $logMessage = "[$timestamp] [$level] $message $contextStr" . PHP_EOL;

        // Dosyaya yaz
        error_log($logMessage, 3, $this->logFile);

        // Veritabanına kaydet
        try {
            $stmt = $this->conn->prepare("INSERT INTO system_logs (level, message, context, created_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $level, $message, $contextStr, $timestamp);
            $stmt->execute();
        } catch (Exception $e) {
            // Veritabanına yazılamazsa sadece dosyaya yaz
            error_log("Database logging failed: " . $e->getMessage(), 3, $this->logFile);
        }
    }
}