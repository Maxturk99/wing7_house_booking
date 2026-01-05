<?php
/**
 * Main Configuration File
 * ไฟล์ตั้งค่าหลักของระบบ
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone setting
date_default_timezone_set('Asia/Bangkok');

// Application paths
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('APP_PATH', ROOT_PATH);
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOAD_PATH', ROOT_PATH . '/assets/images/uploads');

// Application settings
class AppConfig {
    // Application info
    const APP_NAME = 'ระบบจองบ้านพักรับรอง กองบิน7';
    const APP_VERSION = '1.0.0';
    const APP_AUTHOR = 'กองบิน 7';
    
    // Security
    const SESSION_TIMEOUT = 3600; // 1 hour in seconds
    const CSRF_TOKEN_LENGTH = 32;
    
    // Upload settings
    const MAX_FILE_SIZE = 5242880; // 5MB
    const ALLOWED_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif'];
    
    // Pagination
    const ITEMS_PER_PAGE = 10;
    
    // Booking settings
    const MAX_BOOKING_DAYS = 30;
    const CHECKIN_TIME = '14:00:00';
    const CHECKOUT_TIME = '12:00:00';
}

// Helper functions
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(AppConfig::CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

function requireRole($requiredRole) {
    requireLogin();
    
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $requiredRole) {
        if (is_array($requiredRole)) {
            if (!in_array($_SESSION['user_role'], $requiredRole)) {
                header('Location: /unauthorized.php');
                exit();
            }
        } else {
            if ($_SESSION['user_role'] !== $requiredRole) {
                header('Location: /unauthorized.php');
                exit();
            }
        }
    }
}

// Auto-load classes
spl_autoload_register(function ($className) {
    $directories = [
        ROOT_PATH . '/models/',
        ROOT_PATH . '/controllers/',
        ROOT_PATH . '/includes/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Include required files
require_once 'database.php';
?>