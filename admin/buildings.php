<?php
/**
 * Buildings Management Page
 * หน้าจัดการอาคารและบ้านพัก
 */

session_start();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header('Location: ../login.php?error=กรุณาล็อกอินเพื่อเข้าถึงระบบ');
    exit();
}

$pageTitle = "จัดการอาคารและบ้านพัก";
$userRole = $_SESSION['user_role'];

// เชื่อมต่อฐานข้อมูล
require_once '../config/database.php';
$db = getDatabaseConnection();

// ตรวจสอบการดำเนินการ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = [];
    
    try {
        switch ($action) {
            case 'add_building':
                // ตรวจสอบข้อมูลที่จำเป็น
                $required = ['building_code', 'building_name', 'building_type', 'price_per_night'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("กรุณากรอกข้อมูลให้ครบ: $field");
                    }
                }

                // ตรวจสอบรหัสอาคารซ้ำ
                $stmt = $db->prepare("SELECT id FROM buildings WHERE building_code = ?");
                $stmt->execute([$_POST['building_code']]);
                if ($stmt->fetch()) {
                    throw new Exception("รหัสอาคารนี้มีอยู่แล้วในระบบ");
                }

                // เพิ่มอาคาร
                $stmt = $db->prepare("INSERT INTO buildings (
                    building_code, building_name, building_type, location,
                    price_per_night, max_occupancy, description, amenities, status,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

                $success = $stmt->execute([
                    $_POST['building_code'],
                    $_POST['building_name'],
                    $_POST['building_type'],
                    $_POST['location'] ?? '',
                    $_POST['price_per_night'],
                    $_POST['max_occupancy'] ?? 2,
                    $_POST['description'] ?? '',
                    $_POST['amenities'] ?? '',
                    $_POST['status'] ?? 'active'
                ]);

                if ($success) {
                    $response = ['success' => true, 'message' => 'เพิ่มอาคารสำเร็จ'];
                } else {
                    throw new Exception("ไม่สามารถเพิ่มอาคารได้");
                }
                break;

            case 'update_building':
                // ตรวจสอบข้อมูลที่จำเป็น
                $required = ['building_id', 'building_code', 'building_name', 'building_type', 'price_per_night'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("กรุณากรอกข้อมูลให้ครบ: $field");
                    }
                }

                // ตรวจสอบรหัสอาคารซ้ำ (ยกเว้นตัวมันเอง)
                $stmt = $db->prepare("SELECT id FROM buildings WHERE building_code = ? AND id != ?");
                $stmt->execute([$_POST['building_code'], $_POST['building_id']]);
                if ($stmt->fetch()) {
                    throw new Exception("รหัสอาคารนี้มีอยู่แล้วในระบบ");
                }

                // อัปเดตอาคาร
                $stmt = $db->prepare("UPDATE buildings SET
                    building_code = ?,
                    building_name = ?,
                    building_type = ?,
                    location = ?,
                    price_per_night = ?,
                    max_occupancy = ?,
                    description = ?,
                    amenities = ?,
                    status = ?,
                    updated_at = NOW()
                    WHERE id = ?");

                $success = $stmt->execute([
                    $_POST['building_code'],
                    $_POST['building_name'],
                    $_POST['building_type'],
                    $_POST['location'] ?? '',
                    $_POST['price_per_night'],
                    $_POST['max_occupancy'] ?? 2,
                    $_POST['description'] ?? '',
                    $_POST['amenities'] ?? '',
                    $_POST['status'] ?? 'active',
                    $_POST['building_id']
                ]);

                if ($success) {
                    $response = ['success' => true, 'message' => 'อัปเดตอาคารสำเร็จ'];
                } else {
                    throw new Exception("ไม่สามารถอัปเดตอาคารได้");
                }
                break;

            case 'add_room':
                // ตรวจสอบข้อมูลที่จำเป็น
                $required = ['building_id', 'room_number'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("กรุณากรอกข้อมูลให้ครบ: $field");
                    }
                }

                // ตรวจสอบหมายเลขห้องซ้ำในอาคารเดียวกัน
                $stmt = $db->prepare("SELECT id FROM rooms WHERE building_id = ? AND room_number = ?");
                $stmt->execute([$_POST['building_id'], $_POST['room_number']]);
                if ($stmt->fetch()) {
                    throw new Exception("หมายเลขห้องนี้มีอยู่แล้วในอาคารนี้");
                }

                // เพิ่มห้องพัก
                $stmt = $db->prepare("INSERT INTO rooms (
                    building_id, room_number, room_name, room_type,
                    floor, max_capacity, price_per_night, amenities, status,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

                $success = $stmt->execute([
                    $_POST['building_id'],
                    $_POST['room_number'],
                    $_POST['room_name'] ?? '',
                    $_POST['room_type'] ?? 'standard',
                    $_POST['floor'] ?? 1,
                    $_POST['max_capacity'] ?? 2,
                    $_POST['price_per_night'] ?? 0,
                    $_POST['amenities'] ?? '',
                    $_POST['status'] ?? 'available'
                ]);

                if ($success) {
                    $response = ['success' => true, 'message' => 'เพิ่มห้องพักสำเร็จ'];
                } else {
                    throw new Exception("ไม่สามารถเพิ่มห้องพักได้");
                }
                break;

            case 'update_room':
                // ตรวจสอบข้อมูลที่จำเป็น
                $required = ['room_id', 'building_id', 'room_number'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("กรุณากรอกข้อมูลให้ครบ: $field");
                    }
                }

                // ตรวจสอบหมายเลขห้องซ้ำในอาคารเดียวกัน (ยกเว้นตัวมันเอง)
                $stmt = $db->prepare("SELECT id FROM rooms WHERE building_id = ? AND room_number = ? AND id != ?");
                $stmt->execute([$_POST['building_id'], $_POST['room_number'], $_POST['room_id']]);
                if ($stmt->fetch()) {
                    throw new Exception("หมายเลขห้องนี้มีอยู่แล้วในอาคารนี้");
                }

                // อัปเดตห้องพัก
                $stmt = $db->prepare("UPDATE rooms SET
                    building_id = ?,
                    room_number = ?,
                    room_name = ?,
                    room_type = ?,
                    floor = ?,
                    max_capacity = ?,
                    price_per_night = ?,
                    amenities = ?,
                    status = ?,
                    updated_at = NOW()
                    WHERE id = ?");

                $success = $stmt->execute([
                    $_POST['building_id'],
                    $_POST['room_number'],
                    $_POST['room_name'] ?? '',
                    $_POST['room_type'] ?? 'standard',
                    $_POST['floor'] ?? 1,
                    $_POST['max_capacity'] ?? 2,
                    $_POST['price_per_night'] ?? 0,
                    $_POST['amenities'] ?? '',
                    $_POST['status'] ?? 'available',
                    $_POST['room_id']
                ]);

                if ($success) {
                    $response = ['success' => true, 'message' => 'อัปเดตห้องพักสำเร็จ'];
                } else {
                    throw new Exception("ไม่สามารถอัปเดตห้องพักได้");
                }
                break;

            case 'delete_building':
                if ($userRole !== 'admin') {
                    throw new Exception("เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถลบอาคารได้");
                }

                if (empty($_POST['id'])) {
                    throw new Exception("ไม่ระบุ ID อาคาร");
                }

                // ลบห้องพักทั้งหมดในอาคารก่อน
                $stmt = $db->prepare("DELETE FROM rooms WHERE building_id = ?");
                $stmt->execute([$_POST['id']]);

                // ลบอาคาร
                $stmt = $db->prepare("DELETE FROM buildings WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);

                if ($success) {
                    $response = ['success' => true, 'message' => 'ลบอาคารสำเร็จ'];
                } else {
                    throw new Exception("ไม่สามารถลบอาคารได้");
                }
                break;

            case 'delete_room':
                if ($userRole !== 'admin') {
                    throw new Exception("เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถลบห้องพักได้");
                }

                if (empty($_POST['id'])) {
                    throw new Exception("ไม่ระบุ ID ห้องพัก");
                }

                $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
                $success = $stmt->execute([$_POST['id']]);

                if ($success) {
                    $response = ['success' => true, 'message' => 'ลบห้องพักสำเร็จ'];
                } else {
                    throw new Exception("ไม่สามารถลบห้องพักได้");
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

// ดึงข้อมูลอาคาร
$stmt = $db->query("SELECT * FROM buildings ORDER BY building_type, building_name");
$buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลห้องพัก
$roomsStmt = $db->query("SELECT r.*, b.building_name, b.building_code 
                         FROM rooms r 
                         JOIN buildings b ON r.building_id = b.id 
                         ORDER BY b.building_name, r.room_number");
$rooms = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);

// นับจำนวนห้องตามอาคาร
$roomCounts = [];
foreach ($rooms as $room) {
    if (!isset($roomCounts[$room['building_id']])) {
        $roomCounts[$room['building_id']] = [
            'total' => 0,
            'available' => 0,
            'occupied' => 0,
            'cleaning' => 0,
            'maintenance' => 0
        ];
    }
    $roomCounts[$room['building_id']]['total']++;
    $roomCounts[$room['building_id']][$room['status'] ?? 'available']++;
}

// ฟังก์ชันแปลงประเภทอาคาร
function getBuildingTypeThai($type) {
    $types = [
        'guest_house' => 'บ้านพักรับรอง',
        'accommodation' => 'อาคารที่พัก'
    ];
    return $types[$type] ?? $type;
}

// ฟังก์ชันแปลงสถานะอาคาร
function getBuildingStatusThai($status) {
    $statuses = [
        'active' => 'เปิดใช้งาน',
        'maintenance' => 'ปิดซ่อมบำรุง',
        'inactive' => 'ปิดใช้งาน'
    ];
    return $statuses[$status] ?? $status;
}

// ฟังก์ชันแปลงสถานะห้อง
function getRoomStatusThai($status) {
    $statuses = [
        'available' => 'ว่าง',
        'occupied' => 'จองแล้ว',
        'cleaning' => 'ทำความสะอาด',
        'maintenance' => 'ซ่อมบำรุง'
    ];
    return $statuses[$status] ?? $status;
}

// ฟังก์ชันสำหรับ badge สถานะห้อง
function getRoomStatusBadge($status) {
    $badges = [
        'available' => 'success',
        'occupied' => 'danger',
        'cleaning' => 'warning',
        'maintenance' => 'secondary'
    ];
    return $badges[$status] ?? 'secondary';
}

// ฟังก์ชันสำหรับ badge สถานะอาคาร
function getBuildingStatusBadge($status) {
    $badges = [
        'active' => 'success',
        'maintenance' => 'warning',
        'inactive' => 'secondary'
    ];
    return $badges[$status] ?? 'secondary';
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
        .building-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        .building-card:hover {
            transform: translateY(-5px);
        }
        .room-status {
            display: inline-block;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .status-available {
            background-color: #28a745;
        }
        .status-occupied {
            background-color: #dc3545;
        }
        .status-cleaning {
            background-color: #ffc107;
        }
        .status-maintenance {
            background-color: #6c757d;
        }
        .floor-plan {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .room-box {
            border: 2px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .room-box:hover {
            transform: scale(1.05);
        }
        .room-box.available {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
        }
        .room-box.occupied {
            border-color: #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
        }
        .room-box.cleaning {
            border-color: #ffc107;
            background-color: rgba(255, 193, 7, 0.1);
        }
        .room-box.maintenance {
            border-color: #6c757d;
            background-color: rgba(108, 117, 125, 0.1);
        }
        .tab-content {
            background: white;
            border-radius: 0 0 10px 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: #1e3c72;
            border-bottom: 3px solid #1e3c72;
            background: transparent;
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
                    <a href="bookings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-check me-2"></i>จัดการการจอง
                    </a>
                    <a href="buildings.php" class="list-group-item list-group-item-action active">
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
                    <h2><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($pageTitle); ?></h2>
                    <div>
                        <button class="btn btn-primary me-2" onclick="showAddBuildingModal()">
                            <i class="fas fa-plus me-2"></i>เพิ่มอาคาร
                        </button>
                        <button class="btn btn-success" onclick="showAddRoomModal()">
                            <i class="fas fa-bed me-2"></i>เพิ่มห้องพัก
                        </button>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Building Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card building-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">อาคารทั้งหมด</h6>
                                        <h4 class="mb-0"><?php echo count($buildings); ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-building fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card building-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">ห้องพักทั้งหมด</h6>
                                        <h4 class="mb-0"><?php echo count($rooms); ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-bed fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card building-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">ห้องว่าง</h6>
                                        <h4 class="mb-0"><?php echo count(array_filter($rooms, fn($r) => ($r['status'] ?? 'available') === 'available')); ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-door-open fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card building-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="text-muted mb-1">ห้องถูกจอง</h6>
                                        <h4 class="mb-0"><?php echo count(array_filter($rooms, fn($r) => ($r['status'] ?? 'available') === 'occupied')); ?></h4>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-door-closed fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs" id="buildingTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="buildings-tab" data-bs-toggle="tab" data-bs-target="#buildings" type="button" role="tab">
                            <i class="fas fa-building me-2"></i>รายการอาคาร
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rooms-tab" data-bs-toggle="tab" data-bs-target="#rooms" type="button" role="tab">
                            <i class="fas fa-bed me-2"></i>รายการห้องพัก
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="floorplan-tab" data-bs-toggle="tab" data-bs-target="#floorplan" type="button" role="tab">
                            <i class="fas fa-map me-2"></i>แผนผังสถานะ
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="buildingTabContent">
                    <!-- Buildings Tab -->
                    <div class="tab-pane fade show active" id="buildings" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover" id="buildingsTable">
                                <thead>
                                    <tr>
                                        <th>รหัสอาคาร</th>
                                        <th>ชื่ออาคาร</th>
                                        <th>ประเภท</th>
                                        <th>จำนวนห้อง</th>
                                        <th>ราคาต่อคืน</th>
                                        <th>สถานะ</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($buildings as $building): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($building['building_code']); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($building['building_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($building['location'] ?? '-'); ?></small>
                                        </td>
                                        <td><?php echo getBuildingTypeThai($building['building_type']); ?></td>
                                        <td>
                                            <?php 
                                            $count = $roomCounts[$building['id']] ?? ['total' => 0];
                                            echo $count['total'] . ' ห้อง';
                                            ?>
                                            <br>
                                            <small>
                                                <span class="text-success"><?php echo $count['available'] ?? 0; ?> ว่าง</span> | 
                                                <span class="text-danger"><?php echo $count['occupied'] ?? 0; ?> จอง</span>
                                            </small>
                                        </td>
                                        <td>
                                            ฿<?php echo number_format($building['price_per_night'] ?? 0, 2); ?><br>
                                            <small class="text-muted">ต่อห้องต่อคืน</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getBuildingStatusBadge($building['status'] ?? 'active'); ?>">
                                                <?php echo getBuildingStatusThai($building['status'] ?? 'active'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewBuilding(<?php echo $building['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="editBuilding(<?php echo $building['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($userRole === 'admin'): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteBuilding(<?php echo $building['id']; ?>, '<?php echo htmlspecialchars(addslashes($building['building_name'])); ?>')">
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

                    <!-- Rooms Tab -->
                    <div class="tab-pane fade" id="rooms" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover" id="roomsTable">
                                <thead>
                                    <tr>
                                        <th>หมายเลขห้อง</th>
                                        <th>ชื่อห้อง</th>
                                        <th>อาคาร</th>
                                        <th>ประเภท</th>
                                        <th>ความจุ</th>
                                        <th>ราคา/คืน</th>
                                        <th>สถานะ</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rooms as $room): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($room['room_name'] ?: '-'); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($room['building_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($room['building_code']); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $types = [
                                                'standard' => 'มาตรฐาน',
                                                'vip' => 'วีไอพี',
                                                'family' => 'ครอบครัว',
                                                'suite' => 'สวีท'
                                            ];
                                            echo $types[$room['room_type']] ?? $room['room_type'];
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($room['max_capacity'] ?? 2); ?> คน</td>
                                        <td>฿<?php echo number_format($room['price_per_night'] ?? 0, 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getRoomStatusBadge($room['status'] ?? 'available'); ?>">
                                                <?php echo getRoomStatusThai($room['status'] ?? 'available'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewRoom(<?php echo $room['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="editRoom(<?php echo $room['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($userRole === 'admin'): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteRoom(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars(addslashes($room['room_number'] . ' - ' . $room['room_name'])); ?>')">
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

                    <!-- Floor Plan Tab -->
                    <div class="tab-pane fade" id="floorplan" role="tabpanel">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">ตัวกรอง</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="buildingSelect" class="form-label">เลือกอาคาร</label>
                                            <select class="form-select" id="buildingSelect">
                                                <option value="">ทั้งหมด</option>
                                                <?php foreach ($buildings as $building): ?>
                                                <option value="<?php echo $building['id']; ?>">
                                                    <?php echo htmlspecialchars($building['building_name']); ?> (<?php echo htmlspecialchars($building['building_code']); ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="floorSelect" class="form-label">เลือกชั้น</label>
                                            <select class="form-select" id="floorSelect">
                                                <option value="">ทั้งหมด</option>
                                                <?php 
                                                $floors = array_unique(array_column($rooms, 'floor'));
                                                $floors = array_filter($floors);
                                                sort($floors);
                                                foreach ($floors as $floor): 
                                                ?>
                                                <option value="<?php echo $floor; ?>">ชั้น <?php echo $floor; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">สถานะห้อง</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="statusAvailable" checked>
                                                <label class="form-check-label" for="statusAvailable">
                                                    <span class="room-status status-available"></span> ว่าง
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="statusOccupied" checked>
                                                <label class="form-check-label" for="statusOccupied">
                                                    <span class="room-status status-occupied"></span> จองแล้ว
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="statusCleaning" checked>
                                                <label class="form-check-label" for="statusCleaning">
                                                    <span class="room-status status-cleaning"></span> ทำความสะอาด
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="statusMaintenance" checked>
                                                <label class="form-check-label" for="statusMaintenance">
                                                    <span class="room-status status-maintenance"></span> ซ่อมบำรุง
                                                </label>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary w-100" onclick="loadFloorPlan()">ใช้ตัวกรอง</button>
                                    </div>
                                </div>
                                
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">คำอธิบายสี</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="room-status status-available me-2"></div>
                                            <span>ว่าง</span>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="room-status status-occupied me-2"></div>
                                            <span>จองแล้ว</span>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="room-status status-cleaning me-2"></div>
                                            <span>ทำความสะอาด</span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <div class="room-status status-maintenance me-2"></div>
                                            <span>ซ่อมบำรุง</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-9">
                                <div id="floorPlanContainer">
                                    <!-- Floor plans will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Building Modal -->
    <div class="modal fade" id="addBuildingModal" tabindex="-1" aria-labelledby="addBuildingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBuildingModalLabel"><i class="fas fa-plus me-2"></i>เพิ่มอาคารใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addBuildingForm" action="buildings.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_building">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="building_code" class="form-label">รหัสอาคาร *</label>
                                <input type="text" class="form-control" id="building_code" name="building_code" required>
                                <div class="form-text">เช่น GH-001, AC-001</div>
                            </div>
                            <div class="col-md-6">
                                <label for="building_name" class="form-label">ชื่ออาคาร *</label>
                                <input type="text" class="form-control" id="building_name" name="building_name" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="building_type" class="form-label">ประเภทอาคาร *</label>
                                <select class="form-select" id="building_type" name="building_type" required>
                                    <option value="guest_house">บ้านพักรับรอง</option>
                                    <option value="accommodation">อาคารที่พัก</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="location" class="form-label">ที่ตั้ง</label>
                                <input type="text" class="form-control" id="location" name="location">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="price_per_night" class="form-label">ราคาต่อคืน *</label>
                                <input type="number" class="form-control" id="price_per_night" name="price_per_night" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-4">
                                <label for="max_occupancy" class="form-label">ความจุสูงสุดต่อห้อง</label>
                                <input type="number" class="form-control" id="max_occupancy" name="max_occupancy" min="1" value="2">
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label">สถานะ</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active">เปิดใช้งาน</option>
                                    <option value="maintenance">ปิดซ่อมบำรุง</option>
                                    <option value="inactive">ปิดใช้งาน</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">คำอธิบาย</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amenities" class="form-label">สิ่งอำนวยความสะดวก</label>
                            <textarea class="form-control" id="amenities" name="amenities" rows="3"></textarea>
                            <div class="form-text">คั่นด้วยเครื่องหมายจุลภาค (,) เช่น WiFi, TV, ตู้เย็น</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Building Modal -->
    <div class="modal fade" id="editBuildingModal" tabindex="-1" aria-labelledby="editBuildingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBuildingModalLabel"><i class="fas fa-edit me-2"></i>แก้ไขอาคาร</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editBuildingForm" action="buildings.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_building">
                        <input type="hidden" name="building_id" id="edit_building_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_building_code" class="form-label">รหัสอาคาร *</label>
                                <input type="text" class="form-control" id="edit_building_code" name="building_code" required>
                                <div class="form-text">เช่น GH-001, AC-001</div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_building_name" class="form-label">ชื่ออาคาร *</label>
                                <input type="text" class="form-control" id="edit_building_name" name="building_name" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_building_type" class="form-label">ประเภทอาคาร *</label>
                                <select class="form-select" id="edit_building_type" name="building_type" required>
                                    <option value="guest_house">บ้านพักรับรอง</option>
                                    <option value="accommodation">อาคารที่พัก</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_location" class="form-label">ที่ตั้ง</label>
                                <input type="text" class="form-control" id="edit_location" name="location">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="edit_price_per_night" class="form-label">ราคาต่อคืน *</label>
                                <input type="number" class="form-control" id="edit_price_per_night" name="price_per_night" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_max_occupancy" class="form-label">ความจุสูงสุดต่อห้อง</label>
                                <input type="number" class="form-control" id="edit_max_occupancy" name="max_occupancy" min="1" value="2">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_status" class="form-label">สถานะ</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="active">เปิดใช้งาน</option>
                                    <option value="maintenance">ปิดซ่อมบำรุง</option>
                                    <option value="inactive">ปิดใช้งาน</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">คำอธิบาย</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_amenities" class="form-label">สิ่งอำนวยความสะดวก</label>
                            <textarea class="form-control" id="edit_amenities" name="amenities" rows="3"></textarea>
                            <div class="form-text">คั่นด้วยเครื่องหมายจุลภาค (,) เช่น WiFi, TV, ตู้เย็น</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Room Modal -->
    <div class="modal fade" id="addRoomModal" tabindex="-1" aria-labelledby="addRoomModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRoomModalLabel"><i class="fas fa-bed me-2"></i>เพิ่มห้องพักใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addRoomForm" action="buildings.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_room">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="building_id" class="form-label">อาคาร *</label>
                                <select class="form-select" id="building_id" name="building_id" required>
                                    <option value="">เลือกอาคาร</option>
                                    <?php foreach ($buildings as $building): ?>
                                    <option value="<?php echo $building['id']; ?>">
                                        <?php echo htmlspecialchars($building['building_name']); ?> (<?php echo htmlspecialchars($building['building_code']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="room_number" class="form-label">หมายเลขห้อง *</label>
                                <input type="text" class="form-control" id="room_number" name="room_number" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="room_name" class="form-label">ชื่อห้อง</label>
                                <input type="text" class="form-control" id="room_name" name="room_name">
                            </div>
                            <div class="col-md-6">
                                <label for="room_type" class="form-label">ประเภทห้อง</label>
                                <select class="form-select" id="room_type" name="room_type">
                                    <option value="standard">มาตรฐาน</option>
                                    <option value="vip">วีไอพี</option>
                                    <option value="family">ครอบครัว</option>
                                    <option value="suite">สวีท</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="floor" class="form-label">ชั้น</label>
                                <input type="number" class="form-control" id="floor" name="floor" min="1" value="1">
                            </div>
                            <div class="col-md-4">
                                <label for="max_capacity" class="form-label">ความจุสูงสุด</label>
                                <input type="number" class="form-control" id="max_capacity" name="max_capacity" min="1" value="2">
                            </div>
                            <div class="col-md-4">
                                <label for="price_per_night" class="form-label">ราคาต่อคืน</label>
                                <input type="number" class="form-control" id="price_per_night" name="price_per_night" min="0" step="0.01">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amenities" class="form-label">สิ่งอำนวยความสะดวก</label>
                            <textarea class="form-control" id="amenities" name="amenities" rows="2"></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="status" class="form-label">สถานะ</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="available">ว่าง</option>
                                    <option value="cleaning">ทำความสะอาด</option>
                                    <option value="maintenance">ซ่อมบำรุง</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Room Modal -->
    <div class="modal fade" id="editRoomModal" tabindex="-1" aria-labelledby="editRoomModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRoomModalLabel"><i class="fas fa-edit me-2"></i>แก้ไขห้องพัก</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editRoomForm" action="buildings.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_room">
                        <input type="hidden" name="room_id" id="edit_room_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_room_building_id" class="form-label">อาคาร *</label>
                                <select class="form-select" id="edit_room_building_id" name="building_id" required>
                                    <option value="">เลือกอาคาร</option>
                                    <?php foreach ($buildings as $building): ?>
                                    <option value="<?php echo $building['id']; ?>">
                                        <?php echo htmlspecialchars($building['building_name']); ?> (<?php echo htmlspecialchars($building['building_code']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_room_number" class="form-label">หมายเลขห้อง *</label>
                                <input type="text" class="form-control" id="edit_room_number" name="room_number" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_room_name" class="form-label">ชื่อห้อง</label>
                                <input type="text" class="form-control" id="edit_room_name" name="room_name">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_room_type" class="form-label">ประเภทห้อง</label>
                                <select class="form-select" id="edit_room_type" name="room_type">
                                    <option value="standard">มาตรฐาน</option>
                                    <option value="vip">วีไอพี</option>
                                    <option value="family">ครอบครัว</option>
                                    <option value="suite">สวีท</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="edit_room_floor" class="form-label">ชั้น</label>
                                <input type="number" class="form-control" id="edit_room_floor" name="floor" min="1" value="1">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_room_max_capacity" class="form-label">ความจุสูงสุด</label>
                                <input type="number" class="form-control" id="edit_room_max_capacity" name="max_capacity" min="1" value="2">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_room_price_per_night" class="form-label">ราคาต่อคืน</label>
                                <input type="number" class="form-control" id="edit_room_price_per_night" name="price_per_night" min="0" step="0.01">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_room_amenities" class="form-label">สิ่งอำนวยความสะดวก</label>
                            <textarea class="form-control" id="edit_room_amenities" name="amenities" rows="2"></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_room_status" class="form-label">สถานะ</label>
                                <select class="form-select" id="edit_room_status" name="status">
                                    <option value="available">ว่าง</option>
                                    <option value="occupied">จองแล้ว</option>
                                    <option value="cleaning">ทำความสะอาด</option>
                                    <option value="maintenance">ซ่อมบำรุง</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </form>
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
        // Initialize DataTables
        $(document).ready(function() {
            $('#buildingsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json'
                },
                pageLength: 10,
                order: [[1, 'asc']]
            });
            
            $('#roomsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json'
                },
                pageLength: 10,
                order: [[0, 'asc']]
            });
            
            // Load initial floor plan
            loadFloorPlan();
            
            // Handle form submissions with AJAX
            $('#addBuildingForm').on('submit', function(e) {
                e.preventDefault();
                submitForm($(this), 'เพิ่มอาคารสำเร็จ');
            });
            
            $('#editBuildingForm').on('submit', function(e) {
                e.preventDefault();
                submitForm($(this), 'แก้ไขอาคารสำเร็จ');
            });
            
            $('#addRoomForm').on('submit', function(e) {
                e.preventDefault();
                submitForm($(this), 'เพิ่มห้องพักสำเร็จ');
            });
            
            $('#editRoomForm').on('submit', function(e) {
                e.preventDefault();
                submitForm($(this), 'แก้ไขห้องพักสำเร็จ');
            });
        });

        // Show add building modal
        function showAddBuildingModal() {
            $('#addBuildingForm')[0].reset();
            $('#addBuildingModal').modal('show');
        }

        // Show add room modal
        function showAddRoomModal() {
            $('#addRoomForm')[0].reset();
            $('#addRoomModal').modal('show');
        }

        // Edit building
        function editBuilding(id) {
            // Get building data from existing data on page
            const buildings = <?php echo json_encode($buildings); ?>;
            const building = buildings.find(b => b.id == id);
            
            if (building) {
                // Fill form fields
                $('#edit_building_id').val(building.id);
                $('#edit_building_code').val(building.building_code);
                $('#edit_building_name').val(building.building_name);
                $('#edit_building_type').val(building.building_type);
                $('#edit_location').val(building.location || '');
                $('#edit_price_per_night').val(building.price_per_night);
                $('#edit_max_occupancy').val(building.max_occupancy || 2);
                $('#edit_status').val(building.status || 'active');
                $('#edit_description').val(building.description || '');
                $('#edit_amenities').val(building.amenities || '');
                
                // Show modal
                $('#editBuildingModal').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: 'ไม่พบข้อมูลอาคาร'
                });
            }
        }

        // Edit room
        function editRoom(id) {
            // Get room data from existing data on page
            const rooms = <?php echo json_encode($rooms); ?>;
            const room = rooms.find(r => r.id == id);
            
            if (room) {
                // Fill form fields
                $('#edit_room_id').val(room.id);
                $('#edit_room_building_id').val(room.building_id);
                $('#edit_room_number').val(room.room_number);
                $('#edit_room_name').val(room.room_name || '');
                $('#edit_room_type').val(room.room_type || 'standard');
                $('#edit_room_floor').val(room.floor || 1);
                $('#edit_room_max_capacity').val(room.max_capacity || 2);
                $('#edit_room_price_per_night').val(room.price_per_night || 0);
                $('#edit_room_amenities').val(room.amenities || '');
                $('#edit_room_status').val(room.status || 'available');
                
                // Show modal
                $('#editRoomModal').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: 'ไม่พบข้อมูลห้องพัก'
                });
            }
        }

        // Generic form submission function
        function submitForm(formElement, successMessage) {
            const formData = new FormData(formElement[0]);
            
            $.ajax({
                url: 'buildings.php',
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
                            text: response.message || successMessage,
                            confirmButtonText: 'ตกลง'
                        }).then(() => {
                            // Close modal
                            formElement.closest('.modal').modal('hide');
                            // Reload page to show updated data
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'ผิดพลาด',
                            text: response.message || 'เกิดข้อผิดพลาด',
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

        // Load floor plan based on filters
        function loadFloorPlan() {
            const buildingId = $('#buildingSelect').val();
            const floor = $('#floorSelect').val();
            
            // Get checked statuses
            const statuses = [];
            if ($('#statusAvailable').is(':checked')) statuses.push('available');
            if ($('#statusOccupied').is(':checked')) statuses.push('occupied');
            if ($('#statusCleaning').is(':checked')) statuses.push('cleaning');
            if ($('#statusMaintenance').is(':checked')) statuses.push('maintenance');
            
            // Generate floor plan dynamically
            generateFloorPlan(buildingId, floor, statuses);
        }

        // Generate floor plan dynamically
        function generateFloorPlan(buildingId, floor, statuses) {
            const container = $('#floorPlanContainer');
            
            if (buildingId && buildingId !== '') {
                // Filter rooms by building
                const filteredRooms = <?php echo json_encode($rooms); ?>.filter(room => {
                    return room.building_id == buildingId && 
                           (floor === '' || room.floor == floor) &&
                           (statuses.length === 0 || statuses.includes(room.status));
                });
                
                if (filteredRooms.length === 0) {
                    container.html('<div class="alert alert-info">ไม่พบห้องพักที่ตรงกับเงื่อนไข</div>');
                    return;
                }
                
                // Group by floor
                const floors = {};
                filteredRooms.forEach(room => {
                    const floorNum = room.floor || 'ไม่มีชั้น';
                    if (!floors[floorNum]) {
                        floors[floorNum] = [];
                    }
                    floors[floorNum].push(room);
                });
                
                let html = '';
                Object.keys(floors).sort().forEach(floorNum => {
                    html += `<div class="mb-4">
                                <h5>ชั้น ${floorNum}</h5>
                                <div class="floor-plan">`;
                    
                    floors[floorNum].forEach(room => {
                        const statusClass = room.status || 'available';
                        html += `<div class="room-box ${statusClass}" onclick="viewRoom(${room.id})">
                                    <div class="fw-bold">${room.room_number}</div>
                                    <small>${room.room_name || '-'}</small>
                                    <div><span class="badge bg-${getRoomStatusBadge(room.status)}">${getRoomStatusThai(room.status)}</span></div>
                                </div>`;
                    });
                    
                    html += '</div></div>';
                });
                
                container.html(html);
            } else {
                // Show all buildings with their rooms
                const buildings = <?php echo json_encode($buildings); ?>;
                const allRooms = <?php echo json_encode($rooms); ?>;
                
                if (buildings.length === 0) {
                    container.html('<div class="alert alert-info">ยังไม่มีข้อมูลอาคาร</div>');
                    return;
                }
                
                let html = '';
                buildings.forEach(building => {
                    const buildingRooms = allRooms.filter(room => 
                        room.building_id == building.id &&
                        (floor === '' || room.floor == floor) &&
                        (statuses.length === 0 || statuses.includes(room.status))
                    );
                    
                    if (buildingRooms.length > 0) {
                        html += `<div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">${building.building_name} (${building.building_code})</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="floor-plan">`;
                        
                        buildingRooms.forEach(room => {
                            const statusClass = room.status || 'available';
                            html += `<div class="room-box ${statusClass}" onclick="viewRoom(${room.id})">
                                        <div class="fw-bold">${room.room_number}</div>
                                        <small>${room.room_name || '-'}</small>
                                        <div><span class="badge bg-${getRoomStatusBadge(room.status)}">${getRoomStatusThai(room.status)}</span></div>
                                    </div>`;
                        });
                        
                        html += '</div></div></div>';
                    }
                });
                
                container.html(html || '<div class="alert alert-info">ไม่พบห้องพักที่ตรงกับเงื่อนไข</div>');
            }
        }

        // Helper functions
        function getRoomStatusBadge(status) {
            const badges = {
                'available': 'success',
                'occupied': 'danger',
                'cleaning': 'warning',
                'maintenance': 'secondary'
            };
            return badges[status] || 'secondary';
        }

        function getRoomStatusThai(status) {
            const statuses = {
                'available': 'ว่าง',
                'occupied': 'จองแล้ว',
                'cleaning': 'ทำความสะอาด',
                'maintenance': 'ซ่อมบำรุง'
            };
            return statuses[status] || status;
        }

        function getRoomTypeThai(type) {
            const types = {
                'standard': 'มาตรฐาน',
                'vip': 'วีไอพี',
                'family': 'ครอบครัว',
                'suite': 'สวีท'
            };
            return types[type] || type;
        }

        // View building details
        function viewBuilding(id) {
            const buildings = <?php echo json_encode($buildings); ?>;
            const building = buildings.find(b => b.id == id);
            
            if (building) {
                const rooms = <?php echo json_encode($rooms); ?>;
                const buildingRooms = rooms.filter(r => r.building_id == id);
                
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h5>${building.building_name}</h5>
                            <p><strong>รหัสอาคาร:</strong> ${building.building_code}</p>
                            <p><strong>ประเภท:</strong> ${building.building_type === 'guest_house' ? 'บ้านพักรับรอง' : 'อาคารที่พัก'}</p>
                            <p><strong>ที่ตั้ง:</strong> ${building.location || '-'}</p>
                            <p><strong>ราคาต่อคืน:</strong> ฿${parseFloat(building.price_per_night).toLocaleString('th-TH', {minimumFractionDigits: 2})}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>ความจุสูงสุดต่อห้อง:</strong> ${building.max_occupancy || 2} คน</p>
                            <p><strong>สถานะ:</strong> <span class="badge bg-${getBuildingStatusBadge(building.status)}">${getBuildingStatusThai(building.status)}</span></p>
                            <p><strong>จำนวนห้องพัก:</strong> ${buildingRooms.length} ห้อง</p>
                        </div>
                    </div>
                `;
                
                if (building.description) {
                    html += `<div class="mt-3"><strong>คำอธิบาย:</strong><p>${building.description}</p></div>`;
                }
                
                if (building.amenities) {
                    html += `<div class="mt-3"><strong>สิ่งอำนวยความสะดวก:</strong><p>${building.amenities}</p></div>`;
                }
                
                Swal.fire({
                    title: 'รายละเอียดอาคาร',
                    html: html,
                    confirmButtonText: 'ปิด',
                    width: '700px'
                });
            }
        }

        // View room details
        function viewRoom(id) {
            const rooms = <?php echo json_encode($rooms); ?>;
            const room = rooms.find(r => r.id == id);
            
            if (room) {
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h5>${room.room_name || room.room_number}</h5>
                            <p><strong>หมายเลขห้อง:</strong> ${room.room_number}</p>
                            <p><strong>อาคาร:</strong> ${room.building_name} (${room.building_code})</p>
                            <p><strong>ประเภท:</strong> ${getRoomTypeThai(room.room_type)}</p>
                            <p><strong>ชั้น:</strong> ${room.floor || '-'}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>ความจุ:</strong> ${room.max_capacity || 2} คน</p>
                            <p><strong>ราคาต่อคืน:</strong> ฿${parseFloat(room.price_per_night || 0).toLocaleString('th-TH', {minimumFractionDigits: 2})}</p>
                            <p><strong>สถานะ:</strong> <span class="badge bg-${getRoomStatusBadge(room.status)}">${getRoomStatusThai(room.status)}</span></p>
                        </div>
                    </div>
                `;
                
                if (room.amenities) {
                    html += `<div class="mt-3"><strong>สิ่งอำนวยความสะดวก:</strong><p>${room.amenities}</p></div>`;
                }
                
                Swal.fire({
                    title: 'รายละเอียดห้องพัก',
                    html: html,
                    confirmButtonText: 'ปิด',
                    width: '700px'
                });
            }
        }

        // Helper function for building status
        function getBuildingStatusBadge(status) {
            const badges = {
                'active': 'success',
                'maintenance': 'warning',
                'inactive': 'secondary'
            };
            return badges[status] || 'secondary';
        }

        function getBuildingStatusThai(status) {
            const statuses = {
                'active': 'เปิดใช้งาน',
                'maintenance': 'ปิดซ่อมบำรุง',
                'inactive': 'ปิดใช้งาน'
            };
            return statuses[status] || status;
        }

        // Delete building
        function deleteBuilding(id, name) {
            Swal.fire({
                title: 'ยืนยันการลบ',
                html: `คุณต้องการลบอาคาร <strong>${name}</strong> ใช่หรือไม่?<br><small class="text-danger">การลบอาคารจะลบห้องพักทั้งหมดในอาคารนี้ด้วย</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'buildings.php',
                        type: 'POST',
                        data: {
                            action: 'delete_building',
                            id: id
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ',
                                    text: response.message || 'ลบอาคารสำเร็จ'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ผิดพลาด',
                                    text: response.message || 'ไม่สามารถลบอาคารได้'
                                });
                            }
                        }
                    });
                }
            });
        }

        // Delete room
        function deleteRoom(id, name) {
            Swal.fire({
                title: 'ยืนยันการลบ',
                html: `คุณต้องการลบห้องพัก <strong>${name}</strong> ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'buildings.php',
                        type: 'POST',
                        data: {
                            action: 'delete_room',
                            id: id
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ',
                                    text: response.message || 'ลบห้องพักสำเร็จ'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ผิดพลาด',
                                    text: response.message || 'ไม่สามารถลบห้องพักได้'
                                });
                            }
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>