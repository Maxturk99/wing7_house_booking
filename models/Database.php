<?php
/**
 * Database Model Class
 * คลาสสำหรับจัดการการเชื่อมต่อฐานข้อมูล
 */

class Database {
    private $connection;
    private static $instance = null;
    
    // Private constructor to prevent direct instantiation
    private function __construct() {
        $this->connect();
    }
    
    // Get singleton instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    // Connect to database
    private function connect() {
        try {
            $this->connection = getDatabaseConnection();
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    // Get connection
    public function getConnection() {
        return $this->connection;
    }
    
    // Execute query
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            return false;
        }
    }
    
    // Fetch single row
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        if ($stmt) {
            return $stmt->fetch();
        }
        return false;
    }
    
    // Fetch all rows
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        if ($stmt) {
            return $stmt->fetchAll();
        }
        return false;
    }
    
    // Get last insert ID
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    // Begin transaction
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    // Commit transaction
    public function commit() {
        return $this->connection->commit();
    }
    
    // Rollback transaction
    public function rollback() {
        return $this->connection->rollback();
    }
    
    // Check if table exists
    public function tableExists($tableName) {
        $sql = "SHOW TABLES LIKE :table";
        $stmt = $this->query($sql, [':table' => $tableName]);
        return $stmt && $stmt->rowCount() > 0;
    }
}
?>