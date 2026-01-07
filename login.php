<?php
session_start();
require_once 'config/database.php';
date_default_timezone_set('Asia/Bangkok');

// --- เพิ่มระบบ CSRF Token ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// กำหนด path
define('ROOT_PATH', dirname(__FILE__));
define('ASSETS_PATH', ROOT_PATH . '/assets');


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
            // ถ้า role ไม่ถูกต้อง ให้ลบ session และแสดงหน้าล็อกอินใหม่
            session_destroy();
            break;
    }
    exit();
}

// กำหนดชื่อหน้า
$pageTitle = "เข้าสู่ระบบ - ระบบจองบ้านพักรับรอง กองบิน7";

// ตัวแปรสำหรับแสดงข้อความ error/success
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. ตรวจสอบ CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF Token");
    }

    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    if (empty($username) || empty($password)) {
        $error = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    } else {
        try {
            $pdo = DatabaseConfig::getConnection();

            // --- 2. เพิ่มระบบ Login Throttling ---
            $throttle_limit = 5; // ผิดได้ 5 ครั้ง
            $lockout_time = 15;  // ล็อก 15 นาที
            
            $checkLogSql = "SELECT COUNT(*) FROM logs 
                            WHERE ip_address = :ip AND action = 'login_failed' 
                            AND created_at > DATE_SUB(NOW(), INTERVAL :lock_time MINUTE)";
            $stmtLog = $pdo->prepare($checkLogSql);
            $stmtLog->execute([':ip' => $ip_address, ':lock_time' => $lockout_time]);
            $failed_count = $stmtLog->fetchColumn();

            if ($failed_count >= $throttle_limit) {
                $error = "คุณพยายามล็อกอินผิดเกินกำหนด กรุณาลองใหม่ในอีก $lockout_time นาที";
            } else {
                // ค้นหาผู้ใช้
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u1 OR email = :u2 LIMIT 1");
                $stmt->execute([':u1' => $username, ':u2' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] !== 'active') {
                        $error = "บัญชีผู้ใช้ถูกระงับการใช้งาน";
                    } else {
                        // ล็อกอินสำเร็จ: Regenerate Session เพื่อป้องกัน Session Fixation
                        session_regenerate_id(true);
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['logged_in'] = true;

                        // บันทึก Log สำเร็จ
                        $logSql = "INSERT INTO logs (user_id, action, table_name, record_id, ip_address, user_agent) 
                                   VALUES (:uid, 'login_success', 'users', :rid, :ip, :agent)";
                        $pdo->prepare($logSql)->execute([
                            ':uid' => $user['id'], ':rid' => $user['id'], ':ip' => $ip_address, ':agent' => $_SERVER['HTTP_USER_AGENT']
                        ]);

                        header("Location: " . $user['role'] . "/dashboard.php");
                        exit();
                    }
                } else {
                    // ล็อกอินไม่สำเร็จ: บันทึก Log ความล้มเหลวเพื่อใช้ในการ Throttling
                    $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
                    $logFailSql = "INSERT INTO logs (user_id, action, table_name, ip_address, user_agent) 
                                   VALUES (NULL, 'login_failed', 'users', :ip, :agent)";
                    $pdo->prepare($logFailSql)->execute([
                        ':ip' => $ip_address, ':agent' => $_SERVER['HTTP_USER_AGENT']
                    ]);
                }
            }
        } catch (Exception $e) {
            $error = "ระบบขัดข้อง: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            opacity: 0.3;
        }
        .logo-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .logo-container img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        .login-header h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            font-weight: 700;
        }
        .login-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        .login-body {
            padding: 40px 30px;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #2a5298;
            box-shadow: 0 0 0 0.25rem rgba(42, 82, 152, 0.25);
        }
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        .btn-login {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #172b4d 0%, #1e3c72 100%);
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(30, 60, 114, 0.2);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .form-check-input:checked {
            background-color: #2a5298;
            border-color: #2a5298;
        }
        .login-footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
        }
        .login-footer a {
            color: #2a5298;
            text-decoration: none;
            font-weight: 500;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
        .role-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
        .role-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-admin {
            background: #dc3545;
            color: white;
        }
        .badge-staff {
            background: #fd7e14;
            color: white;
        }
        .badge-approver {
            background: #20c997;
            color: white;
        }
        .badge-commander {
            background: #6f42c1;
            color: white;
        }
        .badge-user {
            background: #17a2b8;
            color: white;
        }
        .demo-credentials {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid #2a5298;
        }
        .demo-credentials h6 {
            color: #2a5298;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .demo-credentials table {
            width: 100%;
            font-size: 0.8rem;
        }
        .demo-credentials td {
            padding: 5px;
            border-bottom: 1px solid #e0e0e0;
        }
        .demo-credentials td:first-child {
            font-weight: 600;
            width: 40%;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-container">
                    <i class="fas fa-hotel" style="font-size: 50px; color: #1e3c72;"></i>
                </div>
                <h1>ระบบจองบ้านพักรับรอง</h1>
                <p>กองบิน 7</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-4">
                        <label for="username" class="form-label">
                            <i class="fas fa-user me-2"></i>ชื่อผู้ใช้หรืออีเมล
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="กรอกชื่อผู้ใช้หรืออีเมล" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                   required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-2"></i>รหัสผ่าน
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="กรอกรหัสผ่าน" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                จำการล็อกอิน
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบ
                    </button>
                    
                    <!-- <?php if (isset($_GET['debug'])): ?>
                    <div class="demo-credentials">
                        <h6><i class="fas fa-key me-2"></i>ข้อมูลทดสอบระบบ</h6>
                        <table>
                            <tr>
                                <td>Admin:</td>
                                <td>admin / password</td>
                            </tr>
                            <tr>
                                <td>Staff:</td>
                                <td>staff1 / password</td>
                            </tr>
                            <tr>
                                <td>Approver:</td>
                                <td>approver1 / password</td>
                            </tr>
                            <tr>
                                <td>Commander:</td>
                                <td>commander1 / password</td>
                            </tr>
                            <tr>
                                <td>User:</td>
                                <td>user1 / password</td>
                            </tr>
                        </table>
                    </div>
                    <?php endif; ?> -->
                </form>
                
                <div class="text-center mt-4">
                    <a href="forgot_password.php" class="text-decoration-none">
                        <i class="fas fa-question-circle me-2"></i>ลืมรหัสผ่าน?
                    </a>
                </div>
            </div>
            
            <div class="login-footer">
                <p class="mb-0">
                    สำหรับบุคคลทั่วไป 
                    <a href="general/booking_public.php" class="ms-2">
                        <i class="fas fa-external-link-alt me-1"></i>คลิกที่นี่เพื่อจองบ้านพัก
                    </a>
                </p>
                <p class="text-muted mt-2 mb-0" style="font-size: 0.8rem;">
                    เวอร์ชั่น 1.0.0 © 2024 กองบิน 7
                </p>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Auto-focus username field
        document.getElementById('username').focus();
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังเข้าสู่ระบบ...';
            submitBtn.disabled = true;
            
            return true;
        });
        
        // Enter key navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const activeElement = document.activeElement;
                if (activeElement.tagName === 'INPUT' && activeElement.type !== 'checkbox') {
                    const form = activeElement.closest('form');
                    const submitBtn = form.querySelector('button[type="submit"]');
                    submitBtn.click();
                }
            }
        });
    </script>
</body>
</html>