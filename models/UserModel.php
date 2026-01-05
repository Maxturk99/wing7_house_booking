<?php
/**
 * User Model Class
 * คลาสสำหรับจัดการข้อมูลผู้ใช้
 */

class UserModel {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Get user by ID
    public function getUserById($id) {
        $sql = "SELECT * FROM users WHERE id = :id";
        return $this->db->fetch($sql, [':id' => $id]);
    }
    
    // Get user by username
    public function getUserByUsername($username) {
        $sql = "SELECT * FROM users WHERE username = :username";
        return $this->db->fetch($sql, [':username' => $username]);
    }
    
    // Get user by email
    public function getUserByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = :email";
        return $this->db->fetch($sql, [':email' => $email]);
    }
    
    // Verify user credentials
    public function verifyCredentials($username, $password) {
        $user = $this->getUserByUsername($username);
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }
    
    // Create new user
    public function createUser($data) {
        $sql = "INSERT INTO users (username, password, full_name, email, phone, department, rank, role, status) 
                VALUES (:username, :password, :full_name, :email, :phone, :department, :rank, :role, :status)";
        
        $params = [
            ':username' => $data['username'],
            ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':full_name' => $data['full_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':department' => $data['department'],
            ':rank' => $data['rank'],
            ':role' => $data['role'],
            ':status' => $data['status'] ?? 'active'
        ];
        
        return $this->db->query($sql, $params) !== false;
    }
    
    // Update user
    public function updateUser($id, $data) {
        $sql = "UPDATE users SET 
                full_name = :full_name,
                email = :email,
                phone = :phone,
                department = :department,
                rank = :rank,
                role = :role,
                status = :status,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        
        $params = [
            ':id' => $id,
            ':full_name' => $data['full_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':department' => $data['department'],
            ':rank' => $data['rank'],
            ':role' => $data['role'],
            ':status' => $data['status'] ?? 'active'
        ];
        
        return $this->db->query($sql, $params) !== false;
    }
    
    // Delete user
    public function deleteUser($id) {
        $sql = "DELETE FROM users WHERE id = :id AND role != 'admin'";
        return $this->db->query($sql, [':id' => $id]) !== false;
    }
    
    // Get all users
    public function getAllUsers($filters = []) {
        $sql = "SELECT * FROM users WHERE 1=1";
        $params = [];
        
        if (!empty($filters['role'])) {
            $sql .= " AND role = :role";
            $params[':role'] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (username LIKE :search OR full_name LIKE :search OR email LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // Get users by role
    public function getUsersByRole($role) {
        $sql = "SELECT * FROM users WHERE role = :role AND status = 'active' ORDER BY full_name";
        return $this->db->fetchAll($sql, [':role' => $role]);
    }
    
    // Change password
    public function changePassword($id, $newPassword) {
        $sql = "UPDATE users SET password = :password WHERE id = :id";
        $params = [
            ':id' => $id,
            ':password' => password_hash($newPassword, PASSWORD_DEFAULT)
        ];
        
        return $this->db->query($sql, $params) !== false;
    }
    
    // Update last login
    public function updateLastLogin($id) {
        $sql = "UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        return $this->db->query($sql, [':id' => $id]) !== false;
    }
}
?>