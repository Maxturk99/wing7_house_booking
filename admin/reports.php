<?php
/**
 * Reports and Statistics Page
 * หน้ารายงานและสถิติ
 */

session_start();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'commander', 'approver'])) {
    header('Location: ../login.php?error=กรุณาล็อกอินเพื่อเข้าถึงระบบ');
    exit();
}

$pageTitle = "รายงานและสถิติ";
$userRole = $_SESSION['user_role'];

// เชื่อมต่อฐานข้อมูล
require_once '../config/database.php';
$db = getDatabaseConnection();

// ตัวแปรสำหรับตัวกรอง
$filters = [
    'report_type' => $_GET['report_type'] ?? 'booking_summary',
    'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
    'building_id' => $_GET['building_id'] ?? '',
    'status' => $_GET['status'] ?? ''
];

// ดึงข้อมูลอาคาร
$buildingsStmt = $db->query("SELECT * FROM buildings WHERE status = 'active' ORDER BY building_name");
$buildings = $buildingsStmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลสถิติตามตัวกรอง
$stats = [];
$reportData = [];

try {
    // สถิติการจองทั้งหมด
    $sql = "SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_bookings,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_bookings,
                SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in_bookings,
                SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END) as checked_out_bookings,
                SUM(net_amount) as total_revenue
            FROM bookings 
            WHERE DATE(created_at) BETWEEN :date_from AND :date_to";
    
    $params = [':date_from' => $filters['date_from'], ':date_to' => $filters['date_to']];
    
    if (!empty($filters['building_id'])) {
        $sql .= " AND id IN (SELECT DISTINCT booking_id FROM booking_rooms WHERE building_id = :building_id)";
        $params[':building_id'] = $filters['building_id'];
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND status = :status";
        $params[':status'] = $filters['status'];
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // รายงานรายเดือน
    if ($filters['report_type'] === 'monthly_summary') {
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as booking_count,
                    SUM(net_amount) as revenue,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
                FROM bookings 
                WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC";
        
        $stmt = $db->query($sql);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // รายงานรายอาคาร
    elseif ($filters['report_type'] === 'building_report') {
        $sql = "SELECT 
                    b.id,
                    b.building_name,
                    b.building_code,
                    COUNT(br.booking_id) as booking_count,
                    SUM(CASE WHEN bk.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(bk.net_amount) as revenue,
                    COUNT(DISTINCT br.room_id) as rooms_used
                FROM buildings b
                LEFT JOIN booking_rooms br ON b.id = br.building_id
                LEFT JOIN bookings bk ON br.booking_id = bk.id
                    AND DATE(bk.created_at) BETWEEN :date_from AND :date_to
                GROUP BY b.id, b.building_name, b.building_code
                ORDER BY booking_count DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':date_from' => $filters['date_from'], ':date_to' => $filters['date_to']]);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // รายงานรายประเภท
    elseif ($filters['report_type'] === 'type_report') {
        $sql = "SELECT 
                    booking_type,
                    COUNT(*) as count,
                    SUM(net_amount) as revenue,
                    AVG(net_amount) as avg_revenue
                FROM bookings 
                WHERE DATE(created_at) BETWEEN :date_from AND :date_to
                GROUP BY booking_type
                ORDER BY count DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':date_from' => $filters['date_from'], ':date_to' => $filters['date_to']]);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // รายงานการจอง
    elseif ($filters['report_type'] === 'booking_details') {
        $sql = "SELECT 
                    bk.*,
                    u.full_name as creator_name,
                    a.full_name as approver_name,
                    GROUP_CONCAT(DISTINCT CONCAT(b.building_name, ' (', r.room_number, ')') SEPARATOR ', ') as rooms
                FROM bookings bk
                LEFT JOIN users u ON bk.created_by = u.id
                LEFT JOIN users a ON bk.approved_by = a.id
                LEFT JOIN booking_rooms br ON bk.id = br.booking_id
                LEFT JOIN rooms r ON br.room_id = r.id
                LEFT JOIN buildings b ON br.building_id = b.id
                WHERE DATE(bk.created_at) BETWEEN :date_from AND :date_to";
        
        $params = [':date_from' => $filters['date_from'], ':date_to' => $filters['date_to']];
        
        if (!empty($filters['building_id'])) {
            $sql .= " AND b.id = :building_id";
            $params[':building_id'] = $filters['building_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND bk.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        $sql .= " GROUP BY bk.id ORDER BY bk.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}

// ฟังก์ชันแปลงชื่อรายงาน
function getReportTypeName($type) {
    $types = [
        'booking_summary' => 'สรุปการจอง',
        'monthly_summary' => 'รายงานรายเดือน',
        'building_report' => 'รายงานรายอาคาร',
        'type_report' => 'รายงานรายประเภท',
        'booking_details' => 'รายละเอียดการจอง'
    ];
    return $types[$type] ?? $type;
}

// ฟังก์ชันแปลงประเภทการจอง
function getBookingTypeName($type) {
    $types = [
        'official' => 'ราชการ',
        'personal' => 'ส่วนตัว',
        'training' => 'ฝึกอบรม',
        'other' => 'อื่นๆ'
    ];
    return $types[$type] ?? $type;
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
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
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
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .export-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-bottom: 20px;
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
                    <?php if ($userRole === 'admin'): ?>
                    <a href="users.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i>จัดการผู้ใช้
                    </a>
                    <?php endif; ?>
                    <a href="reports.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-chart-bar me-2"></i>รายงานและสถิติ
                    </a>
                    <?php if ($userRole === 'admin'): ?>
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
                    <h2><i class="fas fa-chart-bar me-2"></i><?php echo $pageTitle; ?></h2>
                    <div class="export-buttons">
                        <button class="btn btn-success" onclick="exportReport('excel')">
                            <i class="fas fa-file-excel me-2"></i>Excel
                        </button>
                        <button class="btn btn-danger" onclick="exportReport('pdf')">
                            <i class="fas fa-file-pdf me-2"></i>PDF
                        </button>
                        <button class="btn btn-secondary" onclick="printReport()">
                            <i class="fas fa-print me-2"></i>พิมพ์
                        </button>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <h5><i class="fas fa-filter me-2"></i>ตัวกรองรายงาน</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="report_type" class="form-label">ประเภทรายงาน</label>
                            <select name="report_type" id="report_type" class="form-select" onchange="this.form.submit()">
                                <option value="booking_summary" <?php echo $filters['report_type'] === 'booking_summary' ? 'selected' : ''; ?>>สรุปการจอง</option>
                                <option value="monthly_summary" <?php echo $filters['report_type'] === 'monthly_summary' ? 'selected' : ''; ?>>รายงานรายเดือน</option>
                                <option value="building_report" <?php echo $filters['report_type'] === 'building_report' ? 'selected' : ''; ?>>รายงานรายอาคาร</option>
                                <option value="type_report" <?php echo $filters['report_type'] === 'type_report' ? 'selected' : ''; ?>>รายงานรายประเภท</option>
                                <option value="booking_details" <?php echo $filters['report_type'] === 'booking_details' ? 'selected' : ''; ?>>รายละเอียดการจอง</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">วันที่เริ่มต้น</label>
                            <input type="date" name="date_from" id="date_from" class="form-control" 
                                   value="<?php echo $filters['date_from']; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">วันที่สิ้นสุด</label>
                            <input type="date" name="date_to" id="date_to" class="form-control" 
                                   value="<?php echo $filters['date_to']; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">สถานะ</label>
                            <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                                <option value="">ทั้งหมด</option>
                                <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>รออนุมัติ</option>
                                <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                                <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>ไม่อนุมัติ</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="building_id" class="form-label">อาคาร</label>
                            <select name="building_id" id="building_id" class="form-select" onchange="this.form.submit()">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($buildings as $building): ?>
                                <option value="<?php echo $building['id']; ?>" <?php echo $filters['building_id'] == $building['id'] ? 'selected' : ''; ?>>
                                    <?php echo $building['building_name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <!-- Report Title -->
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <h4>รายงาน: <?php echo getReportTypeName($filters['report_type']); ?></h4>
                        <p class="text-muted mb-0">
                            วันที่ <?php echo date('d/m/Y', strtotime($filters['date_from'])); ?> 
                            ถึง <?php echo date('d/m/Y', strtotime($filters['date_to'])); ?>
                        </p>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card border-left-primary">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">การจองทั้งหมด</h6>
                                        <h4 class="mb-0"><?php echo $stats['total_bookings'] ?? 0; ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-alt fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card border-left-success">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">อนุมัติแล้ว</h6>
                                        <h4 class="mb-0"><?php echo $stats['approved_bookings'] ?? 0; ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card border-left-warning">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">รออนุมัติ</h6>
                                        <h4 class="mb-0"><?php echo $stats['pending_bookings'] ?? 0; ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card border-left-info">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">รายได้ทั้งหมด</h6>
                                        <h4 class="mb-0">฿<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-money-bill-wave fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h5>สถิติการจอง</h5>
                            <canvas id="bookingChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h5>รายได้รายเดือน</h5>
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Report Data -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">ข้อมูลรายงาน</h5>
                        <div class="table-responsive">
                            <?php if ($filters['report_type'] === 'booking_summary'): ?>
                            <table class="table table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>สถานะ</th>
                                        <th>จำนวน</th>
                                        <th>ร้อยละ</th>
                                        <th>รายได้</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="badge bg-warning">รออนุมัติ</span></td>
                                        <td><?php echo $stats['pending_bookings'] ?? 0; ?></td>
                                        <td>
                                            <?php 
                                            $total = $stats['total_bookings'] ?? 1;
                                            $percent = ($stats['pending_bookings'] ?? 0) / $total * 100;
                                            echo number_format($percent, 1) . '%';
                                            ?>
                                        </td>
                                        <td>-</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-success">อนุมัติแล้ว</span></td>
                                        <td><?php echo $stats['approved_bookings'] ?? 0; ?></td>
                                        <td>
                                            <?php 
                                            $percent = ($stats['approved_bookings'] ?? 0) / $total * 100;
                                            echo number_format($percent, 1) . '%';
                                            ?>
                                        </td>
                                        <td>฿<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-danger">ไม่อนุมัติ</span></td>
                                        <td><?php echo $stats['rejected_bookings'] ?? 0; ?></td>
                                        <td>
                                            <?php 
                                            $percent = ($stats['rejected_bookings'] ?? 0) / $total * 100;
                                            echo number_format($percent, 1) . '%';
                                            ?>
                                        </td>
                                        <td>-</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-info">เช็คอินแล้ว</span></td>
                                        <td><?php echo $stats['checked_in_bookings'] ?? 0; ?></td>
                                        <td>
                                            <?php 
                                            $percent = ($stats['checked_in_bookings'] ?? 0) / $total * 100;
                                            echo number_format($percent, 1) . '%';
                                            ?>
                                        </td>
                                        <td>-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>รวม</strong></td>
                                        <td><strong><?php echo $stats['total_bookings'] ?? 0; ?></strong></td>
                                        <td><strong>100%</strong></td>
                                        <td><strong>฿<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <?php elseif ($filters['report_type'] === 'monthly_summary'): ?>
                            <table class="table table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>เดือน</th>
                                        <th>จำนวนการจอง</th>
                                        <th>อนุมัติ</th>
                                        <th>ไม่อนุมัติ</th>
                                        <th>รายได้</th>
                                        <th>ค่าเฉลี่ย</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $data): ?>
                                    <tr>
                                        <td><?php echo date('F Y', strtotime($data['month'] . '-01')); ?></td>
                                        <td><?php echo $data['booking_count']; ?></td>
                                        <td><?php echo $data['approved_count']; ?></td>
                                        <td><?php echo $data['rejected_count']; ?></td>
                                        <td>฿<?php echo number_format($data['revenue'] ?? 0, 2); ?></td>
                                        <td>
                                            <?php 
                                            $avg = $data['booking_count'] > 0 ? ($data['revenue'] ?? 0) / $data['booking_count'] : 0;
                                            echo '฿' . number_format($avg, 2);
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php elseif ($filters['report_type'] === 'building_report'): ?>
                            <table class="table table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>อาคาร</th>
                                        <th>รหัส</th>
                                        <th>จำนวนการจอง</th>
                                        <th>อนุมัติ</th>
                                        <th>จำนวนห้องที่ใช้</th>
                                        <th>รายได้</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $data): ?>
                                    <tr>
                                        <td><?php echo $data['building_name']; ?></td>
                                        <td><?php echo $data['building_code']; ?></td>
                                        <td><?php echo $data['booking_count']; ?></td>
                                        <td><?php echo $data['approved_count']; ?></td>
                                        <td><?php echo $data['rooms_used']; ?></td>
                                        <td>฿<?php echo number_format($data['revenue'] ?? 0, 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php elseif ($filters['report_type'] === 'type_report'): ?>
                            <table class="table table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>ประเภทการจอง</th>
                                        <th>จำนวน</th>
                                        <th>ร้อยละ</th>
                                        <th>รายได้</th>
                                        <th>ค่าเฉลี่ย/รายการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $data): ?>
                                    <tr>
                                        <td><?php echo getBookingTypeName($data['booking_type']); ?></td>
                                        <td><?php echo $data['count']; ?></td>
                                        <td>
                                            <?php 
                                            $total = array_sum(array_column($reportData, 'count'));
                                            $percent = $total > 0 ? ($data['count'] / $total * 100) : 0;
                                            echo number_format($percent, 1) . '%';
                                            ?>
                                        </td>
                                        <td>฿<?php echo number_format($data['revenue'] ?? 0, 2); ?></td>
                                        <td>฿<?php echo number_format($data['avg_revenue'] ?? 0, 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php elseif ($filters['report_type'] === 'booking_details'): ?>
                            <table class="table table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>รหัสการจอง</th>
                                        <th>ผู้พัก</th>
                                        <th>วันที่จอง</th>
                                        <th>อาคาร/ห้อง</th>
                                        <th>สถานะ</th>
                                        <th>จำนวนเงิน</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $data): ?>
                                    <tr>
                                        <td><?php echo $data['booking_code']; ?></td>
                                        <td>
                                            <?php echo $data['guest_name']; ?><br>
                                            <small class="text-muted"><?php echo $data['guest_phone']; ?></small>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($data['check_in_date'])); ?><br>
                                            <small class="text-muted">ถึง <?php echo date('d/m/Y', strtotime($data['check_out_date'])); ?></small>
                                        </td>
                                        <td><?php echo $data['rooms'] ?? '-'; ?></td>
                                        <td>
                                            <?php 
                                            $badges = [
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'rejected' => 'danger',
                                                'checked_in' => 'info',
                                                'checked_out' => 'secondary'
                                            ];
                                            $statusText = [
                                                'pending' => 'รออนุมัติ',
                                                'approved' => 'อนุมัติแล้ว',
                                                'rejected' => 'ไม่อนุมัติ',
                                                'checked_in' => 'เช็คอินแล้ว',
                                                'checked_out' => 'เช็คเอาต์แล้ว'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $badges[$data['status']] ?? 'secondary'; ?>">
                                                <?php echo $statusText[$data['status']] ?? $data['status']; ?>
                                            </span>
                                        </td>
                                        <td>฿<?php echo number_format($data['net_amount'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#reportTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json'
                },
                pageLength: 10,
                order: [[0, 'desc']],
                dom: 'Bfrtip'
            });
            
            // Initialize charts
            initializeCharts();
        });

        // Initialize charts
        function initializeCharts() {
            // Booking Status Chart
            const bookingCtx = document.getElementById('bookingChart').getContext('2d');
            const bookingChart = new Chart(bookingCtx, {
                type: 'doughnut',
                data: {
                    labels: ['รออนุมัติ', 'อนุมัติแล้ว', 'ไม่อนุมัติ', 'เช็คอินแล้ว', 'เช็คเอาต์แล้ว'],
                    datasets: [{
                        data: [
                            <?php echo $stats['pending_bookings'] ?? 0; ?>,
                            <?php echo $stats['approved_bookings'] ?? 0; ?>,
                            <?php echo $stats['rejected_bookings'] ?? 0; ?>,
                            <?php echo $stats['checked_in_bookings'] ?? 0; ?>,
                            <?php echo $stats['checked_out_bookings'] ?? 0; ?>
                        ],
                        backgroundColor: [
                            '#ffc107',
                            '#28a745',
                            '#dc3545',
                            '#17a2b8',
                            '#6c757d'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        title: {
                            display: true,
                            text: 'สถานะการจอง'
                        }
                    }
                }
            });

            // Revenue Chart (sample data - you should replace with actual data)
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            const revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],
                    datasets: [{
                        label: 'รายได้',
                        data: [45000, 52000, 49000, 62000, 58000, 75000, 82000, 78000, 85000, 90000, 95000, 100000],
                        borderColor: '#1e3c72',
                        backgroundColor: 'rgba(30, 60, 114, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'รายได้รายเดือน'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '฿' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        // Export functions
        function exportReport(format) {
            const reportType = '<?php echo $filters['report_type']; ?>';
            const dateFrom = '<?php echo $filters['date_from']; ?>';
            const dateTo = '<?php echo $filters['date_to']; ?>';
            const buildingId = '<?php echo $filters['building_id']; ?>';
            const status = '<?php echo $filters['status']; ?>';
            
            let url = `export_report.php?format=${format}&report_type=${reportType}&date_from=${dateFrom}&date_to=${dateTo}`;
            
            if (buildingId) {
                url += `&building_id=${buildingId}`;
            }
            
            if (status) {
                url += `&status=${status}`;
            }
            
            window.open(url, '_blank');
        }

        function printReport() {
            window.print();
        }
    </script>
</body>
</html>