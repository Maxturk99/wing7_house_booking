<?php
require_once '../../config/config.php';
$pageTitle = "เข้าสู่ระบบ";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - ระบบจองบ้านพักรับรอง กองบิน7</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../../assets/css/style.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 30px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px;
            width: 100%;
            font-weight: bold;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4090 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="login-header">
                <img src="../../assets/images/logo.png" alt="Logo" width="80" height="80" class="mb-3">
                <h3>ระบบจองบ้านพักรับรอง</h3>
                <h5>กองบิน 7</h5>
            </div>
            
            <div class="login-body">
                <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <form action="../../controllers/AuthController.php?action=login" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i> ชื่อผู้ใช้
                        </label>
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="กรอกชื่อผู้ใช้" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> รหัสผ่าน
                        </label>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="กรอกรหัสผ่าน" required>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">จำการล็อกอิน</label>
                    </div>
                    
                    <button type="submit" class="btn btn-login mb-3">
                        <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
                    </button>
                    
                    <div class="text-center">
                        <a href="forgot_password.php" class="text-decoration-none">ลืมรหัสผ่าน?</a>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="mb-2">สำหรับบุคคลทั่วไป</p>
                    <a href="../../general/booking_public.php" class="btn btn-outline-primary">
                        <i class="fas fa-home"></i> จองบ้านพัก
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../../assets/js/main.js"></script>
</body>
</html>