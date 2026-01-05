<?php
/**
 * Bookings Management Page
 * หน้าจัดการการจอง
 */

session_start();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header('Location: ../login.php?error=กรุณาล็อกอินเพื่อเข้าถึงระบบ');
    exit();
}

$pageTitle = "จัดการการจอง";
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];

// เชื่อมต่อฐานข้อมูล
require_once '../config/database.php';
$db = getDatabaseConnection();

// ตรวจสอบการดำเนินการ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = [];
    
    try {
        switch ($action) {
            case 'create_booking':
                // ตรวจสอบข้อมูลที่จำเป็น
                $required = ['guest_name', 'guest_phone', 'check_in_date', 'check_out_date'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("กรุณากรอกข้อมูลให้ครบ: $field");
                    }
                }

                // ตรวจสอบวันที่
                $checkInDate = new DateTime($_POST['check_in_date']);
                $checkOutDate = new DateTime($_POST['check_out_date']);
                
                if ($checkInDate >= $checkOutDate) {
                    throw new Exception("วันที่เช็คเอาต์ต้องมากกว่าวันที่เช็คอิน");
                }

                // สร้างรหัสการจอง
                $bookingCode = 'BK' . date('Ymd') . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);

                // สร้างการจอง
                $stmt = $db->prepare("INSERT INTO bookings (
                    booking_code, guest_name, guest_phone, guest_email, 
                    guest_department, check_in_date, check_out_date, 
                    number_of_guests, purpose, booking_type, special_request,
                    status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");

                $success = $stmt->execute([
                    $bookingCode,
                    $_POST['guest_name'],
                    $_POST['guest_phone'],
                    $_POST['guest_email'] ?? '',
                    $_POST['guest_department'] ?? '',
                    $_POST['check_in_date'],
                    $_POST['check_out_date'],
                    $_POST['number_of_guests'] ?? 1,
                    $_POST['purpose'] ?? '',
                    $_POST['booking_type'] ?? 'official',
                    $_POST['special_request'] ?? '',
                    $userId
                ]);

                if ($success) {
                    $bookingId = $db->lastInsertId();
                    $response = ['success' => true, 'message' => 'สร้างการจองสำเร็จ', 'booking_id' => $bookingId];
                } else {
                    throw new Exception("ไม่สามารถสร้างการจองได้");
                }
                break;

            case 'cancel_booking':
                if (empty($_POST['id'])) {
                    throw new Exception("ไม่ระบุ ID การจอง");
                }

                $stmt = $db->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);

                if ($success) {
                    $response = ['success' => true, 'message' => 'ยกเลิกการจองสำเร็จ'];
                } else {
                    throw new Exception("ไม่สามารถยกเลิกการจองได้");
                }
                break;

            case 'delete_booking':
                if ($userRole !== 'admin') {
                    throw new Exception("เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถลบการจองได้");
                }

                if (empty($_POST['id'])) {
                    throw new Exception("ไม่ระบุ ID การจอง");
                }

                $stmt = $db->prepare("DELETE FROM bookings WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);

                if ($success) {
                    $response = ['success' => true, 'message' => 'ลบการจองสำเร็จ'];
                } else {
                    throw new Exception("ไม่สามารถลบการจองได้");
                }
                break;

            default:
                throw new Exception("ไม่ระบุการดำเนินการ");
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// ตัวแปรสำหรับการกรอง
$filters = [
    'status' => $_GET['status'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// ดึงข้อมูลการจอง
$sql = "SELECT b.*, u.full_name as creator_name, a.full_name as approver_name 
        FROM bookings b 
        LEFT JOIN users u ON b.created_by = u.id 
        LEFT JOIN users a ON b.approved_by = a.id 
        WHERE 1=1";
$params = [];

if (!empty($filters['status'])) {
    $sql .= " AND b.status = :status";
    $params[':status'] = $filters['status'];
}

if (!empty($filters['date_from'])) {
    $sql .= " AND b.check_in_date >= :date_from";
    $params[':date_from'] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $sql .= " AND b.check_out_date <= :date_to";
    $params[':date_to'] = $filters['date_to'];
}

if (!empty($filters['search'])) {
    $sql .= " AND (b.guest_name LIKE :search OR b.booking_code LIKE :search OR b.guest_phone LIKE :search)";
    $params[':search'] = "%{$filters['search']}%";
}

// สำหรับ staff ให้แสดงเฉพาะที่ตัวเองสร้าง
if ($userRole === 'staff') {
    $sql .= " AND b.created_by = :user_id";
    $params[':user_id'] = $userId;
}

$sql .= " ORDER BY b.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงอาคารทั้งหมด
$buildingsStmt = $db->query("SELECT * FROM buildings WHERE status = 'active' ORDER BY building_name");
$buildings = $buildingsStmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงห้องทั้งหมด
$roomsStmt = $db->query("SELECT r.*, b.building_name, b.building_code 
                         FROM rooms r 
                         JOIN buildings b ON r.building_id = b.id 
                         WHERE r.status IN ('available', 'occupied', 'cleaning', 'maintenance')
                         ORDER BY b.building_name, r.room_number");
$allRooms = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);

// ฟังก์ชันแปลงสถานะเป็นภาษาไทย
function getStatusThai($status) {
    $statusMap = [
        'pending' => 'รออนุมัติ',
        'approved' => 'อนุมัติแล้ว',
        'rejected' => 'ไม่อนุมัติ',
        'checked_in' => 'เช็คอินแล้ว',
        'checked_out' => 'เช็คเอาต์แล้ว',
        'cancelled' => 'ยกเลิก'
    ];
    return $statusMap[$status] ?? $status;
}

// ฟังก์ชันแปลงประเภทการจอง
function getBookingTypeThai($type) {
    $typeMap = [
        'official' => 'ราชการ',
        'personal' => 'ส่วนตัว',
        'training' => 'ฝึกอบรม',
        'other' => 'อื่นๆ'
    ];
    return $typeMap[$type] ?? $type;
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

// ฟังก์ชันคำนวณจำนวนคืน
function calculateNights($checkIn, $checkOut) {
    $checkInDate = new DateTime($checkIn);
    $checkOutDate = new DateTime($checkOut);
    $interval = $checkInDate->diff($checkOutDate);
    return $interval->days;
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
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(30, 60, 114, 0.05);
        }
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .action-buttons .btn {
            padding: 3px 8px;
            margin: 2px;
        }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .room-selection-item {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
        }
        .room-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            margin-right: 5px;
        }
        .available {
            background-color: #d4edda;
            color: #155724;
        }
        .occupied {
            background-color: #f8d7da;
            color: #721c24;
        }
        .cleaning {
            background-color: #fff3cd;
            color: #856404;
        }
        .maintenance {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .date-inputs {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .date-inputs i {
            color: #6c757d;
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
                    <a href="bookings.php" class="list-group-item list-group-item-action active">
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
                    <a href="reports.php" class="list-group-item list-group-item-action">
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
                    <h2><i class="fas fa-calendar-check me-2"></i><?php echo htmlspecialchars($pageTitle); ?></h2>
                    <button class="btn btn-primary" onclick="showCreateBookingModal()">
                        <i class="fas fa-plus me-2"></i>สร้างการจองใหม่
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card border-left-primary">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">ทั้งหมด</h6>
                                        <h4 class="mb-0"><?php echo count($bookings); ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-alt fa-2x text-primary"></i>
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
                                        <h4 class="mb-0"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'pending')); ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-warning"></i>
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
                                        <h4 class="mb-0"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'approved')); ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-success"></i>
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
                                        <h6 class="text-muted mb-1">กำลังพัก</h6>
                                        <h4 class="mb-0"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'checked_in')); ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-bed fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section mb-4">
                    <h5><i class="fas fa-filter me-2"></i>ตัวกรองข้อมูล</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">สถานะ</label>
                            <select name="status" id="status" class="form-select">
                                <option value="">ทั้งหมด</option>
                                <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>รออนุมัติ</option>
                                <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                                <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>ไม่อนุมัติ</option>
                                <option value="checked_in" <?php echo $filters['status'] === 'checked_in' ? 'selected' : ''; ?>>กำลังพัก</option>
                                <option value="checked_out" <?php echo $filters['status'] === 'checked_out' ? 'selected' : ''; ?>>เช็คเอาต์แล้ว</option>
                                <option value="cancelled" <?php echo $filters['status'] === 'cancelled' ? 'selected' : ''; ?>>ยกเลิก</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">วันที่เริ่มต้น</label>
                            <input type="date" name="date_from" id="date_from" class="form-control" 
                                   value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">วันที่สิ้นสุด</label>
                            <input type="date" name="date_to" id="date_to" class="form-control" 
                                   value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="search" class="form-label">ค้นหา</label>
                            <input type="text" name="search" id="search" class="form-control" 
                                   placeholder="ชื่อผู้พัก, เบอร์โทร, รหัสการจอง" 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="bookings.php" class="btn btn-secondary">ล้างตัวกรอง</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>ค้นหา
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Bookings Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="bookingsTable">
                                <thead>
                                    <tr>
                                        <th>รหัสการจอง</th>
                                        <th>ผู้พัก</th>
                                        <th>วันที่จอง</th>
                                        <th>จำนวนคืน</th>
                                        <th>สถานะ</th>
                                        <th>สร้างโดย</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong><br>
                                            <small class="text-muted"><?php echo getBookingTypeThai($booking['booking_type']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($booking['guest_phone']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($booking['check_in_date'])); ?><br>
                                            <small class="text-muted">ถึง <?php echo date('d/m/Y', strtotime($booking['check_out_date'])); ?></small>
                                        </td>
                                        <td>
                                            <?php echo calculateNights($booking['check_in_date'], $booking['check_out_date']); ?> คืน
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadge($booking['status']); ?>">
                                                <?php echo getStatusThai($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['creator_name'] ?? '-'); ?><br>
                                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($booking['created_at'])); ?></small>
                                        </td>
                                        <td class="action-buttons">
                                            <button class="btn btn-sm btn-info" onclick="viewBooking(<?php echo $booking['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (($booking['status'] === 'pending' && $booking['created_by'] == $userId) || $userRole === 'admin'): ?>
                                            <button class="btn btn-sm btn-warning" onclick="editBooking(<?php echo $booking['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($booking['status'] === 'pending'): ?>
                                            <button class="btn btn-sm btn-danger" onclick="cancelBooking(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars(addslashes($booking['booking_code'])); ?>')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($userRole === 'admin'): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteBooking(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars(addslashes($booking['booking_code'])); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Booking Modal -->
    <div class="modal fade" id="createBookingModal" tabindex="-1" aria-labelledby="createBookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createBookingModalLabel"><i class="fas fa-plus me-2"></i>สร้างการจองใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="createBookingForm" action="bookings.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_booking">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="guest_name" class="form-label">ชื่อผู้พัก *</label>
                                <input type="text" class="form-control" id="guest_name" name="guest_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="guest_phone" class="form-label">เบอร์ติดต่อ *</label>
                                <input type="tel" class="form-control" id="guest_phone" name="guest_phone" pattern="[0-9]{10}" required>
                                <div class="form-text">กรุณากรอกเบอร์โทรศัพท์ 10 หลัก</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="guest_email" class="form-label">อีเมล</label>
                                <input type="email" class="form-control" id="guest_email" name="guest_email">
                            </div>
                            <div class="col-md-6">
                                <label for="guest_department" class="form-label">สังกัด/หน่วยงาน</label>
                                <input type="text" class="form-control" id="guest_department" name="guest_department">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="check_in_date" class="form-label">วันที่เช็คอิน *</label>
                                <div class="date-inputs">
                                    <input type="date" class="form-control" id="check_in_date" name="check_in_date" required>
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="check_out_date" class="form-label">วันที่เช็คเอาต์ *</label>
                                <div class="date-inputs">
                                    <input type="date" class="form-control" id="check_out_date" name="check_out_date" required>
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="booking_type" class="form-label">ประเภทการจอง</label>
                                <select class="form-select" id="booking_type" name="booking_type">
                                    <option value="official">ราชการ</option>
                                    <option value="personal">ส่วนตัว</option>
                                    <option value="training">ฝึกอบรม</option>
                                    <option value="other">อื่นๆ</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="number_of_guests" class="form-label">จำนวนผู้พัก</label>
                                <input type="number" class="form-control" id="number_of_guests" name="number_of_guests" min="1" value="1">
                            </div>
                            <div class="col-md-6">
                                <label for="purpose" class="form-label">วัตถุประสงค์</label>
                                <input type="text" class="form-control" id="purpose" name="purpose" placeholder="เช่น ประชุม, ฝึกอบรม, เดินทางราชการ">
                            </div>
                        </div>
                        
                        <!-- Room Selection Section -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label">เลือกห้องพัก</label>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="showRoomSelectionModal()">
                                    <i class="fas fa-plus me-1"></i>เลือกห้องพัก
                                </button>
                            </div>
                            <div id="selectedRoomsContainer">
                                <!-- Selected rooms will be displayed here -->
                                <p class="text-muted">ยังไม่ได้เลือกห้องพัก</p>
                            </div>
                            <input type="hidden" id="selectedRoomIds" name="selected_room_ids" value="">
                        </div>
                        
                        <div class="mb-3">
                            <label for="special_request" class="form-label">คำขอพิเศษ</label>
                            <textarea class="form-control" id="special_request" name="special_request" rows="3" placeholder="เช่น ต้องการเตียงเสริม, อาหารพิเศษ"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">บันทึกการจอง</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<!-- เพิ่ม modal สำหรับแก้ไขการจอง -->
<div class="modal fade" id="editBookingModal" tabindex="-1" aria-labelledby="editBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBookingModalLabel"><i class="fas fa-edit me-2"></i>แก้ไขการจอง</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editBookingForm">
                <div class="modal-body" id="editBookingContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Room Selection Modal -->
    <div class="modal fade" id="roomSelectionModal" tabindex="-1" aria-labelledby="roomSelectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="roomSelectionModalLabel">เลือกห้องพัก</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="room_check_in" class="form-label">วันที่เช็คอิน</label>
                            <input type="date" class="form-control" id="room_check_in" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="room_check_out" class="form-label">วันที่เช็คเอาต์</label>
                            <input type="date" class="form-control" id="room_check_out" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </div>
                    </div>
                    
                    <div class="row" id="availableRoomsContainer">
                        <!-- Available rooms will be loaded here -->
                        <?php 
                        // Group rooms by building
                        $roomsByBuilding = [];
                        foreach ($allRooms as $room) {
                            $buildingId = $room['building_id'];
                            if (!isset($roomsByBuilding[$buildingId])) {
                                $roomsByBuilding[$buildingId] = [
                                    'building_name' => $room['building_name'],
                                    'building_code' => $room['building_code'],
                                    'rooms' => []
                                ];
                            }
                            $roomsByBuilding[$buildingId]['rooms'][] = $room;
                        }
                        
                        foreach ($roomsByBuilding as $buildingId => $building): 
                        ?>
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($building['building_name']); ?> (<?php echo htmlspecialchars($building['building_code']); ?>)</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php foreach ($building['rooms'] as $room): 
                                            $statusClass = '';
                                            switch ($room['status']) {
                                                case 'available': $statusClass = 'available'; break;
                                                case 'occupied': $statusClass = 'occupied'; break;
                                                case 'cleaning': $statusClass = 'cleaning'; break;
                                                case 'maintenance': $statusClass = 'maintenance'; break;
                                            }
                                        ?>
                                        <div class="col-6 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input room-checkbox" type="checkbox" 
                                                       id="room_<?php echo $room['id']; ?>" 
                                                       value="<?php echo $room['id']; ?>"
                                                       data-room-number="<?php echo htmlspecialchars($room['room_number']); ?>"
                                                       data-building-name="<?php echo htmlspecialchars($room['building_name']); ?>"
                                                       data-room-name="<?php echo htmlspecialchars($room['room_name']); ?>"
                                                       data-price="<?php echo $room['price_per_night']; ?>"
                                                       data-status="<?php echo $room['status']; ?>"
                                                       <?php echo $room['status'] !== 'available' ? 'disabled' : ''; ?>>
                                                <label class="form-check-label" for="room_<?php echo $room['id']; ?>">
                                                    <?php echo htmlspecialchars($room['room_number']); ?>
                                                    <?php if ($room['room_name']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($room['room_name']); ?></small>
                                                    <?php endif; ?>
                                                    <span class="room-badge <?php echo $statusClass; ?> ms-1">
                                                        <?php 
                                                        $statusText = [
                                                            'available' => 'ว่าง',
                                                            'occupied' => 'จองแล้ว',
                                                            'cleaning' => 'ทำความสะอาด',
                                                            'maintenance' => 'ซ่อมบำรุง'
                                                        ];
                                                        echo $statusText[$room['status']] ?? $room['status'];
                                                        ?>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="button" class="btn btn-primary" onclick="addSelectedRooms()">เพิ่มห้องที่เลือก</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Booking Modal -->
    <div class="modal fade" id="viewBookingModal" tabindex="-1" aria-labelledby="viewBookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewBookingModalLabel">รายละเอียดการจอง</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewBookingContent">
                    <!-- Content will be loaded via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#bookingsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json'
                },
                pageLength: 10,
                order: [[0, 'desc']]
            });
            
            // Handle create booking form submission
            $('#createBookingForm').on('submit', function(e) {
                e.preventDefault();
                submitBookingForm($(this), 'สร้างการจอง');
            });
        });

        // Show create booking modal
        function showCreateBookingModal() {
            $('#createBookingForm')[0].reset();
            $('#selectedRoomsContainer').html('<p class="text-muted">ยังไม่ได้เลือกห้องพัก</p>');
            $('#selectedRoomIds').val('');
            $('#createBookingModal').modal('show');
        }

        // Show room selection modal
        function showRoomSelectionModal() {
            const checkIn = $('#check_in_date').val();
            const checkOut = $('#check_out_date').val();
            
            if (!checkIn || !checkOut) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาเลือกวันที่',
                    text: 'กรุณาเลือกวันที่เช็คอินและเช็คเอาต์ก่อนเลือกห้องพัก'
                });
                return;
            }
            
            if (new Date(checkIn) >= new Date(checkOut)) {
                Swal.fire({
                    icon: 'error',
                    title: 'วันที่ไม่ถูกต้อง',
                    text: 'วันที่เช็คเอาต์ต้องมากกว่าวันที่เช็คอิน'
                });
                return;
            }
            
            $('#room_check_in').val(checkIn);
            $('#room_check_out').val(checkOut);
            $('#roomSelectionModal').modal('show');
        }

        // Add selected rooms to booking form
        function addSelectedRooms() {
            const selectedRooms = [];
            const selectedRoomIds = [];
            
            $('.room-checkbox:checked:not(:disabled)').each(function() {
                const roomId = $(this).val();
                const roomNumber = $(this).data('room-number');
                const buildingName = $(this).data('building-name');
                const roomName = $(this).data('room-name');
                const price = $(this).data('price');
                
                selectedRooms.push({
                    roomId: roomId,
                    roomNumber: roomNumber,
                    buildingName: buildingName,
                    roomName: roomName,
                    price: price
                });
                
                selectedRoomIds.push(roomId);
            });
            
            if (selectedRooms.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ยังไม่ได้เลือกห้อง',
                    text: 'กรุณาเลือกห้องพักอย่างน้อย 1 ห้อง'
                });
                return;
            }
            
            // Update selected rooms display
            let html = '<div class="selected-rooms-list">';
            selectedRooms.forEach(room => {
                html += `
                    <div class="room-selection-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${room.roomNumber}</strong> - ${room.buildingName}
                                ${room.roomName ? `<br><small class="text-muted">${room.roomName}</small>` : ''}
                                <br><small>ราคาต่อคืน: ฿${parseFloat(room.price).toLocaleString('th-TH', {minimumFractionDigits: 2})}</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRoom(${room.roomId})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            $('#selectedRoomsContainer').html(html);
            $('#selectedRoomIds').val(selectedRoomIds.join(','));
            
            $('#roomSelectionModal').modal('hide');
            Swal.fire({
                icon: 'success',
                title: 'เลือกห้องพักสำเร็จ',
                text: `เลือกห้องพักแล้ว ${selectedRooms.length} ห้อง`,
                timer: 1500,
                showConfirmButton: false
            });
        }

        // Remove room from selection
        function removeRoom(roomId) {
            let selectedRoomIds = $('#selectedRoomIds').val().split(',').filter(id => id && id != roomId);
            $('#selectedRoomIds').val(selectedRoomIds.join(','));
            
            // Remove from display
            $(`#selectedRoomsContainer .room-selection-item`).each(function() {
                const roomNumber = $(this).find('strong').text();
                if (roomNumber.includes(roomId.toString())) {
                    $(this).remove();
                }
            });
            
            // If no rooms left, show placeholder
            if (selectedRoomIds.length === 0) {
                $('#selectedRoomsContainer').html('<p class="text-muted">ยังไม่ได้เลือกห้องพัก</p>');
            }
        }

        // Submit booking form
        function submitBookingForm(formElement, actionName) {
            const formData = new FormData(formElement[0]);
            
            // Validate selected rooms
            const selectedRoomIds = $('#selectedRoomIds').val();
            if (!selectedRoomIds) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาเลือกห้องพัก',
                    text: 'กรุณาเลือกห้องพักอย่างน้อย 1 ห้อง'
                });
                return;
            }
            
            $.ajax({
                url: 'bookings.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            text: response.message || `${actionName}สำเร็จ`,
                            confirmButtonText: 'ตกลง'
                        }).then(() => {
                            formElement.closest('.modal').modal('hide');
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ผิดพลาด',
                            text: response.message || `ไม่สามารถ${actionName}ได้`,
                            confirmButtonText: 'ตกลง'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: 'เกิดข้อผิดพลาดในการส่งข้อมูล: ' + error,
                        confirmButtonText: 'ตกลง'
                    });
                }
            });
        }

        // View booking details
        function viewBooking(bookingId) {
            const bookings = <?php echo json_encode($bookings); ?>;
            const booking = bookings.find(b => b.id == bookingId);
            
            if (booking) {
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h5>${booking.booking_code}</h5>
                            <p><strong>ผู้พัก:</strong> ${booking.guest_name}</p>
                            <p><strong>เบอร์โทร:</strong> ${booking.guest_phone}</p>
                            ${booking.guest_email ? `<p><strong>อีเมล:</strong> ${booking.guest_email}</p>` : ''}
                            ${booking.guest_department ? `<p><strong>สังกัด/หน่วยงาน:</strong> ${booking.guest_department}</p>` : ''}
                        </div>
                        <div class="col-md-6">
                            <p><strong>วันที่เช็คอิน:</strong> ${new Date(booking.check_in_date).toLocaleDateString('th-TH')}</p>
                            <p><strong>วันที่เช็คเอาต์:</strong> ${new Date(booking.check_out_date).toLocaleDateString('th-TH')}</p>
                            <p><strong>จำนวนผู้พัก:</strong> ${booking.number_of_guests || 1} คน</p>
                            <p><strong>วัตถุประสงค์:</strong> ${booking.purpose || '-'}</p>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p><strong>ประเภทการจอง:</strong> ${getBookingTypeThai(booking.booking_type)}</p>
                            <p><strong>สถานะ:</strong> <span class="badge bg-${getStatusBadge(booking.status)}">${getStatusThai(booking.status)}</span></p>
                            ${booking.approved_by ? `<p><strong>ผู้อนุมัติ:</strong> ${booking.approver_name || '-'}</p>` : ''}
                        </div>
                        <div class="col-md-6">
                            <p><strong>สร้างโดย:</strong> ${booking.creator_name}</p>
                            <p><strong>วันที่สร้าง:</strong> ${new Date(booking.created_at).toLocaleDateString('th-TH')} ${new Date(booking.created_at).toLocaleTimeString('th-TH')}</p>
                        </div>
                    </div>
                `;
                
                if (booking.special_request) {
                    html += `
                        <div class="mt-3">
                            <strong>คำขอพิเศษ:</strong>
                            <p>${booking.special_request}</p>
                        </div>
                    `;
                }
                
                $('#viewBookingContent').html(html);
                $('#viewBookingModal').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'ไม่พบข้อมูล',
                    text: 'ไม่พบข้อมูลการจองนี้'
                });
            }
        }

        // ในส่วนของ JavaScript ของ bookings.php
// เพิ่มฟังก์ชันเหล่านี้:

// Edit booking function
function editBooking(bookingId) {
    $.ajax({
        url: 'edit_booking.php',
        type: 'GET',
        data: { 
            id: bookingId, 
            action: 'get_form'
        },
        success: function(response) {
            $('#editBookingContent').html(response);
            $('#editBookingModal').modal('show');
        },
        error: function(xhr, status, error) {
            try {
                const response = JSON.parse(xhr.responseText);
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: response.message || 'ไม่สามารถโหลดฟอร์มแก้ไขได้'
                });
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถโหลดฟอร์มแก้ไขได้'
                });
            }
        }
    });
}

// Show edit room selection
function showEditRoomSelection(bookingId) {
    $.ajax({
        url: 'edit_booking.php',
        type: 'GET',
        data: { 
            id: bookingId, 
            action: 'get_rooms'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showRoomSelectionModalForEdit(response.bookedRooms, response.availableRooms);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: response.message || 'ไม่สามารถโหลดข้อมูลห้องพักได้'
                });
            }
        }
    });
}

// Show room selection modal for edit
function showRoomSelectionModalForEdit(bookedRooms, availableRooms) {
    // Group available rooms by building
    const roomsByBuilding = {};
    availableRooms.forEach(room => {
        const buildingId = room.building_id;
        if (!roomsByBuilding[buildingId]) {
            roomsByBuilding[buildingId] = {
                building_name: room.building_name,
                building_code: room.building_code,
                rooms: []
            };
        }
        roomsByBuilding[buildingId].rooms.push(room);
    });
    
    // Build HTML
    let html = '<div class="row" id="editAvailableRoomsContainer">';
    
    for (const buildingId in roomsByBuilding) {
        const building = roomsByBuilding[buildingId];
        html += `
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">${building.building_name} (${building.building_code})</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
        `;
        
        building.rooms.forEach(room => {
            const statusClass = getRoomStatusClass(room.status);
            const statusText = getRoomStatusText(room.status);
            const isBooked = bookedRooms.some(r => r.room_id == room.id);
            
            html += `
                <div class="col-6 mb-2">
                    <div class="form-check">
                        <input class="form-check-input edit-room-checkbox" type="checkbox" 
                               id="edit_room_${room.id}" 
                               value="${room.id}"
                               data-room-number="${room.room_number}"
                               data-building-name="${room.building_name}"
                               data-room-name="${room.room_name}"
                               data-price="${room.price_per_night}"
                               data-status="${room.status}"
                               ${isBooked ? 'checked' : ''}
                               ${room.status !== 'available' && room.status !== 'cleaning' ? 'disabled' : ''}>
                        <label class="form-check-label" for="edit_room_${room.id}">
                            ${room.room_number}
                            ${room.room_name ? `<br><small class="text-muted">${room.room_name}</small>` : ''}
                            <span class="room-badge ${statusClass} ms-1">${statusText}</span>
                        </label>
                    </div>
                </div>
            `;
        });
        
        html += `
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    html += '</div>';
    
    Swal.fire({
        title: 'จัดการห้องพัก',
        html: html,
        width: '800px',
        showCancelButton: true,
        confirmButtonText: 'บันทึก',
        cancelButtonText: 'ยกเลิก',
        preConfirm: () => {
            const selectedRooms = [];
            const selectedRoomIds = [];
            
            $('.edit-room-checkbox:checked:not(:disabled)').each(function() {
                const roomId = $(this).val();
                const roomNumber = $(this).data('room-number');
                const buildingName = $(this).data('building-name');
                const roomName = $(this).data('room-name');
                const price = $(this).data('price');
                
                selectedRooms.push({
                    roomId: roomId,
                    roomNumber: roomNumber,
                    buildingName: buildingName,
                    roomName: roomName,
                    price: price
                });
                
                selectedRoomIds.push(roomId);
            });
            
            if (selectedRooms.length === 0) {
                Swal.showValidationMessage('กรุณาเลือกห้องพักอย่างน้อย 1 ห้อง');
                return false;
            }
            
            return { selectedRooms, selectedRoomIds };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { selectedRooms, selectedRoomIds } = result.value;
            
            // Update display
            updateEditSelectedRoomsDisplay(selectedRooms);
            
            // Send to server
            $.ajax({
                url: 'process_booking.php',
                type: 'POST',
                data: {
                    action: 'update_booking_rooms',
                    booking_id: bookingId,
                    room_ids: selectedRoomIds.join(',')
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ผิดพลาด',
                            text: response.message
                        });
                    }
                }
            });
        }
    });
}

// Update selected rooms display for edit
function updateEditSelectedRoomsDisplay(selectedRooms) {
    let html = '<div class="selected-rooms-list">';
    
    selectedRooms.forEach(room => {
        html += `
            <div class="room-selection-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${room.roomNumber}</strong> - ${room.buildingName}
                        ${room.roomName ? `<br><small class="text-muted">${room.roomName}</small>` : ''}
                        <br><small>ราคาต่อคืน: ฿${parseFloat(room.price).toLocaleString('th-TH', {minimumFractionDigits: 2})}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" 
                            onclick="removeBookedRoom(${room.roomId})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    
    $('#edit_selectedRoomsContainer').html(html);
    $('#edit_selectedRoomIds').val(selectedRooms.map(r => r.roomId).join(','));
}

// Remove booked room
function removeBookedRoom(bookingId, roomId) {
    // First get current selected rooms
    let selectedRoomIds = $('#edit_selectedRoomIds').val().split(',').filter(id => id && id != roomId);
    
    if (selectedRoomIds.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'ไม่สามารถลบได้',
            text: 'ต้องมีห้องพักอย่างน้อย 1 ห้อง'
        });
        return;
    }
    
    Swal.fire({
        title: 'ยืนยันการลบห้องพัก',
        text: 'คุณต้องการลบห้องพักนี้ใช่หรือไม่?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            // Send update to server
            $.ajax({
                url: 'process_booking.php',
                type: 'POST',
                data: {
                    action: 'update_booking_rooms',
                    booking_id: bookingId,
                    room_ids: selectedRoomIds.join(',')
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Update display
                        const remainingRooms = $('#edit_selectedRoomsContainer .room-selection-item').filter(function() {
                            return !$(this).find('strong').text().includes(roomId.toString());
                        });
                        
                        if (remainingRooms.length > 0) {
                            let newHtml = '<div class="selected-rooms-list">';
                            remainingRooms.each(function() {
                                newHtml += $(this).prop('outerHTML');
                            });
                            newHtml += '</div>';
                            $('#edit_selectedRoomsContainer').html(newHtml);
                        } else {
                            $('#edit_selectedRoomsContainer').html('<p class="text-muted">ยังไม่มีห้องพักที่จอง</p>');
                        }
                        
                        $('#edit_selectedRoomIds').val(selectedRoomIds.join(','));
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'ลบสำเร็จ',
                            text: 'ลบห้องพักเรียบร้อยแล้ว',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ผิดพลาด',
                            text: response.message
                        });
                    }
                }
            });
        }
    });
}

// Handle edit booking form submission
$(document).on('submit', '#editBookingForm', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    $.ajax({
        url: 'process_booking.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: response.message,
                    confirmButtonText: 'ตกลง'
                }).then(() => {
                    $('#editBookingModal').modal('hide');
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'ผิดพลาด',
                    text: response.message,
                    confirmButtonText: 'ตกลง'
                });
            }
        },
        error: function(xhr, status, error) {
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: 'เกิดข้อผิดพลาดในการส่งข้อมูล: ' + error,
                confirmButtonText: 'ตกลง'
            });
        }
    });
});

// Room status helper functions
function getRoomStatusClass(status) {
    switch (status) {
        case 'available': return 'available';
        case 'occupied': return 'occupied';
        case 'cleaning': return 'cleaning';
        case 'maintenance': return 'maintenance';
        default: return '';
    }
}

function getRoomStatusText(status) {
    const statusText = {
        'available': 'ว่าง',
        'occupied': 'จองแล้ว',
        'cleaning': 'ทำความสะอาด',
        'maintenance': 'ซ่อมบำรุง'
    };
    return statusText[status] || status;
}

// Additional functions for booking management
function approveBooking(bookingId, bookingCode) {
    Swal.fire({
        title: 'อนุมัติการจอง',
        html: `คุณต้องการอนุมัติการจอง <strong>${bookingCode}</strong> ใช่หรือไม่?`,
        input: 'textarea',
        inputLabel: 'หมายเหตุ (ถ้ามี)',
        inputPlaceholder: 'ระบุหมายเหตุสำหรับการอนุมัติ...',
        showCancelButton: true,
        confirmButtonText: 'อนุมัติ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'process_booking.php',
                type: 'POST',
                data: {
                    action: 'approve_booking',
                    id: bookingId,
                    notes: result.value || ''
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            text: response.message
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ผิดพลาด',
                            text: response.message
                        });
                    }
                }
            });
        }
    });
}

function checkInBooking(bookingId, bookingCode) {
    Swal.fire({
        title: 'เช็คอิน',
        html: `คุณต้องการเช็คอินการจอง <strong>${bookingCode}</strong> ใช่หรือไม่?`,
        input: 'textarea',
        inputLabel: 'หมายเหตุ (ถ้ามี)',
        inputPlaceholder: 'ระบุหมายเหตุสำหรับการเช็คอิน...',
        showCancelButton: true,
        confirmButtonText: 'เช็คอิน',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'process_booking.php',
                type: 'POST',
                data: {
                    action: 'check_in_booking',
                    id: bookingId,
                    notes: result.value || ''
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            text: response.message
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ผิดพลาด',
                            text: response.message
                        });
                    }
                }
            });
        }
    });
}

function checkOutBooking(bookingId, bookingCode) {
    Swal.fire({
        title: 'เช็คเอาต์',
        html: `คุณต้องการเช็คเอาต์การจอง <strong>${bookingCode}</strong> ใช่หรือไม่?`,
        input: 'textarea',
        inputLabel: 'หมายเหตุ (ถ้ามี)',
        inputPlaceholder: 'ระบุหมายเหตุสำหรับการเช็คเอาต์...',
        showCancelButton: true,
        confirmButtonText: 'เช็คเอาต์',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'process_booking.php',
                type: 'POST',
                data: {
                    action: 'check_out_booking',
                    id: bookingId,
                    notes: result.value || ''
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            text: response.message
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ผิดพลาด',
                            text: response.message
                        });
                    }
                }
            });
        }
    });
}

// Auto-fill check-out date for edit form
$(document).on('change', '#edit_check_in_date', function() {
    const checkInDate = new Date($(this).val());
    if (checkInDate) {
        const checkOutDate = new Date($('#edit_check_out_date').val());
        if (!checkOutDate || checkOutDate <= checkInDate) {
            checkInDate.setDate(checkInDate.getDate() + 1);
            const nextDay = checkInDate.toISOString().split('T')[0];
            $('#edit_check_out_date').val(nextDay);
        }
    }
});

        // Cancel booking
        function cancelBooking(bookingId, bookingCode) {
            Swal.fire({
                title: 'ยืนยันการยกเลิก',
                html: `คุณต้องการยกเลิกการจอง <strong>${bookingCode}</strong> ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ยกเลิกการจอง',
                cancelButtonText: 'ไม่ยืนยัน'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'bookings.php',
                        type: 'POST',
                        data: {
                            action: 'cancel_booking',
                            id: bookingId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ',
                                    text: response.message || 'ยกเลิกการจองสำเร็จ'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ผิดพลาด',
                                    text: response.message || 'ไม่สามารถยกเลิกการจองได้'
                                });
                            }
                        }
                    });
                }
            });
        }

        // Delete booking
        function deleteBooking(bookingId, bookingCode) {
            Swal.fire({
                title: 'ยืนยันการลบ',
                html: `คุณต้องการลบการจอง <strong>${bookingCode}</strong> ใช่หรือไม่?<br><small class="text-danger">การลบนี้ไม่สามารถย้อนกลับได้</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ลบการจอง',
                cancelButtonText: 'ไม่ยืนยัน'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'bookings.php',
                        type: 'POST',
                        data: {
                            action: 'delete_booking',
                            id: bookingId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ',
                                    text: response.message || 'ลบการจองสำเร็จ'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ผิดพลาด',
                                    text: response.message || 'ไม่สามารถลบการจองได้'
                                });
                            }
                        }
                    });
                }
            });
        }

        // Helper functions
        function getStatusBadge(status) {
            const badges = {
                'pending': 'warning',
                'approved': 'success',
                'rejected': 'danger',
                'checked_in': 'info',
                'checked_out': 'secondary',
                'cancelled': 'dark'
            };
            return badges[status] || 'secondary';
        }

        function getStatusThai(status) {
            const statuses = {
                'pending': 'รออนุมัติ',
                'approved': 'อนุมัติแล้ว',
                'rejected': 'ไม่อนุมัติ',
                'checked_in': 'เช็คอินแล้ว',
                'checked_out': 'เช็คเอาต์แล้ว',
                'cancelled': 'ยกเลิก'
            };
            return statuses[status] || status;
        }

        function getBookingTypeThai(type) {
            const types = {
                'official': 'ราชการ',
                'personal': 'ส่วนตัว',
                'training': 'ฝึกอบรม',
                'other': 'อื่นๆ'
            };
            return types[type] || type;
        }

        // Auto-fill check-out date (1 day after check-in)
        $('#check_in_date').on('change', function() {
            const checkInDate = new Date($(this).val());
            if (checkInDate && !$('#check_out_date').val()) {
                checkInDate.setDate(checkInDate.getDate() + 1);
                const nextDay = checkInDate.toISOString().split('T')[0];
                $('#check_out_date').val(nextDay);
            }
        });
    </script>
</body>
</html>