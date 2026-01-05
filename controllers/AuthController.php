<?php
/**
 * Authentication Controller
 * ควบคุมการล็อกอิน ออกจากระบบ และจัดการเซสชัน
 */

class AuthController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new UserModel();
    }
    
    // Handle login
    public function login($username, $password) {
        // Validate input
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน'];
        }
        
        // Verify credentials
        $user = $this->userModel->verifyCredentials($username, $password);
        
        if ($user) {
            // Check if user is active
            if ($user['status'] !== 'active') {
                return ['success' => false, 'message' => 'บัญชีผู้ใช้ถูกระงับการใช้งาน'];
            }
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['department'] = $user['department'];
            $_SESSION['logged_in'] = true;
            
            // Update last login
            $this->userModel->updateLastLogin($user['id']);
            
            // Log login activity
            $this->logActivity($user['id'], 'login', 'users', $user['id'], null, json_encode($user));
            
            return [
                'success' => true,
                'message' => 'ล็อกอินสำเร็จ',
                'redirect' => $this->getDashboardUrl($user['role'])
            ];
        }
        
        return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
    }
    
    // Handle logout
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id']);
        }
        
        // Clear session
        session_unset();
        session_destroy();
        
        return ['success' => true, 'message' => 'ออกจากระบบสำเร็จ', 'redirect' => '/login.php'];
    }
    
    // Get dashboard URL based on role
    private function getDashboardUrl($role) {
        switch ($role) {
            case 'admin':
                return '/admin/dashboard.php';
            case 'staff':
                return '/staff/dashboard.php';
            case 'approver':
                return '/approver/dashboard.php';
            case 'commander':
                return '/commander/dashboard.php';
            case 'user':
                return '/user/dashboard.php';
            default:
                return '/';
        }
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    // Get current user info
    public function getCurrentUser() {
        if ($this->isLoggedIn() && isset($_SESSION['user_id'])) {
            return $this->userModel->getUserById($_SESSION['user_id']);
        }
        return null;
    }
    
    // Get current user role
    public function getCurrentUserRole() {
        return $_SESSION['user_role'] ?? null;
    }
    
    // Check permission
    public function hasPermission($requiredRole) {
        $userRole = $this->getCurrentUserRole();
        
        if (is_array($requiredRole)) {
            return in_array($userRole, $requiredRole);
        }
        
        return $userRole === $requiredRole;
    }
    
    // Change password
    public function changePassword($userId, $currentPassword, $newPassword) {
        $user = $this->userModel->getUserById($userId);
        
        if (!$user) {
            return ['success' => false, 'message' => 'ไม่พบผู้ใช้'];
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'];
        }
        
        // Update password
        if ($this->userModel->changePassword($userId, $newPassword)) {
            $this->logActivity($userId, 'change_password', 'users', $userId);
            return ['success' => true, 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ'];
        }
        
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน'];
    }
    
    // Log activity
    private function logActivity($userId, $action, $table = null, $recordId = null, $oldValue = null, $newValue = null) {
        $db = Database::getInstance();
        
        $sql = "INSERT INTO logs (user_id, action, table_name, record_id, old_value, new_value, ip_address, user_agent) 
                VALUES (:user_id, :action, :table_name, :record_id, :old_value, :new_value, :ip_address, :user_agent)";
        
        $params = [
            ':user_id' => $userId,
            ':action' => $action,
            ':table_name' => $table,
            ':record_id' => $recordId,
            ':old_value' => $oldValue,
            ':new_value' => $newValue,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        
        $db->query($sql, $params);
    }
}
?>