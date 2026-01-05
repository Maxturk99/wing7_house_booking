<?php
/**
 * Admin Dashboard Page
 * แดชบอร์ดผู้ดูแลระบบ
 */

session_start();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php?error=กรุณาล็อกอินเพื่อเข้าถึงระบบ');
    exit();
}

$pageTitle = "แดชบอร์ดผู้ดูแลระบบ";

// เชื่อมต่อฐานข้อมูล
require_once '../config/database.php';
$db = getDatabaseConnection();

// กำหนดค่าเริ่มต้นสำหรับตัวแปร
$stats = [];
$recentBookings = [];
$buildingStatus = [];
$monthlyStats = [];
$error = '';

// ดึงสถิติจากฐานข้อมูล
try {
    // สถิติการจอง
    $stmt = $db->query("SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_bookings,
        SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in_bookings,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_bookings,
        SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END) as checked_out_bookings,
        SUM(net_amount) as total_revenue
    FROM bookings");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // สถิติผู้ใช้
    $stmt = $db->query("SELECT COUNT(*) as total_users FROM users");
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_users'] = $userStats['total_users'] ?? 0;

    // สถิติอาคาร
    $stmt = $db->query("SELECT COUNT(*) as total_buildings FROM buildings WHERE status = 'active'");
    $buildingStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_buildings'] = $buildingStats['total_buildings'] ?? 0;

    // สถิติห้องพัก
    $stmt = $db->query("SELECT 
        COUNT(*) as total_rooms,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_rooms,
        SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_rooms
    FROM rooms");
    $roomStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_rooms'] = $roomStats['total_rooms'] ?? 0;
    $stats['available_rooms'] = $roomStats['available_rooms'] ?? 0;
    $stats['occupied_rooms'] = $roomStats['occupied_rooms'] ?? 0;

    // รายได้เดือนนี้
    $currentMonth = date('Y-m');
    $stmt = $db->prepare("SELECT SUM(net_amount) as monthly_revenue 
                         FROM bookings 
                         WHERE DATE_FORMAT(created_at, '%Y-%m') = ? 
                         AND status IN ('approved', 'checked_in', 'checked_out')");
    $stmt->execute([$currentMonth]);
    $revenueStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['monthly_revenue'] = $revenueStats['monthly_revenue'] ?? 0;

    // ดึงการจองล่าสุด
    $stmt = $db->query("SELECT b.*, u.full_name as creator_name 
                       FROM bookings b 
                       LEFT JOIN users u ON b.created_by = u.id 
                       ORDER BY b.created_at DESC 
                       LIMIT 5");
    $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // ดึงสถานะอาคาร
    $stmt = $db->query("SELECT 
        b.*,
        COUNT(r.id) as total_rooms,
        SUM(CASE WHEN r.status = 'available' THEN 1 ELSE 0 END) as available_rooms
    FROM buildings b
    LEFT JOIN rooms r ON b.id = r.building_id
    WHERE b.status = 'active'
    GROUP BY b.id
    ORDER BY b.building_name
    LIMIT 4");
    $buildingStatus = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // ดึงสถิติรายเดือน
    $stmt = $db->query("SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as booking_count,
        SUM(net_amount) as revenue
    FROM bookings 
    WHERE status IN ('approved', 'checked_in', 'checked_out')
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month");
    $monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (Exception $e) {
    $error = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}

// ฟังก์ชันแปลงสถานะเป็นภาษาไทย
function getStatusThai($status) {
    $statuses = [
        'pending' => 'รออนุมัติ',
        'approved' => 'อนุมัติแล้ว',
        'rejected' => 'ไม่อนุมัติ',
        'checked_in' => 'เช็คอินแล้ว',
        'checked_out' => 'เช็คเอาต์แล้ว',
        'cancelled' => 'ยกเลิก'
    ];
    return $statuses[$status] ?? $status;
}

// ฟังก์ชันสำหรับ badge สถานะ
function getStatusBadge($status) {
    $badges = [
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'checked_in' => 'info',
        'checked_out' => 'secondary',
        'cancelled' => 'dark'
    ];
    return $badges[$status] ?? 'secondary';
}

// ฟังก์ชันสำหรับ badge สถานะอาคาร
function getBuildingStatusBadge($available, $total) {
    if ($total == 0) {
        return 'secondary';
    }
    
    $percentage = ($available / $total) * 100;
    
    if ($percentage >= 70) {
        return 'success';
    } elseif ($percentage >= 30) {
        return 'warning';
    } else {
        return 'danger';
    }
}

function getBuildingStatusColor($status) {
    $colors = [
        'active' => 'success',
        'maintenance' => 'warning',
        'inactive' => 'secondary'
    ];
    return $colors[$status] ?? 'secondary';
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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        .stat-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .welcome-card {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .quick-action-btn {
            background: white;
            border: none;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: inherit;
        }
        .quick-action-btn i {
            font-size: 24px;
            margin-bottom: 10px;
            color: #1e3c72;
        }
        .progress {
            height: 8px;
            border-radius: 4px;
        }
        .time-display {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.8);
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
            .quick-actions {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
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
                <span class="me-3 time-display" id="current-time">
                    <?php echo date('d/m/Y H:i'); ?>
                </span>
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
                    <a href="dashboard.php" class="list-group-item list-group-item-action active">
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
                    <a href="settings.php" class="list-group-item list-group-item-action">
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
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Welcome Message -->
                <div class="welcome-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2>ยินดีต้อนรับ, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                            <p class="mb-0">ผู้ดูแลระบบสามารถจัดการทุกส่วนของระบบผ่านเมนูด้านซ้ายมือ</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <i class="fas fa-hotel fa-4x opacity-50"></i>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">การจองทั้งหมด</h6>
                                        <h4 class="mb-0"><?php echo $stats['total_bookings'] ?? 0; ?></h4>
                                        <p class="mb-0 text-primary">
                                            <small>
                                                <i class="fas fa-arrow-up me-1"></i>
                                                <?php echo $stats['pending_bookings'] ?? 0; ?> รออนุมัติ
                                            </small>
                                        </p>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-alt fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-warning">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">รออนุมัติ</h6>
                                        <h4 class="mb-0"><?php echo $stats['pending_bookings'] ?? 0; ?></h4>
                                        <p class="mb-0 text-warning">
                                            <small><i class="fas fa-clock me-1"></i>รอตรวจสอบ</small>
                                        </p>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-success">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">รายได้เดือนนี้</h6>
                                        <h4 class="mb-0">฿<?php echo number_format($stats['monthly_revenue'] ?? 0, 2); ?></h4>
                                        <p class="mb-0 text-success">
                                            <small>
                                                <i class="fas fa-arrow-up me-1"></i>
                                                ฿<?php echo number_format(($stats['total_revenue'] ?? 0) / max(1, ($stats['total_bookings'] ?? 1)), 2); ?> เฉลี่ย/การจอง
                                            </small>
                                        </p>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-money-bill-wave fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-info">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">ผู้ใช้ระบบ</h6>
                                        <h4 class="mb-0"><?php echo $stats['total_users'] ?? 0; ?></h4>
                                        <p class="mb-0 text-info">
                                            <small>5 ระดับสิทธิ์</small>
                                        </p>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts and Data -->
                <div class="row">
                    <!-- Recent Bookings -->
                    <div class="col-lg-8">
                        <div class="chart-container">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">การจองล่าสุด</h5>
                                <div>
                                    <button class="btn btn-sm btn-outline-secondary me-2" onclick="refreshStats()" id="refreshBtn">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                    <a href="bookings.php" class="btn btn-sm btn-primary">ดูทั้งหมด</a>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>รหัสการจอง</th>
                                            <th>ผู้พัก</th>
                                            <th>วันที่</th>
                                            <th>สถานะ</th>
                                            <th>ดำเนินการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recentBookings)): ?>
                                            <?php foreach ($recentBookings as $booking): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($booking['booking_code'] ?? 'N/A'); ?></strong><br>
                                                    <small class="text-muted">
                                                        <?php 
                                                        $types = [
                                                            'official' => 'ราชการ',
                                                            'personal' => 'ส่วนตัว',
                                                            'training' => 'ฝึกอบรม',
                                                            'other' => 'อื่นๆ'
                                                        ];
                                                        echo $types[$booking['booking_type'] ?? 'other'] ?? 'อื่นๆ';
                                                        ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($booking['guest_name'] ?? 'N/A'); ?><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($booking['guest_phone'] ?? '-'); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y', strtotime($booking['check_in_date'] ?? date('Y-m-d'))); ?> -<br>
                                                    <?php echo date('d/m/Y', strtotime($booking['check_out_date'] ?? date('Y-m-d'))); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadge($booking['status'] ?? 'pending'); ?>">
                                                        <?php echo getStatusThai($booking['status'] ?? 'pending'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="bookings.php?view=<?php echo $booking['id'] ?? ''; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">ยังไม่มีข้อมูลการจอง</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Revenue Chart -->
                        <div class="chart-container mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">สถิติรายได้ 6 เดือนย้อนหลัง</h5>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="exportChart()">
                                        <i class="fas fa-download"></i> Export
                                    </button>
                                </div>
                            </div>
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <!-- Quick Actions and Stats -->
                    <div class="col-lg-4">
                        <!-- Quick Actions -->
                        <div class="chart-container">
                            <h5 class="mb-4">เมนูดำเนินการด่วน</h5>
                            <div class="quick-actions">
                                <a href="bookings.php?action=create" class="quick-action-btn">
                                    <i class="fas fa-plus"></i>
                                    <div class="fw-bold">สร้างการจอง</div>
                                    <small class="text-muted">เพิ่มการจองใหม่</small>
                                </a>
                                
                                <a href="buildings.php" class="quick-action-btn">
                                    <i class="fas fa-building"></i>
                                    <div class="fw-bold">จัดการอาคาร</div>
                                    <small class="text-muted">ดูและจัดการอาคาร</small>
                                </a>
                                
                                <a href="users.php" class="quick-action-btn">
                                    <i class="fas fa-user-plus"></i>
                                    <div class="fw-bold">เพิ่มผู้ใช้</div>
                                    <small class="text-muted">เพิ่มผู้ใช้ใหม่</small>
                                </a>
                                
                                <a href="reports.php" class="quick-action-btn">
                                    <i class="fas fa-file-export"></i>
                                    <div class="fw-bold">ส่งออกรายงาน</div>
                                    <small class="text-muted">Excel/PDF</small>
                                </a>
                                
                                <a href="settings.php" class="quick-action-btn">
                                    <i class="fas fa-cog"></i>
                                    <div class="fw-bold">ตั้งค่าระบบ</div>
                                    <small class="text-muted">ปรับแต่งระบบ</small>
                                </a>
                                
                                <a href="../general/booking_public.php" class="quick-action-btn">
                                    <i class="fas fa-external-link-alt"></i>
                                    <div class="fw-bold">จองสาธารณะ</div>
                                    <small class="text-muted">สำหรับบุคคลทั่วไป</small>
                                </a>
                            </div>
                        </div>

                        <!-- Building Status -->
                        <div class="chart-container mt-4">
                            <h5 class="mb-4">สถานะอาคาร</h5>
                            <?php if (!empty($buildingStatus)): ?>
                                <?php foreach ($buildingStatus as $building): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><?php echo htmlspecialchars($building['building_name'] ?? 'อาคาร'); ?></span>
                                        <span class="badge bg-<?php echo getBuildingStatusBadge($building['available_rooms'] ?? 0, $building['total_rooms'] ?? 1); ?>">
                                            <?php echo $building['available_rooms'] ?? 0; ?>/<?php echo $building['total_rooms'] ?? 0; ?> ห้อง
                                        </span>
                                    </div>
                                    <div class="progress">
                                        <?php 
                                        $totalRooms = $building['total_rooms'] ?? 1;
                                        $availableRooms = $building['available_rooms'] ?? 0;
                                        $percentage = 0;
                                        if ($totalRooms > 0) {
                                            $percentage = ($availableRooms / $totalRooms) * 100;
                                        }
                                        ?>
                                        <div class="progress-bar bg-<?php echo getBuildingStatusBadge($availableRooms, $totalRooms); ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%">
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center">ยังไม่มีข้อมูลอาคาร</p>
                            <?php endif; ?>
                            <div class="text-center mt-3">
                                <a href="buildings.php" class="btn btn-outline-primary btn-sm">ดูทั้งหมด</a>
                            </div>
                        </div>

                        <!-- System Stats -->
                        <div class="chart-container mt-4">
                            <h5 class="mb-4">สถิติระบบ</h5>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="p-3 bg-light rounded mb-2">
                                        <h4 class="mb-0 text-primary"><?php echo $stats['total_buildings'] ?? 0; ?></h4>
                                        <small>อาคาร</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3 bg-light rounded mb-2">
                                        <h4 class="mb-0 text-success"><?php echo $stats['available_rooms'] ?? 0; ?></h4>
                                        <small>ห้องว่าง</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3 bg-light rounded mb-2">
                                        <h4 class="mb-0 text-danger"><?php echo $stats['occupied_rooms'] ?? 0; ?></h4>
                                        <small>ห้องจอง</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>อัตราการใช้ห้องพัก</span>
                                    <span>
                                        <?php 
                                        $totalRooms = $stats['total_rooms'] ?? 0;
                                        $occupiedRooms = $stats['occupied_rooms'] ?? 0;
                                        $occupancyRate = 0;
                                        if ($totalRooms > 0) {
                                            $occupancyRate = ($occupiedRooms / $totalRooms) * 100;
                                        }
                                        echo number_format($occupancyRate, 1); ?>%
                                    </span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $occupancyRate; ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize revenue chart
        document.addEventListener('DOMContentLoaded', function() {
            // Prepare data for chart
            const monthlyData = <?php echo json_encode($monthlyStats); ?>;
            
            // Extract months and revenues
            const months = monthlyData.map(item => {
                const [year, month] = item.month.split('-');
                const monthNames = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
                                   'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                return monthNames[parseInt(month) - 1] + ' ' + (parseInt(year) + 543);
            });
            
            const revenues = monthlyData.map(item => parseFloat(item.revenue) || 0);
            const bookingCounts = monthlyData.map(item => parseInt(item.booking_count) || 0);
            
            // Create revenue chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            const revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'รายได้ (บาท)',
                        data: revenues,
                        borderColor: '#1e3c72',
                        backgroundColor: 'rgba(30, 60, 114, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    }, {
                        label: 'จำนวนการจอง',
                        data: bookingCounts,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    stacked: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label.includes('รายได้')) {
                                        return label + ': ฿' + context.parsed.y.toLocaleString('th-TH');
                                    } else {
                                        return label + ': ' + context.parsed.y.toLocaleString('th-TH') + ' รายการ';
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'รายได้ (บาท)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '฿' + value.toLocaleString('th-TH');
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'จำนวนการจอง'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('th-TH') + ' รายการ';
                                }
                            }
                        }
                    }
                }
            });
            
            // Update current time
            function updateCurrentTime() {
                const now = new Date();
                const options = {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                };
                
                const thaiDate = now.toLocaleDateString('th-TH', options);
                document.getElementById('current-time').textContent = thaiDate.replace(' ', ' ');
            }
            
            // Initial update
            updateCurrentTime();
            
            // Update time every minute
            setInterval(updateCurrentTime, 60000);
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Quick action button effects
        document.querySelectorAll('.quick-action-btn').forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
                this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.2)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 2px 5px rgba(0,0,0,0.1)';
            });
        });

        // Refresh statistics button
        function refreshStats() {
            const refreshBtn = document.getElementById('refreshBtn');
            if (refreshBtn) {
                const originalHtml = refreshBtn.innerHTML;
                refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                refreshBtn.disabled = true;
                
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }
        }

        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('d-none');
                mainContent.classList.toggle('col-12');
            }
        }

        // Print dashboard
        function printDashboard() {
            window.print();
        }

        // Export chart as image
        function exportChart() {
            const canvas = document.getElementById('revenueChart');
            const link = document.createElement('a');
            link.download = 'รายงานรายได้-' + new Date().toISOString().slice(0,10) + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        }

        // Export dashboard data
        function exportDashboard() {
            alert('กำลังเตรียมข้อมูลสำหรับส่งออก...');
            // In a real application, you would implement PDF export here
        }

        // Mobile menu toggle
        function toggleMobileMenu() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('d-none');
                mainContent.classList.toggle('col-12');
            }
        }
    </script>
</body>
</html>