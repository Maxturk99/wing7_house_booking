<?php
/**
 * Get Room Data for Edit
 */

session_start();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์ใช้งาน']);
    exit();
}

require_once '../config/database.php';
$db = getDatabaseConnection();

$id = $_GET['id'] ?? 0;

try {
    $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($room) {
        echo json_encode(['success' => true, 'data' => $room]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลห้องพัก']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>