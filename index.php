<?php
/**
 * Main Entry Point
 * จุดเริ่มต้นหลักของระบบ
 */

// เริ่ม session
session_start();

// ตรวจสอบว่าล็อกอินอยู่แล้วหรือไม่
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    // Redirect ไปยัง dashboard ตาม role
    switch ($_SESSION['user_role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'staff':
            header('Location: staff/dashboard.php');
            break;
        case 'approver':
            header('Location: approver/dashboard.php');
            break;
        case 'commander':
            header('Location: commander/dashboard.php');
            break;
        case 'user':
            header('Location: user/dashboard.php');
            break;
        default:
            header('Location: login.php');
            break;
    }
    exit();
} else {
    // Redirect ไปยังหน้าล็อกอิน
    header('Location: login.php');
    exit();
}
?>