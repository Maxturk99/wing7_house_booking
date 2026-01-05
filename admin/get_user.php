<?php
session_start();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit();
}

require_once '../config/database.php';
$db = getDatabaseConnection();

$action = $_GET['action'] ?? '';
$userId = $_GET['id'] ?? 0;

// ตรวจสอบว่า ID เป็นตัวเลข
if (!is_numeric($userId)) {
    echo json_encode(['success' => false, 'message' => 'ID ไม่ถูกต้อง']);
    exit();
}

// ดึงข้อมูลผู้ใช้
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบผู้ใช้']);
    exit();
}

// ฟังก์ชันแปลงบทบาทเป็นภาษาไทย
function getRoleThai($role) {
    $roles = [
        'admin' => 'ผู้ดูแลระบบ',
        'staff' => 'เจ้าหน้าที่',
        'approver' => 'ผู้อนุมัติ',
        'commander' => 'ผู้บังคับบัญชา',
        'user' => 'ผู้ใช้งาน'
    ];
    return $roles[$role] ?? $role;
}

// ฟังก์ชันแปลงสถานะเป็นภาษาไทย
function getStatusThai($status) {
    $statuses = [
        'active' => 'ใช้งาน',
        'inactive' => 'ระงับการใช้งาน'
    ];
    return $statuses[$status] ?? $status;
}

switch ($action) {
    case 'view':
        // แสดงหน้าดูรายละเอียด
        echo '
        <div class="text-center mb-4">
            <div class="avatar mx-auto" style="width: 100px; height: 100px; font-size: 40px;">
                ' . strtoupper(substr($user['full_name'], 0, 1)) . '
            </div>
            <h4 class="mt-3">' . htmlspecialchars($user['full_name']) . '</h4>
            <p class="text-muted">@' . htmlspecialchars($user['username']) . '</p>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <th width="40%">อีเมล:</th>
                        <td>' . ($user['email'] ? htmlspecialchars($user['email']) : '<span class="text-muted">ไม่ระบุ</span>') . '</td>
                    </tr>
                    <tr>
                        <th>เบอร์โทร:</th>
                        <td>' . ($user['phone'] ? htmlspecialchars($user['phone']) : '<span class="text-muted">ไม่ระบุ</span>') . '</td>
                    </tr>
                    <tr>
                        <th>สังกัด:</th>
                        <td>' . ($user['department'] ? htmlspecialchars($user['department']) : '<span class="text-muted">ไม่ระบุ</span>') . '</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <th width="40%">ยศ/ตำแหน่ง:</th>
                        <td>' . ($user['rank'] ? htmlspecialchars($user['rank']) : '<span class="text-muted">ไม่ระบุ</span>') . '</td>
                    </tr>
                    <tr>
                        <th>บทบาท:</th>
                        <td><span class="badge bg-' . $user['role'] . '">' . getRoleThai($user['role']) . '</span></td>
                    </tr>
                    <tr>
                        <th>สถานะ:</th>
                        <td><span class="badge bg-' . ($user['status'] === 'active' ? 'success' : 'secondary') . '">' . getStatusThai($user['status']) . '</span></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="mb-3">
            <table class="table table-sm">
                <tr>
                    <th width="20%">Line Token:</th>
                    <td>' . ($user['line_token'] ? '<code>' . substr(htmlspecialchars($user['line_token']), 0, 30) . '...</code>' : '<span class="text-muted">ไม่ระบุ</span>') . '</td>
                </tr>
            </table>
        </div>
        
        <div class="alert alert-info">
            <small>
                <i class="fas fa-info-circle me-1"></i>
                สร้างเมื่อ: ' . date('d/m/Y H:i', strtotime($user['created_at'])) . '
                ' . ($user['updated_at'] ? '<br>แก้ไขล่าสุด: ' . date('d/m/Y H:i', strtotime($user['updated_at'])) : '') . '
            </small>
        </div>';
        break;
        
    case 'edit':
        // แสดงฟอร์มแก้ไข
        echo '
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="id" value="' . $user['id'] . '">
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="edit_username" class="form-label">ชื่อผู้ใช้</label>
                <input type="text" class="form-control" id="edit_username" name="username" value="' . htmlspecialchars($user['username']) . '" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">เปลี่ยนรหัสผ่าน</label><br>
                <button type="button" class="btn btn-outline-primary btn-sm change-password-btn" data-id="' . $user['id'] . '">
                    <i class="fas fa-key me-1"></i>เปลี่ยนรหัสผ่าน
                </button>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="edit_full_name" class="form-label">ชื่อ-นามสกุล</label>
                <input type="text" class="form-control" id="edit_full_name" name="full_name" value="' . htmlspecialchars($user['full_name']) . '" required>
            </div>
            <div class="col-md-6">
                <label for="edit_email" class="form-label">อีเมล</label>
                <input type="email" class="form-control" id="edit_email" name="email" value="' . htmlspecialchars($user['email']) . '">
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="edit_phone" class="form-label">เบอร์โทรศัพท์</label>
                <input type="tel" class="form-control" id="edit_phone" name="phone" value="' . htmlspecialchars($user['phone']) . '">
            </div>
            <div class="col-md-6">
                <label for="edit_department" class="form-label">สังกัด/หน่วยงาน</label>
                <input type="text" class="form-control" id="edit_department" name="department" value="' . htmlspecialchars($user['department']) . '">
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="edit_rank" class="form-label">ยศ/ตำแหน่ง</label>
                <input type="text" class="form-control" id="edit_rank" name="rank" value="' . htmlspecialchars($user['rank']) . '">
            </div>
            <div class="col-md-6">
                <label for="edit_role" class="form-label">บทบาท</label>
                <select class="form-select" id="edit_role" name="role" required>
                    <option value="user" ' . ($user['role'] == 'user' ? 'selected' : '') . '>ผู้ใช้งาน</option>
                    <option value="staff" ' . ($user['role'] == 'staff' ? 'selected' : '') . '>เจ้าหน้าที่</option>
                    <option value="approver" ' . ($user['role'] == 'approver' ? 'selected' : '') . '>ผู้อนุมัติ</option>
                    <option value="commander" ' . ($user['role'] == 'commander' ? 'selected' : '') . '>ผู้บังคับบัญชา</option>
                    <option value="admin" ' . ($user['role'] == 'admin' ? 'selected' : '') . '>ผู้ดูแลระบบ</option>
                </select>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="edit_line_token" class="form-label">Line Notify Token</label>
                <input type="text" class="form-control" id="edit_line_token" name="line_token" value="' . htmlspecialchars($user['line_token']) . '">
                <div class="form-text">สำหรับรับการแจ้งเตือนผ่าน Line</div>
            </div>
            <div class="col-md-6">
                <label for="edit_status" class="form-label">สถานะ</label>
                <select class="form-select" id="edit_status" name="status">
                    <option value="active" ' . ($user['status'] == 'active' ? 'selected' : '') . '>ใช้งาน</option>
                    <option value="inactive" ' . ($user['status'] == 'inactive' ? 'selected' : '') . '>ระงับการใช้งาน</option>
                </select>
            </div>
        </div>';
        break;
        
    case 'change_password':
        // แสดงฟอร์มเปลี่ยนรหัสผ่าน
        echo '
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="id" value="' . $user['id'] . '">
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            กำลังเปลี่ยนรหัสผ่านสำหรับ: <strong>' . htmlspecialchars($user['full_name']) . ' (@' . htmlspecialchars($user['username']) . ')</strong>
        </div>
        
        <div class="mb-3">
            <label for="new_password" class="form-label">รหัสผ่านใหม่</label>
            <input type="password" class="form-control" id="new_password" name="new_password" required>
            <div class="form-text">ต้องมีความยาวอย่างน้อย 6 ตัวอักษร</div>
        </div>
        
        <div class="mb-3">
            <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
        </div>';
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action ไม่ถูกต้อง']);
}
?>