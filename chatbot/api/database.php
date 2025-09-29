<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    private $connection;
    
    public function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function createSession($sessionId, $userIp = null, $userAgent = null) {
        try {
            $stmt = $this->connection->prepare("
                INSERT IGNORE INTO chat_sessions (session_id, user_ip, user_agent) 
                VALUES (?, ?, ?)
            ");
            return $stmt->execute([$sessionId, $userIp, $userAgent]);
        } catch (Exception $e) {
            error_log("Error creating session: " . $e->getMessage());
            return false;
        }
    }
    
    public function saveMessage($sessionId, $messageType, $message, $responseTime = null) {
        try {
            $stmt = $this->connection->prepare("
                INSERT INTO chat_messages (session_id, message_type, message, response_time_ms) 
                VALUES (?, ?, ?, ?)
            ");
            return $stmt->execute([$sessionId, $messageType, $message, $responseTime]);
        } catch (Exception $e) {
            error_log("Error saving message: " . $e->getMessage());
            return false;
        }
    }
    
    public function getChatHistory($sessionId, $limit = 10) {
        try {
            $stmt = $this->connection->prepare("
                SELECT message_type, message, created_at 
                FROM chat_messages 
                WHERE session_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$sessionId, $limit]);
            return array_reverse($stmt->fetchAll());
        } catch (Exception $e) {
            error_log("Error getting chat history: " . $e->getMessage());
            return [];
        }
    }
    
    public function getMessageCount($sessionId) {
        try {
            $stmt = $this->connection->prepare("
                SELECT COUNT(*) as count 
                FROM chat_messages 
                WHERE session_id = ?
            ");
            $stmt->execute([$sessionId]);
            return $stmt->fetch()['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}
?>