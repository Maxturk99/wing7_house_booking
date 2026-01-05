<?php
/**
 * Get Building Data for Edit
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
    $stmt = $db->prepare("SELECT * FROM buildings WHERE id = ?");
    $stmt->execute([$id]);
    $building = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($building) {
        echo json_encode(['success' => true, 'data' => $building]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลอาคาร']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>