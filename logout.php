<?php
/**
 * Logout Script
 * ออกจากระบบ
 */

session_start();

// ล้าง session ทั้งหมด
$_SESSION = array();

// ทำลาย session
session_destroy();

// ลบ session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect ไปหน้าล็อกอิน
header('Location: login.php?success=ออกจากระบบสำเร็จ');
exit();
?>