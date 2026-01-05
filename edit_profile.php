<?php
/**
 * Edit Profile Page
 * หน้าแก้ไขโปรไฟล์ผู้ใช้
 */

session_start();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?error=กรุณาล็อกอินเพื่อเข้าถึงระบบ');
    exit();
}

$pageTitle = "แก้ไขโปรไฟล์";

// เชื่อมต่อฐานข้อมูล
require_once '../config/database.php';
$db = getDatabaseConnection();

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$userId = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ถ้าไม่พบข้อมูลผู้ใช้
if (!$user) {
    header('Location: login.php?error=ไม่พบข้อมูลผู้ใช้');
    exit();
}

// ตรวจสอบการอัปเดตโปรไฟล์
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $fullName = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $position = trim($_POST['position']);
        $department = trim($_POST['department']);
        
        if (empty($fullName)) {
            throw new Exception("กรุณากรอกชื่อ-นามสกุล");
        }
        
        $stmt = $db->prepare("UPDATE users SET 
            full_name = ?, 
            phone = ?, 
            position = ?, 
            department = ?,
            updated_at = NOW()
            WHERE id = ?");
        
        $successUpdate = $stmt->execute([
            $fullName,
            $phone,
            $position,
            $department,
            $userId
        ]);
        
        if ($successUpdate) {
            // อัปเดต session
            $_SESSION['full_name'] = $fullName;
            $success = "อัปเดตโปรไฟล์สำเร็จ";
            // ดึงข้อมูลใหม่
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            throw new Exception("ไม่สามารถอัปเดตโปรไฟล์ได้");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - ระบบจองบ้านพักรับรอง กองบิน7</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-user-edit me-2"></i>แก้ไขโปรไฟล์</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">ชื่อ-นามสกุล *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">อีเมล</label>
                                <input type="email" class="form-control" id="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <div class="form-text">ไม่สามารถเปลี่ยนอีเมลได้</div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="position" class="form-label">ตำแหน่ง/ยศ</label>
                                    <input type="text" class="form-control" id="position" name="position" 
                                           value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="department" class="form-label">หน่วยงาน</label>
                                <input type="text" class="form-control" id="department" name="department" 
                                       value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">ชื่อผู้ใช้</label>
                                <input type="text" class="form-control" id="username" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <div class="form-text">ไม่สามารถเปลี่ยนชื่อผู้ใช้ได้</div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="profile.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>กลับ
                                </a>
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>บันทึกการเปลี่ยนแปลง
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>