<?php
/**
 * Database Configuration File
 * ไฟล์ตั้งค่าฐานข้อมูล MySQL
 */

class DatabaseConfig {
    // Database connection settings
    const DB_HOST = 'localhost';         // MySQL host
    const DB_NAME = 'wing7_guest_db';    // Database name
    const DB_USER = 'root';              // Database username
    const DB_PASS = '';                  // Database password (empty for XAMPP)
    const DB_CHARSET = 'utf8mb4';        // Character set
    
    // Optional: Development mode
    const DEBUG_MODE = true;             // Set to false in production
}

// Create connection function
function getDatabaseConnection() {
    try {
        $dsn = "mysql:host=" . DatabaseConfig::DB_HOST . ";dbname=" . DatabaseConfig::DB_NAME . ";charset=" . DatabaseConfig::DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, DatabaseConfig::DB_USER, DatabaseConfig::DB_PASS, $options);
        
        if (DatabaseConfig::DEBUG_MODE) {
            error_log("Database connection established successfully");
        }
        
        return $pdo;
    } catch (PDOException $e) {
        // Log error and show user-friendly message
        error_log("Database Connection Error: " . $e->getMessage());
        
        if (DatabaseConfig::DEBUG_MODE) {
            die("Database Connection Failed: " . $e->getMessage());
        } else {
            die("ขออภัย ระบบฐานข้อมูลขัดข้อง กรุณาลองใหม่ในภายหลัง");
        }
    }
}

// Test connection (for debugging)
function testDatabaseConnection() {
    try {
        $conn = getDatabaseConnection();
        echo "Database connection successful!";
        return true;
    } catch (Exception $e) {
        echo "Database connection failed: " . $e->getMessage();
        return false;
    }
}
?>