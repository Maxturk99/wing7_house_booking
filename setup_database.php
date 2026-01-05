<?php
/**
 * Database Setup Script
 * สคริปต์สำหรับสร้างฐานข้อมูลเริ่มต้น
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'wing7_guest_db';

try {
    // สร้างการเชื่อมต่อ (ไม่ระบุ database name เพื่อสร้าง database)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // สร้างฐานข้อมูล
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("USE `$dbname`");
    
    echo "<h2>กำลังสร้างฐานข้อมูล...</h2>";
    
    // สร้างตาราง users
    $sql = "CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `full_name` varchar(100) NOT NULL,
        `email` varchar(100) DEFAULT NULL,
        `phone` varchar(20) DEFAULT NULL,
        `department` varchar(100) DEFAULT NULL,
        `rank` varchar(50) DEFAULT NULL,
        `role` enum('admin','staff','approver','commander','user') NOT NULL DEFAULT 'user',
        `line_token` varchar(255) DEFAULT NULL,
        `status` enum('active','inactive') NOT NULL DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "<p>✓ สร้างตาราง users สำเร็จ</p>";
    
    // สร้างตาราง buildings
    $sql = "CREATE TABLE IF NOT EXISTS `buildings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `building_code` varchar(20) NOT NULL,
        `building_name` varchar(100) NOT NULL,
        `building_type` enum('guest_house','accommodation') NOT NULL,
        `location` varchar(200) DEFAULT NULL,
        `total_rooms` int(11) NOT NULL DEFAULT 1,
        `max_occupancy` int(11) NOT NULL DEFAULT 1,
        `description` text DEFAULT NULL,
        `amenities` text DEFAULT NULL,
        `price_per_night` decimal(10,2) NOT NULL DEFAULT 0.00,
        `price_per_person` decimal(10,2) DEFAULT 0.00,
        `status` enum('active','maintenance','inactive') NOT NULL DEFAULT 'active',
        `created_by` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `building_code` (`building_code`),
        KEY `created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "<p>✓ สร้างตาราง buildings สำเร็จ</p>";
    
    // เพิ่มข้อมูลเริ่มต้น
    echo "<h3>กำลังเพิ่มข้อมูลเริ่มต้น...</h3>";
    
    // เพิ่มผู้ใช้เริ่มต้น
    $users = [
        ['admin', 'admin@wing7.local', 'System Administrator', '0812345678', 'Administration', 'Administrator', 'admin'],
        ['staff1', 'staff1@wing7.local', 'Staff User 1', '0823456789', 'Guest House', 'Staff', 'staff'],
        ['approver1', 'approver1@wing7.local', 'Approver User 1', '0834567890', 'Administration', 'Officer', 'approver'],
        ['commander1', 'commander1@wing7.local', 'Commander User 1', '0845678901', 'Command', 'Commander', 'commander'],
        ['user1', 'user1@wing7.local', 'General User 1', '0856789012', 'Operations', 'Officer', 'user']
    ];
    
    foreach ($users as $user) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, phone, department, rank, role, password) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user[0], $user[1], $user[2], $user[3], $user[4], $user[5], $user[6], password_hash('password', PASSWORD_DEFAULT)]);
        echo "<p>✓ เพิ่มผู้ใช้: {$user[0]} ({$user[2]})</p>";
    }
    
    // เพิ่มอาคารเริ่มต้น
    $buildings = [
        ['GH-001', 'บ้านพักรับรอง 1', 'guest_house', 'พื้นที่ A', 10, 20, 1500.00],
        ['GH-002', 'บ้านพักรับรอง 2', 'guest_house', 'พื้นที่ B', 8, 16, 1200.00],
        ['AC-001', 'อาคารที่พัก 1', 'accommodation', 'พื้นที่ C', 20, 40, 800.00],
        ['AC-002', 'อาคารที่พัก 2', 'accommodation', 'พื้นที่ D', 15, 30, 600.00]
    ];
    
    foreach ($buildings as $building) {
        $stmt = $pdo->prepare("INSERT INTO buildings (building_code, building_name, building_type, location, total_rooms, max_occupancy, price_per_night) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($building);
        echo "<p>✓ เพิ่มอาคาร: {$building[1]} ({$building[0]})</p>";
    }
    
    echo "<h2>การติดตั้งฐานข้อมูลสำเร็จ!</h2>";
    echo "<p>สามารถเข้าสู่ระบบด้วย:</p>";
    echo "<ul>";
    echo "<li>Admin: admin / password</li>";
    echo "<li>Staff: staff1 / password</li>";
    echo "<li>Approver: approver1 / password</li>";
    echo "<li>Commander: commander1 / password</li>";
    echo "<li>User: user1 / password</li>";
    echo "</ul>";
    echo "<p><a href='login.php'>คลิกที่นี่เพื่อเข้าสู่ระบบ</a></p>";
    
} catch (PDOException $e) {
    echo "<h2>เกิดข้อผิดพลาด!</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>ตรวจสอบการตั้งค่า MySQL:</p>";
    echo "<ul>";
    echo "<li>MySQL ต้องกำลังทำงานอยู่</li>";
    echo "<li>Username: root</li>";
    echo "<li>Password: (ว่างเปล่าสำหรับ XAMPP)</li>";
    echo "</ul>";
}
?>