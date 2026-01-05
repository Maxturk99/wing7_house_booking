<?php
/**
 * Delete Logo Script
 * ลบไฟล์โลโก้
 */

session_start();

// ตรวจสอบสิทธิ์ - เฉพาะ admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '无权访问']);
    exit();
}

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logo_filename = $_POST['logo_filename'] ?? '';
    
    if (empty($logo_filename)) {
        echo json_encode(['success' => false, 'message' => 'ไม่มีชื่อไฟล์โลโก้']);
        exit();
    }
    
    // ตรวจสอบว่าไฟล์เป็นโลโก้จริงๆ
    if (strpos($logo_filename, 'logo_') !== 0) {
        echo json_encode(['success' => false, 'message' => 'ไฟล์นี้ไม่ใช่โลโก้']);
        exit();
    }
    
    $uploadDir = dirname(__DIR__) . '/assets/images/';
    $logoPath = $uploadDir . $logo_filename;
    
    if (file_exists($logoPath)) {
        // ลบไฟล์
        if (unlink($logoPath)) {
            // อัปเดตฐานข้อมูลให้เป็นโลโก้เริ่มต้น
            try {
                $db = getDatabaseConnection();
                $stmt = $db->prepare("UPDATE system_settings SET setting_value = 'logo.png' WHERE setting_key = 'system_logo'");
                $stmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'ลบโลโก้สำเร็จ']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'ลบไฟล์สำเร็จ แต่ไม่สามารถอัปเดตฐานข้อมูล: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบไฟล์โลโก้']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบไฟล์โลโก้']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>