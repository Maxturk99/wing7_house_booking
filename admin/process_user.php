<?php
session_start();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit();
}

require_once '../config/database.php';
$db = getDatabaseConnection();

$action = $_POST['action'] ?? '';

// ฟังก์ชันส่งผลลัพธ์เป็น JSON
function sendResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

switch ($action) {
    case 'add_user':
        // เพิ่มผู้ใช้ใหม่
        try {
            $required = ['username', 'password', 'full_name', 'role'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    sendResponse(false, "กรุณากรอกข้อมูลให้ครบ: " . $field);
                }
            }
            
            // ตรวจสอบว่าชื่อผู้ใช้ซ้ำหรือไม่
            $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->execute([$_POST['username']]);
            if ($checkStmt->fetch()) {
                sendResponse(false, "ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว");
            }
            
            // ตรวจสอบความยาวรหัสผ่าน
            if (strlen($_POST['password']) < 6) {
                sendResponse(false, "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร");
            }
            
            // เข้ารหัสรหัสผ่าน
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            // เพิ่มผู้ใช้ใหม่
            $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, phone, department, rank, role, line_token, status, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $result = $stmt->execute([
                $_POST['username'],
                $hashedPassword,
                $_POST['full_name'],
                $_POST['email'] ?? '',
                $_POST['phone'] ?? '',
                $_POST['department'] ?? '',
                $_POST['rank'] ?? '',
                $_POST['role'],
                $_POST['line_token'] ?? '',
                $_POST['status'] ?? 'active'
            ]);
            
            if ($result) {
                sendResponse(true, "เพิ่มผู้ใช้ใหม่สำเร็จ");
            } else {
                sendResponse(false, "เกิดข้อผิดพลาดในการเพิ่มผู้ใช้");
            }
            
        } catch (PDOException $e) {
            sendResponse(false, "ข้อผิดพลาดฐานข้อมูล: " . $e->getMessage());
        }
        break;
        
    case 'edit_user':
        // แก้ไขข้อมูลผู้ใช้
        try {
            if (empty($_POST['id'])) {
                sendResponse(false, "ไม่พบ ID ผู้ใช้");
            }
            
            // ตรวจสอบว่าไม่แก้ไขตัวเองให้ไม่ใช่ admin
            if ($_POST['id'] == $_SESSION['user_id'] && $_POST['role'] !== 'admin') {
                sendResponse(false, "ไม่สามารถเปลี่ยนบทบาทของตัวเองได้");
            }
            
            // ตรวจสอบว่าชื่อผู้ใช้ซ้ำหรือไม่ (ยกเว้นตัวเอง)
            $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $checkStmt->execute([$_POST['username'], $_POST['id']]);
            if ($checkStmt->fetch()) {
                sendResponse(false, "ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว");
            }
            
            // อัพเดทข้อมูล
            $stmt = $db->prepare("UPDATE users SET 
                username = ?,
                full_name = ?,
                email = ?,
                phone = ?,
                department = ?,
                rank = ?,
                role = ?,
                line_token = ?,
                status = ?,
                updated_at = NOW()
                WHERE id = ?");
            
            $result = $stmt->execute([
                $_POST['username'],
                $_POST['full_name'],
                $_POST['email'] ?? '',
                $_POST['phone'] ?? '',
                $_POST['department'] ?? '',
                $_POST['rank'] ?? '',
                $_POST['role'],
                $_POST['line_token'] ?? '',
                $_POST['status'] ?? 'active',
                $_POST['id']
            ]);
            
            if ($result) {
                sendResponse(true, "อัพเดทข้อมูลผู้ใช้สำเร็จ");
            } else {
                sendResponse(false, "เกิดข้อผิดพลาดในการอัพเดทข้อมูล");
            }
            
        } catch (PDOException $e) {
            sendResponse(false, "ข้อผิดพลาดฐานข้อมูล: " . $e->getMessage());
        }
        break;
        
    case 'change_password':
        // เปลี่ยนรหัสผ่าน
        try {
            if (empty($_POST['id']) || empty($_POST['new_password'])) {
                sendResponse(false, "กรุณากรอกข้อมูลให้ครบ");
            }
            
            // ตรวจสอบความยาวรหัสผ่าน
            if (strlen($_POST['new_password']) < 6) {
                sendResponse(false, "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร");
            }
            
            // เข้ารหัสรหัสผ่าน
            $hashedPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            
            // อัพเดทรหัสผ่าน
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$hashedPassword, $_POST['id']]);
            
            if ($result) {
                sendResponse(true, "เปลี่ยนรหัสผ่านสำเร็จ");
            } else {
                sendResponse(false, "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน");
            }
            
        } catch (PDOException $e) {
            sendResponse(false, "ข้อผิดพลาดฐานข้อมูล: " . $e->getMessage());
        }
        break;
        
    case 'delete_user':
        // ลบผู้ใช้
        try {
            if (empty($_POST['id'])) {
                sendResponse(false, "ไม่พบ ID ผู้ใช้");
            }
            
            $userId = $_POST['id'];
            
            // ตรวจสอบว่าไม่ลบตัวเอง
            if ($userId == $_SESSION['user_id']) {
                sendResponse(false, "ไม่สามารถลบบัญชีของตัวเองได้");
            }
            
            // ตรวจสอบว่าผู้ใช้เป็น admin หรือไม่ (ป้องกันลบ admin อื่น)
            $checkStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
            $checkStmt->execute([$userId]);
            $user = $checkStmt->fetch();
            
            if ($user && $user['role'] === 'admin') {
                sendResponse(false, "ไม่สามารถลบผู้ดูแลระบบได้");
            }
            
            // ลบผู้ใช้
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $result = $stmt->execute([$userId]);
            
            if ($result) {
                sendResponse(true, "ลบผู้ใช้สำเร็จ");
            } else {
                sendResponse(false, "เกิดข้อผิดพลาดในการลบผู้ใช้");
            }
            
        } catch (PDOException $e) {
            sendResponse(false, "ข้อผิดพลาดฐานข้อมูล: " . $e->getMessage());
        }
        break;
        
    default:
        sendResponse(false, "Action ไม่ถูกต้อง");
}
?>