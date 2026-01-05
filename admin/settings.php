<?php
/**
 * System Settings Page
 * หน้าการตั้งค่าระบบ
 */

session_start();

// ตรวจสอบสิทธิ์ - เฉพาะ admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php?error=กรุณาล็อกอินเพื่อเข้าถึงระบบ');
    exit();
}

$pageTitle = "ตั้งค่าระบบ";

// เชื่อมต่อฐานข้อมูล
require_once '../config/database.php';
$db = getDatabaseConnection();

// ดึงการตั้งค่าปัจจุบัน
$settingsStmt = $db->query("SELECT * FROM system_settings ORDER BY setting_group, setting_key");
$settings = [];
while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// กำหนดค่าสีเริ่มต้น
$colors = [
    'primary_color' => $settings['primary_color'] ?? '#1e3c72',
    'secondary_color' => $settings['secondary_color'] ?? '#2a5298',
    'success_color' => $settings['success_color'] ?? '#28a745',
    'danger_color' => $settings['danger_color'] ?? '#dc3545',
    'warning_color' => $settings['warning_color'] ?? '#ffc107',
    'info_color' => $settings['info_color'] ?? '#17a2b8'
];

// ตัวแปรสำหรับข้อความ
$error = '';
$success = '';

// ตรวจสอบการอัปโหลดไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // อัปโหลดโลโก้
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        // ตรวจสอบขนาดไฟล์
        if ($_FILES['logo']['size'] > $maxSize) {
            $error = "ไฟล์ต้องเป็นรูปภาพ (JPG, PNG, GIF) และขนาดไม่เกิน 2MB";
        } else {
            // ตรวจสอบประเภทไฟล์
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['logo']['tmp_name']);
            finfo_close($finfo);
            
            if (in_array($mime, $allowedTypes)) {
                $uploadDir = dirname(__DIR__) . '/assets/images/';
                
                // สร้างโฟลเดอร์ถ้ายังไม่มี
                if (!file_exists($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        $error = "ไม่สามารถสร้างโฟลเดอร์สำหรับเก็บไฟล์";
                    }
                }
                
                // ตรวจสอบว่าโฟลเดอร์มีสิทธิ์เขียนหรือไม่
                if (!is_writable($uploadDir)) {
                    $error = "โฟลเดอร์ assets/images ไม่มีสิทธิ์การเขียน กรุณาตรวจสอบสิทธิ์ของโฟลเดอร์";
                } else {
                    // สร้างชื่อไฟล์ใหม่
                    $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                    $filename = 'logo_' . time() . '.' . strtolower($extension);
                    $uploadPath = $uploadDir . $filename;
                    
                    // ลบไฟล์โลโก้เก่า (ถ้ามี)
                    if (!empty($settings['system_logo']) && $settings['system_logo'] !== 'logo.png') {
                        $oldLogoPath = $uploadDir . $settings['system_logo'];
                        if (file_exists($oldLogoPath)) {
                            @unlink($oldLogoPath);
                        }
                    }
                    
                    // อัปโหลดไฟล์
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                        // อัปเดตการตั้งค่าในฐานข้อมูล
                        try {
                            $stmt = $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'system_logo'");
                            $stmt->execute([$filename]);
                            
                            $success = "อัปโหลดโลโก้สำเร็จ";
                            $settings['system_logo'] = $filename;
                        } catch (Exception $e) {
                            $error = "ไม่สามารถบันทึกข้อมูลโลโก้ในฐานข้อมูล: " . $e->getMessage();
                            // ลบไฟล์ที่อัปโหลดแล้ว
                            @unlink($uploadPath);
                        }
                    } else {
                        $error = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
                        // แสดงข้อผิดพลาดเพิ่มเติม
                        $uploadErrors = [
                            0 => 'ไม่มีข้อผิดพลาด',
                            1 => 'ไฟล์มีขนาดเกิน upload_max_filesize',
                            2 => 'ไฟล์มีขนาดเกิน MAX_FILE_SIZE',
                            3 => 'ไฟล์ถูกอัปโหลดเพียงบางส่วน',
                            4 => 'ไม่มีไฟล์ถูกอัปโหลด',
                            6 => 'ไม่มีโฟลเดอร์ temp',
                            7 => 'เขียนไฟล์ล้มเหลว',
                            8 => 'PHP extension หยุดการอัปโหลด'
                        ];
                        $errorCode = $_FILES['logo']['error'];
                        $error .= " (รหัสข้อผิดพลาด: {$errorCode})";
                    }
                }
            } else {
                $error = "ไฟล์ต้องเป็นรูปภาพเท่านั้น (JPG, PNG, GIF)";
            }
        }
    }
    
    // บันทึกการตั้งค่าอื่นๆ
    if (isset($_POST['save_settings'])) {
        try {
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'setting_') === 0) {
                    $settingKey = str_replace('setting_', '', $key);
                    $stmt = $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$value, $settingKey]);
                }
            }
            if (empty($error)) {
                $success = "บันทึกการตั้งค่าสำเร็จ";
            }
        } catch (Exception $e) {
            $error = "เกิดข้อผิดพลาดในการบันทึกการตั้งค่า: " . $e->getMessage();
        }
    }
}
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
    <!-- Color Picker -->
    <link href="https://cdn.jsdelivr.net/npm/spectrum-colorpicker2/dist/spectrum.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .sidebar {
            background: white;
            min-height: calc(100vh - 56px);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: absolute;
            width: 250px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .settings-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .settings-section {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        .settings-section:last-child {
            border-bottom: none;
        }
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            display: inline-block;
            margin-right: 10px;
            border: 1px solid #dee2e6;
        }
        .theme-preview {
            width: 100%;
            height: 150px;
            border-radius: 10px;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            position: relative;
            overflow: hidden;
        }
        .theme-preview::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(255,255,255,0.1) 100%);
        }
        .logo-preview {
            max-width: 200px;
            max-height: 100px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
            padding: 10px;
            background: white;
        }
        .logo-preview img {
            max-width: 100%;
            max-height: 80px;
            object-fit: contain;
        }
        .sp-replacer {
            border: 1px solid #dee2e6 !important;
            border-radius: 5px !important;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: static;
                width: 100%;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-home me-2"></i>ระบบจองบ้านพักรับรอง กองบิน7
            </a>
            <div class="d-flex align-items-center text-white">
                <span class="me-3"><?php echo $_SESSION['full_name']; ?></span>
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit me-2"></i>โปรไฟล์</a></li>
                        <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2"></i>เปลี่ยนรหัสผ่าน</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>แดชบอร์ด
                    </a>
                    <a href="bookings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-check me-2"></i>จัดการการจอง
                    </a>
                    <a href="buildings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-building me-2"></i>จัดการอาคาร
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i>จัดการผู้ใช้
                    </a>
                    <a href="reports.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-bar me-2"></i>รายงานและสถิติ
                    </a>
                    <a href="settings.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-cog me-2"></i>ตั้งค่าระบบ
                    </a>
                    <div class="mt-4 pt-3 border-top">
                        <a href="../general/booking_public.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-external-link-alt me-2"></i>จองสำหรับบุคคลทั่วไป
                        </a>
                        <a href="../logout.php" class="list-group-item list-group-item-action text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-cog me-2"></i><?php echo $pageTitle; ?></h2>
                    <button type="button" class="btn btn-success" onclick="document.getElementById('settingsForm').submit();">
                        <i class="fas fa-save me-2"></i>บันทึกการตั้งค่าทั้งหมด
                    </button>
                </div>

                <!-- Messages -->
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Settings Form -->
                <form id="settingsForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="save_settings" value="1">
                    
                    <!-- Logo Upload -->
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-image me-2"></i>โลโก้ระบบ</h5>
                        </div>
                        <div class="settings-section">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">โลโก้ปัจจุบัน</label>
                                    <div class="logo-preview">
                                        <?php 
                                        $logoPath = '../assets/images/' . ($settings['system_logo'] ?? 'logo.png');
                                        if (file_exists($logoPath) && !empty($settings['system_logo'])): 
                                        ?>
                                        <img src="<?php echo $logoPath . '?t=' . time(); ?>" 
                                             alt="Logo" id="currentLogo">
                                        <?php else: ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-image fa-3x mb-3"></i>
                                            <p class="mb-0">ยังไม่ได้ตั้งค่าโลโก้</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (file_exists($logoPath) && !empty($settings['system_logo'])): ?>
                                    <div class="text-center">
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteLogo()">
                                            <i class="fas fa-trash me-1"></i>ลบโลโก้ปัจจุบัน
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="logo" class="form-label">อัปโหลดโลโก้ใหม่</label>
                                        <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                        <div class="form-text">
                                            รองรับไฟล์: JPG, PNG, GIF | ขนาดสูงสุด: 2MB
                                            <br>ขนาดแนะนำ: 200x80 พิกเซล
                                        </div>
                                    </div>
                                    <button type="submit" name="upload_logo" class="btn btn-primary" onclick="setUploadAction()">
                                        <i class="fas fa-upload me-2"></i>อัปโหลดโลโก้
                                    </button>
                                    <div class="mt-3">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>หมายเหตุ:</strong> 
                                            <ul class="mb-0 mt-2">
                                                <li>โฟลเดอร์สำหรับเก็บไฟล์: <code>assets/images/</code></li>
                                                <li>ไฟล์โลโก้จะถูกเก็บชื่อว่า: <code>logo_เวลาปัจจุบัน.นามสกุล</code></li>
                                                <li>โลโก้เก่าจะถูกลบออกอัตโนมัติ</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>ข้อมูลระบบ</h5>
                        </div>
                        <div class="settings-section">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="setting_system_name" class="form-label">ชื่อระบบ</label>
                                    <input type="text" class="form-control" id="setting_system_name" 
                                           name="setting_system_name" 
                                           value="<?php echo htmlspecialchars($settings['system_name'] ?? 'ระบบจองบ้านพักรับรอง กองบิน7'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="setting_app_version" class="form-label">เวอร์ชั่น</label>
                                    <input type="text" class="form-control" id="setting_app_version" 
                                           name="setting_app_version" 
                                           value="<?php echo htmlspecialchars($settings['app_version'] ?? '1.0.0'); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="setting_system_description" class="form-label">คำอธิบายระบบ</label>
                                <textarea class="form-control" id="setting_system_description" 
                                          name="setting_system_description" rows="3"><?php echo htmlspecialchars($settings['system_description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Theme Settings -->
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-palette me-2"></i>การตั้งค่าธีมและสี</h5>
                        </div>
                        <div class="settings-section">
                            <div class="theme-preview mb-4" 
                                 style="--primary-color: <?php echo $colors['primary_color']; ?>; 
                                        --secondary-color: <?php echo $colors['secondary_color']; ?>;">
                                <div class="position-absolute top-0 start-0 p-3 text-white">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($settings['system_name'] ?? 'ระบบจองบ้านพักรับรอง'); ?></h5>
                                    <small>ตัวอย่างธีม</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="setting_primary_color" class="form-label">สีหลัก</label>
                                    <div class="input-group">
                                        <span class="color-preview" 
                                              style="background-color: <?php echo $colors['primary_color']; ?>"></span>
                                        <input type="text" class="form-control color-picker" 
                                               id="setting_primary_color" name="setting_primary_color"
                                               value="<?php echo $colors['primary_color']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="setting_secondary_color" class="form-label">สีรอง</label>
                                    <div class="input-group">
                                        <span class="color-preview" 
                                              style="background-color: <?php echo $colors['secondary_color']; ?>"></span>
                                        <input type="text" class="form-control color-picker" 
                                               id="setting_secondary_color" name="setting_secondary_color"
                                               value="<?php echo $colors['secondary_color']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="setting_success_color" class="form-label">สีสำเร็จ</label>
                                    <div class="input-group">
                                        <span class="color-preview" 
                                              style="background-color: <?php echo $colors['success_color']; ?>"></span>
                                        <input type="text" class="form-control color-picker" 
                                               id="setting_success_color" name="setting_success_color"
                                               value="<?php echo $colors['success_color']; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="setting_danger_color" class="form-label">สีข้อผิดพลาด</label>
                                    <div class="input-group">
                                        <span class="color-preview" 
                                              style="background-color: <?php echo $colors['danger_color']; ?>"></span>
                                        <input type="text" class="form-control color-picker" 
                                               id="setting_danger_color" name="setting_danger_color"
                                               value="<?php echo $colors['danger_color']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="setting_warning_color" class="form-label">สีคำเตือน</label>
                                    <div class="input-group">
                                        <span class="color-preview" 
                                              style="background-color: <?php echo $colors['warning_color']; ?>"></span>
                                        <input type="text" class="form-control color-picker" 
                                               id="setting_warning_color" name="setting_warning_color"
                                               value="<?php echo $colors['warning_color']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="setting_info_color" class="form-label">สีข้อมูล</label>
                                    <div class="input-group">
                                        <span class="color-preview" 
                                              style="background-color: <?php echo $colors['info_color']; ?>"></span>
                                        <input type="text" class="form-control color-picker" 
                                               id="setting_info_color" name="setting_info_color"
                                               value="<?php echo $colors['info_color']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>การตั้งค่าอีเมล</h5>
                        </div>
                        <div class="settings-section">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="setting_smtp_host" class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" id="setting_smtp_host" 
                                           name="setting_smtp_host" 
                                           value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="setting_smtp_port" class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" id="setting_smtp_port" 
                                           name="setting_smtp_port" 
                                           value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="setting_smtp_username" class="form-label">SMTP Username</label>
                                    <input type="text" class="form-control" id="setting_smtp_username" 
                                           name="setting_smtp_username" 
                                           value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="setting_smtp_password" class="form-label">SMTP Password</label>
                                    <input type="password" class="form-control" id="setting_smtp_password" 
                                           name="setting_smtp_password" 
                                           value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="setting_email_from" class="form-label">Email From</label>
                                    <input type="email" class="form-control" id="setting_email_from" 
                                           name="setting_email_from" 
                                           value="<?php echo htmlspecialchars($settings['email_from'] ?? 'noreply@wing7.local'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="setting_email_from_name" class="form-label">Email From Name</label>
                                    <input type="text" class="form-control" id="setting_email_from_name" 
                                           name="setting_email_from_name" 
                                           value="<?php echo htmlspecialchars($settings['email_from_name'] ?? 'ระบบจองบ้านพักรับรอง กองบิน7'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Settings -->
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bell me-2"></i>การตั้งค่าการแจ้งเตือน</h5>
                        </div>
                        <div class="settings-section">
                            <div class="mb-3">
                                <label for="setting_line_notify_token" class="form-label">Line Notify Token</label>
                                <input type="text" class="form-control" id="setting_line_notify_token" 
                                       name="setting_line_notify_token" 
                                       value="<?php echo htmlspecialchars($settings['line_notify_token'] ?? ''); ?>">
                                <div class="form-text">
                                    <a href="https://notify-bot.line.me/th/" target="_blank">คลิกที่นี่เพื่อสร้าง Line Notify Token</a>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="setting_enable_email_notification" 
                                       name="setting_enable_email_notification" value="1"
                                       <?php echo ($settings['enable_email_notification'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="setting_enable_email_notification">
                                    เปิดใช้งานการแจ้งเตือนผ่านอีเมล
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="setting_enable_line_notification" 
                                       name="setting_enable_line_notification" value="1"
                                       <?php echo ($settings['enable_line_notification'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="setting_enable_line_notification">
                                    เปิดใช้งานการแจ้งเตือนผ่าน Line
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Settings -->
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>การตั้งค่าการจอง</h5>
                        </div>
                        <div class="settings-section">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="setting_default_checkin_time" class="form-label">เวลาเช็คอินมาตรฐาน</label>
                                    <input type="time" class="form-control" id="setting_default_checkin_time" 
                                           name="setting_default_checkin_time" 
                                           value="<?php echo htmlspecialchars($settings['default_checkin_time'] ?? '14:00:00'); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="setting_default_checkout_time" class="form-label">เวลาเช็คเอาต์มาตรฐาน</label>
                                    <input type="time" class="form-control" id="setting_default_checkout_time" 
                                           name="setting_default_checkout_time" 
                                           value="<?php echo htmlspecialchars($settings['default_checkout_time'] ?? '12:00:00'); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="setting_max_booking_days" class="form-label">จำนวนวันที่จองสูงสุด</label>
                                    <input type="number" class="form-control" id="setting_max_booking_days" 
                                           name="setting_max_booking_days" 
                                           value="<?php echo htmlspecialchars($settings['max_booking_days'] ?? '30'); ?>">
                                    <div class="form-text">จำนวนวัน</div>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="setting_allow_overbooking" 
                                       name="setting_allow_overbooking" value="1"
                                       <?php echo ($settings['allow_overbooking'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="setting_allow_overbooking">
                                    อนุญาตให้จองเกินกำหนดได้
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="setting_require_approval" 
                                       name="setting_require_approval" value="1"
                                       <?php echo ($settings['require_approval'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="setting_require_approval">
                                    ต้องได้รับการอนุมัติก่อนจอง
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- System Maintenance -->
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-tools me-2"></i>บำรุงรักษาระบบ</h5>
                        </div>
                        <div class="settings-section">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>คำเตือน:</strong> การดำเนินการในส่วนนี้อาจส่งผลต่อระบบ กรุณาดำเนินการด้วยความระมัดระวัง
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">สำรองฐานข้อมูล</label>
                                    <button type="button" class="btn btn-outline-primary w-100" onclick="backupDatabase()">
                                        <i class="fas fa-database me-2"></i>สำรองฐานข้อมูล
                                    </button>
                                    <div class="form-text">ดาวน์โหลดไฟล์ SQL สำหรับการสำรองข้อมูล</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ล้างแคชระบบ</label>
                                    <button type="button" class="btn btn-outline-secondary w-100" onclick="clearCache()">
                                        <i class="fas fa-broom me-2"></i>ล้างแคช
                                    </button>
                                    <div class="form-text">ล้างข้อมูลแคชและรีเฟรชระบบ</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="maintenance_mode" class="form-label">โหมดบำรุงรักษา</label>
                                <select class="form-select" id="maintenance_mode" name="maintenance_mode">
                                    <option value="0" <?php echo ($settings['maintenance_mode'] ?? '0') == '0' ? 'selected' : ''; ?>>ปิด</option>
                                    <option value="1" <?php echo ($settings['maintenance_mode'] ?? '0') == '1' ? 'selected' : ''; ?>>เปิด</option>
                                </select>
                                <div class="form-text">
                                    เมื่อเปิดโหมดบำรุงรักษา ระบบจะแสดงข้อความ "กำลังบำรุงรักษา" แก่ผู้ใช้ทั่วไป
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="text-center mb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>บันทึกการตั้งค่าทั้งหมด
                        </button>
                        <button type="reset" class="btn btn-secondary btn-lg ms-2">
                            <i class="fas fa-undo me-2"></i>คืนค่าตั้งต้น
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/spectrum-colorpicker2/dist/spectrum.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Initialize color pickers
        $(document).ready(function() {
            $('.color-picker').spectrum({
                preferredFormat: "hex",
                showInput: true,
                showPalette: true,
                palette: [
                    ["#1e3c72", "#2a5298", "#007bff", "#6c757d"],
                    ["#28a745", "#20c997", "#17a2b8", "#fd7e14"],
                    ["#dc3545", "#6f42c1", "#e83e8c", "#ffc107"],
                    ["#343a40", "#495057", "#dee2e6", "#f8f9fa"]
                ]
            });
            
            // Preview logo before upload
            $('#logo').on('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#currentLogo').attr('src', e.target.result);
                    }
                    reader.readAsDataURL(file);
                }
            });
        });

        // Set upload action for logo button
        function setUploadAction() {
            // Ensure the form will submit with upload
            $('#settingsForm').append('<input type="hidden" name="upload_logo" value="1">');
        }

        // Preview theme changes
        $('.color-picker').on('change', function() {
            const color = $(this).val();
            const setting = $(this).attr('id').replace('setting_', '');
            
            // Update preview
            if (setting === 'primary_color' || setting === 'secondary_color') {
                const primary = $('#setting_primary_color').val();
                const secondary = $('#setting_secondary_color').val();
                $('.theme-preview').css({
                    '--primary-color': primary,
                    '--secondary-color': secondary,
                    'background': `linear-gradient(135deg, ${primary} 0%, ${secondary} 100%)`
                });
            }
            
            // Update color preview box
            $(this).siblings('.color-preview').css('background-color', color);
        });

        // Delete current logo
        function deleteLogo() {
            Swal.fire({
                title: 'ยืนยันการลบโลโก้',
                text: 'คุณต้องการลบโลโก้ปัจจุบันใช่หรือไม่?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'delete_logo.php',
                        type: 'POST',
                        data: {
                            logo_filename: '<?php echo $settings['system_logo'] ?? ""; ?>'
                        },
                        success: function(response) {
                            const result = JSON.parse(response);
                            if (result.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ',
                                    text: result.message
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ผิดพลาด',
                                    text: result.message
                                });
                            }
                        }
                    });
                }
            });
        }

        // Backup database function
        function backupDatabase() {
            Swal.fire({
                title: 'ยืนยันการสำรองข้อมูล',
                text: 'คุณต้องการสำรองฐานข้อมูลใช่หรือไม่?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'สำรองข้อมูล',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'backup_database.php';
                }
            });
        }

        // Clear cache function
        function clearCache() {
            Swal.fire({
                title: 'ยืนยันการล้างแคช',
                text: 'คุณต้องการล้างแคชระบบใช่หรือไม่?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'ล้างแคช',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'clear_cache.php',
                        type: 'POST',
                        success: function(response) {
                            const result = JSON.parse(response);
                            if (result.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ',
                                    text: result.message
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ผิดพลาด',
                                    text: result.message
                                });
                            }
                        }
                    });
                }
            });
        }

        // Test email settings
        function testEmailSettings() {
            Swal.fire({
                title: 'ทดสอบการส่งอีเมล',
                text: 'กรุณากรอกอีเมลที่ต้องการทดสอบ',
                input: 'email',
                inputPlaceholder: 'email@example.com',
                showCancelButton: true,
                confirmButtonText: 'ส่งอีเมลทดสอบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    $.ajax({
                        url: 'test_email.php',
                        type: 'POST',
                        data: { email: result.value },
                        success: function(response) {
                            const result = JSON.parse(response);
                            if (result.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ',
                                    text: result.message
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ผิดพลาด',
                                    text: result.message
                                });
                            }
                        }
                    });
                }
            });
        }

        // Test Line Notify
        function testLineNotify() {
            Swal.fire({
                title: 'ทดสอบ Line Notify',
                text: 'ส่งข้อความทดสอบไปยัง Line Notify',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'ส่งข้อความทดสอบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'test_line_notify.php',
                        type: 'POST',
                        success: function(response) {
                            const result = JSON.parse(response);
                            if (result.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ',
                                    text: result.message
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ผิดพลาด',
                                    text: result.message
                                });
                            }
                        }
                    });
                }
            });
        }

        // Form validation
        $('#settingsForm').on('submit', function(e) {
            const email = $('#setting_smtp_username').val();
            const password = $('#setting_smtp_password').val();
            
            if (email && !password) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณากรอกรหัสผ่าน SMTP',
                    text: 'หากต้องการใช้การส่งอีเมล กรุณากรอกรหัสผ่าน SMTP'
                });
                e.preventDefault();
                return false;
            }
            
            // Show loading for logo upload
            if ($('#logo').val()) {
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>กำลังอัปโหลด...');
                submitBtn.prop('disabled', true);
            }
            
            return true;
        });
    </script>
</body>
</html>