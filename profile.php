<?php
/**
 * User Profile Page
 * หน้าโปรไฟล์ผู้ใช้
 */

session_start();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?error=กรุณาล็อกอินเพื่อเข้าถึงระบบ');
    exit();
}

$pageTitle = "โปรไฟล์ผู้ใช้";

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

// ฟังก์ชันแปลงสถานะผู้ใช้
function getUserStatusThai($status) {
    $statuses = [
        'active' => 'ใช้งานปกติ',
        'inactive' => 'ระงับการใช้งาน',
        'pending' => 'รออนุมัติ'
    ];
    return $statuses[$status] ?? $status;
}

// ฟังก์ชันแปลงบทบาทผู้ใช้
function getUserRoleThai($role) {
    $roles = [
        'admin' => 'ผู้ดูแลระบบ',
        'staff' => 'เจ้าหน้าที่',
        'officer' => 'เจ้าหน้าที่หน่วยงาน',
        'general' => 'บุคคลทั่วไป',
        'guest' => 'ผู้เข้าพัก'
    ];
    return $roles[$role] ?? $role;
}

// ฟังก์ชันสำหรับ badge สถานะ
function getUserStatusBadge($status) {
    $badges = [
        'active' => 'success',
        'inactive' => 'danger',
        'pending' => 'warning'
    ];
    return $badges[$status] ?? 'secondary';
}

// ฟังก์ชันสำหรับ badge บทบาท
function getUserRoleBadge($role) {
    $badges = [
        'admin' => 'danger',
        'staff' => 'primary',
        'officer' => 'info',
        'general' => 'secondary',
        'guest' => 'light'
    ];
    return $badges[$role] ?? 'secondary';
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
        .profile-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 30px;
            font-size: 48px;
            color: white;
            font-weight: bold;
        }
        .profile-info {
            flex: 1;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            transition: transform 0.3s;
        }
        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .info-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .info-value {
            color: #333;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 10px;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
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
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 20px;
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
                <span class="me-3"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
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
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="users.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i>จัดการผู้ใช้
                    </a>
                    <?php endif; ?>
                    <a href="reports.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-bar me-2"></i>รายงานและสถิติ
                    </a>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="settings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog me-2"></i>ตั้งค่าระบบ
                    </a>
                    <?php endif; ?>
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
                    <h2><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($pageTitle); ?></h2>
                    <div>
                        <a href="change_password.php" class="btn btn-warning me-2">
                            <i class="fas fa-key me-2"></i>เปลี่ยนรหัสผ่าน
                        </a>
                        <a href="edit_profile.php" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>แก้ไขโปรไฟล์
                        </a>
                    </div>
                </div>

                <!-- Profile Card -->
                <div class="profile-card">
                    <!-- Profile Header -->
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php 
                            // แสดงตัวอักษรแรกของชื่อ-นามสกุล
                            $nameParts = explode(' ', $user['full_name']);
                            $firstName = $nameParts[0] ?? '';
                            $lastName = end($nameParts) ?? '';
                            $initials = mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1);
                            echo mb_strtoupper($initials);
                            ?>
                        </div>
                        <div class="profile-info">
                            <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge bg-<?php echo getUserRoleBadge($user['role']); ?> me-2">
                                    <?php echo getUserRoleThai($user['role']); ?>
                                </span>
                                <span class="badge bg-<?php echo getUserStatusBadge($user['status']); ?>">
                                    <?php echo getUserStatusThai($user['status']); ?>
                                </span>
                            </div>
                            <p class="text-muted mb-0">
                                <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Information Grid -->
                    <div class="info-grid">
                        <!-- Basic Information -->
                        <div class="info-card">
                            <h5><i class="fas fa-info-circle me-2 text-primary"></i>ข้อมูลพื้นฐาน</h5>
                            <div class="mt-3">
                                <div class="mb-3">
                                    <div class="info-label">ชื่อ-นามสกุล</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">ตำแหน่ง/ยศ</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['position'] ?: '-'); ?></div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">หน่วยงาน</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['department'] ?: '-'); ?></div>
                                </div>
                                <div>
                                    <div class="info-label">เบอร์โทรศัพท์</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?: '-'); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="info-card">
                            <h5><i class="fas fa-user-circle me-2 text-success"></i>ข้อมูลบัญชี</h5>
                            <div class="mt-3">
                                <div class="mb-3">
                                    <div class="info-label">ชื่อผู้ใช้</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">อีเมล</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">บทบาท</div>
                                    <div class="info-value">
                                        <span class="badge bg-<?php echo getUserRoleBadge($user['role']); ?>">
                                            <?php echo getUserRoleThai($user['role']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <div class="info-label">สถานะ</div>
                                    <div class="info-value">
                                        <span class="badge bg-<?php echo getUserStatusBadge($user['status']); ?>">
                                            <?php echo getUserStatusThai($user['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Information -->
                        <div class="info-card">
                            <h5><i class="fas fa-cogs me-2 text-info"></i>ข้อมูลระบบ</h5>
                            <div class="mt-3">
                                <div class="mb-3">
                                    <div class="info-label">วันที่ลงทะเบียน</div>
                                    <div class="info-value">
                                        <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">อัปเดตล่าสุด</div>
                                    <div class="info-value">
                                        <?php echo $user['updated_at'] ? date('d/m/Y H:i', strtotime($user['updated_at'])) : '-'; ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="info-label">เข้าสู่ระบบล่าสุด</div>
                                    <div class="info-value">
                                        <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-'; ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="info-label">หมายเหตุ</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['notes'] ?: '-'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics (Optional) -->
                    <?php 
                    // ดึงสถิติการใช้งาน
                    $bookingCount = 0;
                    $approvedCount = 0;
                    $recentBookings = 0;
                    
                    if (in_array($user['role'], ['staff', 'officer', 'general'])) {
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE created_by = ?");
                        $stmt->execute([$userId]);
                        $bookingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                        
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE created_by = ? AND status = 'approved'");
                        $stmt->execute([$userId]);
                        $approvedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                        
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE created_by = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                        $stmt->execute([$userId]);
                        $recentBookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    }
                    ?>
                    
                    <?php if ($bookingCount > 0): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $bookingCount; ?></div>
                            <div class="stat-label">การจองทั้งหมด</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $approvedCount; ?></div>
                            <div class="stat-label">การจองที่อนุมัติ</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $recentBookings; ?></div>
                            <div class="stat-label">การจองล่าสุด (30 วัน)</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-center gap-3 mt-4">
                    <a href="edit_profile.php" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>แก้ไขโปรไฟล์
                    </a>
                    <a href="change_password.php" class="btn btn-warning">
                        <i class="fas fa-key me-2"></i>เปลี่ยนรหัสผ่าน
                    </a>
                    <a href="../logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Print profile function
        function printProfile() {
            const printWindow = window.open('', '_blank');
            const profileContent = document.querySelector('.profile-card').innerHTML;
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>โปรไฟล์ผู้ใช้ - ระบบจองบ้านพักรับรอง กองบิน7</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        @media print {
                            body { padding: 20px; }
                            .btn { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h2 class="text-center mb-4">โปรไฟล์ผู้ใช้</h2>
                        <div class="text-center mb-4">
                            <strong>พิมพ์เมื่อ:</strong> ${new Date().toLocaleDateString('th-TH')} ${new Date().toLocaleTimeString('th-TH')}
                        </div>
                        ${profileContent}
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
        }

        // Export profile as PDF (simplified)
        function exportProfile() {
            Swal.fire({
                title: 'กำลังส่งออก',
                text: 'กำลังเตรียมข้อมูลสำหรับส่งออก...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    setTimeout(() => {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            text: 'ข้อมูลพร้อมสำหรับส่งออก',
                            showCancelButton: true,
                            confirmButtonText: 'เปิด',
                            cancelButtonText: 'ปิด'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                printProfile();
                            }
                        });
                    }, 1500);
                }
            });
        }
    </script>
</body>
</html>