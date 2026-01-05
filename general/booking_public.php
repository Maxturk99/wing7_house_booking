<?php
/**
 * Public Booking Form
 * แบบฟอร์มจองสำหรับบุคคลทั่วไป
 */

session_start();

// เชื่อมต่อฐานข้อมูล
require_once '../config/database.php';
$db = getDatabaseConnection();

$pageTitle = "จองบ้านพักสำหรับบุคคลทั่วไป";

// ตัวแปรสำหรับข้อความ
$error = '';
$success = '';
$bookingCode = '';

// ดึงข้อมูลอาคารที่ใช้งานได้
$buildingsStmt = $db->query("SELECT * FROM buildings WHERE status = 'active' AND building_type = 'guest_house' ORDER BY building_name");
$buildings = $buildingsStmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลห้องพักว่าง
$roomsStmt = $db->query("SELECT r.*, b.building_name 
                         FROM rooms r 
                         JOIN buildings b ON r.building_id = b.id 
                         WHERE r.status = 'available' 
                         AND b.status = 'active'
                         ORDER BY b.building_name, r.room_number");
$availableRooms = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);

// ตรวจสอบการ submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // รับข้อมูลจากฟอร์ม
        $guest_name = htmlspecialchars(trim($_POST['guest_name']));
        $guest_phone = htmlspecialchars(trim($_POST['guest_phone']));
        $guest_email = htmlspecialchars(trim($_POST['guest_email'] ?? ''));
        $guest_department = htmlspecialchars(trim($_POST['guest_department'] ?? ''));
        $guest_rank = htmlspecialchars(trim($_POST['guest_rank'] ?? ''));
        $check_in_date = $_POST['check_in_date'];
        $check_out_date = $_POST['check_out_date'];
        $number_of_rooms = $_POST['number_of_rooms'] ?? 1;
        $number_of_guests = $_POST['number_of_guests'] ?? 1;
        $purpose = htmlspecialchars(trim($_POST['purpose'] ?? ''));
        $building_id = $_POST['building_id'] ?? null;
        $room_id = $_POST['room_id'] ?? null;
        $special_request = htmlspecialchars(trim($_POST['special_request'] ?? ''));
        
        // ตรวจสอบข้อมูลที่จำเป็น
        if (empty($guest_name) || empty($guest_phone) || empty($check_in_date) || empty($check_out_date)) {
            throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
        }
        
        // ตรวจสอบวันที่
        $checkIn = new DateTime($check_in_date);
        $checkOut = new DateTime($check_out_date);
        $today = new DateTime();
        
        if ($checkIn < $today) {
            throw new Exception('วันที่เช็คอินต้องไม่ใช้วันที่ผ่านมาแล้ว');
        }
        
        if ($checkOut <= $checkIn) {
            throw new Exception('วันที่เช็คเอาต์ต้องมากกว่าวันที่เช็คอิน');
        }
        
        // ตรวจสอบจำนวนวันที่จอง (สูงสุด 30 วัน)
        $interval = $checkIn->diff($checkOut);
        if ($interval->days > 30) {
            throw new Exception('ไม่สามารถจองเกิน 30 วัน');
        }
        
        // สร้างรหัสการจอง
        $year = date('Y');
        $month = date('m');
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?");
        $stmt->execute([$year, $month]);
        $result = $stmt->fetch();
        $sequence = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
        $booking_code = "BK{$year}{$month}{$sequence}";
        
        // คำนวณราคา
        $total_amount = 0;
        if ($room_id) {
            // ดึงราคาห้อง
            $roomStmt = $db->prepare("SELECT price_per_night FROM rooms WHERE id = ?");
            $roomStmt->execute([$room_id]);
            $roomPrice = $roomStmt->fetchColumn();
            $nights = $interval->days;
            $total_amount = $roomPrice * $nights;
        } else {
            // คำนวณตามอาคาร
            $buildingStmt = $db->prepare("SELECT price_per_night FROM buildings WHERE id = ?");
            $buildingStmt->execute([$building_id]);
            $buildingPrice = $buildingStmt->fetchColumn();
            $nights = $interval->days;
            $total_amount = $buildingPrice * $nights * $number_of_rooms;
        }
        
        // บันทึกการจอง
        $stmt = $db->prepare("INSERT INTO bookings (
            booking_code, guest_name, guest_phone, guest_email, guest_department,
            guest_rank, booking_type, purpose, check_in_date, check_out_date,
            number_of_rooms, number_of_guests, total_amount, net_amount,
            special_request, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $booking_code,
            $guest_name,
            $guest_phone,
            $guest_email,
            $guest_department,
            $guest_rank,
            'personal', // ประเภทส่วนตัวสำหรับบุคคลทั่วไป
            $purpose,
            $check_in_date,
            $check_out_date,
            $number_of_rooms,
            $number_of_guests,
            $total_amount,
            $total_amount, // net_amount เท่ากับ total_amount สำหรับบุคคลทั่วไป
            $special_request,
            'pending'
        ]);
        
        $booking_id = $db->lastInsertId();
        
        // บันทึกห้องพักที่จอง (ถ้ามี)
        if ($room_id && $building_id) {
            $stmt = $db->prepare("INSERT INTO booking_rooms (booking_id, room_id, building_id, price_per_night, check_in_date, check_out_date) 
                                 VALUES (?, ?, ?, ?, ?, ?)");
            
            $roomStmt = $db->prepare("SELECT price_per_night FROM rooms WHERE id = ?");
            $roomStmt->execute([$room_id]);
            $roomPrice = $roomStmt->fetchColumn();
            
            $stmt->execute([$booking_id, $room_id, $building_id, $roomPrice, $check_in_date, $check_out_date]);
            
            // อัปเดตสถานะห้อง
            $updateStmt = $db->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
            $updateStmt->execute([$room_id]);
        }
        
        $db->commit();
        
        // ส่งอีเมลยืนยัน (ถ้ามีอีเมล)
        if ($guest_email) {
            sendConfirmationEmail($guest_email, $booking_code, $guest_name, $check_in_date, $check_out_date);
        }
        
        $success = "การจองสำเร็จ! รหัสการจองของคุณคือ: <strong>{$booking_code}</strong>";
        $bookingCode = $booking_code;
        
        // รีเซ็ตฟอร์ม
        $_POST = [];
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

// ฟังก์ชันส่งอีเมลยืนยัน
function sendConfirmationEmail($email, $booking_code, $name, $check_in, $check_out) {
    $to = $email;
    $subject = "ยืนยันการจองบ้านพักรับรอง กองบิน7 - {$booking_code}";
    $message = "
    <html>
    <head>
        <title>ยืนยันการจองบ้านพักรับรอง</title>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; }
            .header { background: #1e3c72; color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center; }
            .content { padding: 20px; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
            .booking-code { font-size: 24px; font-weight: bold; color: #1e3c72; }
            .details { margin: 20px 0; }
            .details td { padding: 8px 0; }
            .details td:first-child { font-weight: bold; width: 150px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>ระบบจองบ้านพักรับรอง กองบิน7</h2>
            </div>
            <div class='content'>
                <h3>สวัสดีคุณ {$name}</h3>
                <p>การจองบ้านพักรับรองของคุณได้รับการยืนยันแล้ว รายละเอียดดังนี้:</p>
                
                <p class='booking-code'>รหัสการจอง: {$booking_code}</p>
                
                <table class='details'>
                    <tr>
                        <td>ชื่อผู้พัก:</td>
                        <td>{$name}</td>
                    </tr>
                    <tr>
                        <td>วันที่เช็คอิน:</td>
                        <td>" . date('d/m/Y', strtotime($check_in)) . "</td>
                    </tr>
                    <tr>
                        <td>วันที่เช็คเอาต์:</td>
                        <td>" . date('d/m/Y', strtotime($check_out)) . "</td>
                    </tr>
                    <tr>
                        <td>สถานะ:</td>
                        <td><strong>รอการอนุมัติ</strong></td>
                    </tr>
                </table>
                
                <p>กรุณานำรหัสการจองนี้มาแสดงที่จุดลงทะเบียน</p>
                <p>หากมีข้อสงสัยประการใด กรุณาติดต่อเจ้าหน้าที่ โทร. 0-1234-5678</p>
            </div>
            <div class='footer'>
                <p>อีเมลนี้ถูกส่งจากระบบจองบ้านพักรับรอง กองบิน7</p>
                <p>กรุณาอย่าตอบกลับอีเมลนี้</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: booking@wing7.local\r\n";
    $headers .= "Reply-To: no-reply@wing7.local\r\n";
    
    @mail($to, $subject, $message, $headers);
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
    <!-- Datepicker CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/css/bootstrap-datepicker.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
        }
        .booking-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .booking-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .booking-body {
            padding: 40px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #dee2e6;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
        }
        .step.active .step-number {
            background: #1e3c72;
            color: white;
        }
        .step.completed .step-number {
            background: #28a745;
            color: white;
        }
        .step-text {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .step.active .step-text {
            color: #1e3c72;
            font-weight: 500;
        }
        .step-section {
            display: none;
        }
        .step-section.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-label {
            font-weight: 600;
            color: #333;
        }
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1e3c72;
            box-shadow: 0 0 0 0.25rem rgba(30, 60, 114, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #172b4d 0%, #1e3c72 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.2);
        }
        .booking-success {
            text-align: center;
            padding: 40px;
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .room-availability {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .room-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .room-card:hover {
            border-color: #1e3c72;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .room-card.selected {
            border-color: #1e3c72;
            background-color: rgba(30, 60, 114, 0.05);
        }
        .room-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .room-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: #1e3c72;
        }
        .required::after {
            content: ' *';
            color: #dc3545;
        }
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        @media (max-width: 768px) {
            .booking-body {
                padding: 20px;
            }
            .step-indicator {
                flex-wrap: wrap;
                gap: 20px;
            }
            .step-indicator::before {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <i class="fas fa-home fa-4x mb-3"></i>
                </div>
                <div class="col-md-8">
                    <h1 class="mb-3">ระบบจองบ้านพักรับรอง กองบิน7</h1>
                    <p class="lead mb-0">สำหรับบุคคลทั่วไปและผู้มาติดต่อราชการ</p>
                </div>
                <div class="col-md-2 text-center">
                    <a href="../login.php" class="btn btn-outline-light">
                        <i class="fas fa-sign-in-alt me-2"></i>ล็อกอินเจ้าหน้าที่
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <div class="booking-container">
            <!-- Step Indicator -->
            <div class="booking-header">
                <h2 class="mb-3">แบบฟอร์มจองบ้านพักรับรอง</h2>
                <div class="step-indicator">
                    <div class="step active" data-step="1">
                        <div class="step-number">1</div>
                        <div class="step-text">ข้อมูลส่วนตัว</div>
                    </div>
                    <div class="step" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-text">วันที่พัก</div>
                    </div>
                    <div class="step" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-text">เลือกห้องพัก</div>
                    </div>
                    <div class="step" data-step="4">
                        <div class="step-number">4</div>
                        <div class="step-text">ยืนยันการจอง</div>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show m-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="booking-success">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="mb-4">การจองสำเร็จ!</h3>
                <div class="alert alert-success mb-4">
                    <p class="mb-2"><?php echo $success; ?></p>
                    <p class="mb-0">กรุณาบันทึกรหัสการจองนี้เพื่อใช้ตรวจสอบสถานะ</p>
                </div>
                <div class="booking-details mb-4 p-4 bg-light rounded">
                    <h5 class="mb-3">ข้อมูลการจอง</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>รหัสการจอง:</strong> <?php echo $bookingCode; ?></p>
                            <p><strong>ชื่อผู้พัก:</strong> <?php echo htmlspecialchars($_POST['guest_name'] ?? ''); ?></p>
                            <p><strong>เบอร์ติดต่อ:</strong> <?php echo htmlspecialchars($_POST['guest_phone'] ?? ''); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>วันที่เช็คอิน:</strong> <?php echo date('d/m/Y', strtotime($_POST['check_in_date'] ?? '')); ?></p>
                            <p><strong>วันที่เช็คเอาต์:</strong> <?php echo date('d/m/Y', strtotime($_POST['check_out_date'] ?? '')); ?></p>
                            <p><strong>สถานะ:</strong> <span class="badge bg-warning">รออนุมัติ</span></p>
                        </div>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn btn-primary me-2" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>พิมพ์ใบจอง
                    </button>
                    <a href="check_booking.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-search me-2"></i>ตรวจสอบสถานะ
                    </a>
                    <a href="booking_public.php" class="btn btn-secondary">
                        <i class="fas fa-plus me-2"></i>จองใหม่
                    </a>
                </div>
            </div>
            <?php else: ?>

            <!-- Booking Form -->
            <form id="bookingForm" method="POST" class="booking-body">
                <!-- Step 1: Personal Information -->
                <div class="step-section active" id="step1">
                    <h4 class="mb-4">ข้อมูลส่วนตัว</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="guest_name" class="form-label required">ชื่อ-นามสกุล</label>
                            <input type="text" class="form-control" id="guest_name" name="guest_name" 
                                   value="<?php echo htmlspecialchars($_POST['guest_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="guest_phone" class="form-label required">เบอร์โทรศัพท์</label>
                            <input type="tel" class="form-control" id="guest_phone" name="guest_phone" 
                                   value="<?php echo htmlspecialchars($_POST['guest_phone'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="guest_email" class="form-label">อีเมล</label>
                            <input type="email" class="form-control" id="guest_email" name="guest_email" 
                                   value="<?php echo htmlspecialchars($_POST['guest_email'] ?? ''); ?>">
                            <div class="form-text">กรอกอีเมลเพื่อรับการยืนยันและอัปเดตสถานะ</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="guest_department" class="form-label">สังกัด/หน่วยงาน</label>
                            <input type="text" class="form-control" id="guest_department" name="guest_department" 
                                   value="<?php echo htmlspecialchars($_POST['guest_department'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="guest_rank" class="form-label">ยศ/ตำแหน่ง</label>
                            <input type="text" class="form-control" id="guest_rank" name="guest_rank" 
                                   value="<?php echo htmlspecialchars($_POST['guest_rank'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="purpose" class="form-label">วัตถุประสงค์</label>
                            <input type="text" class="form-control" id="purpose" name="purpose" 
                                   value="<?php echo htmlspecialchars($_POST['purpose'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="nav-buttons">
                        <div></div>
                        <button type="button" class="btn btn-primary next-step" data-next="2">
                            ถัดไป <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Dates -->
                <div class="step-section" id="step2">
                    <h4 class="mb-4">วันที่พัก</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="check_in_date" class="form-label required">วันที่เช็คอิน</label>
                            <input type="date" class="form-control" id="check_in_date" name="check_in_date" 
                                   value="<?php echo $_POST['check_in_date'] ?? ''; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="check_out_date" class="form-label required">วันที่เช็คเอาต์</label>
                            <input type="date" class="form-control" id="check_out_date" name="check_out_date" 
                                   value="<?php echo $_POST['check_out_date'] ?? ''; ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="number_of_rooms" class="form-label">จำนวนห้อง</label>
                            <input type="number" class="form-control" id="number_of_rooms" name="number_of_rooms" 
                                   min="1" max="10" value="<?php echo $_POST['number_of_rooms'] ?? 1; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="number_of_guests" class="form-label">จำนวนผู้พัก</label>
                            <input type="number" class="form-control" id="number_of_guests" name="number_of_guests" 
                                   min="1" max="20" value="<?php echo $_POST['number_of_guests'] ?? 1; ?>">
                        </div>
                    </div>
                    <div class="nav-buttons">
                        <button type="button" class="btn btn-outline-secondary prev-step" data-prev="1">
                            <i class="fas fa-arrow-left me-2"></i> ย้อนกลับ
                        </button>
                        <button type="button" class="btn btn-primary next-step" data-next="3">
                            ถัดไป <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Room Selection -->
                <div class="step-section" id="step3">
                    <h4 class="mb-4">เลือกห้องพัก</h4>
                    
                    <div class="mb-4">
                        <label class="form-label">เลือกอาคาร</label>
                        <select class="form-select mb-3" id="building_id" name="building_id">
                            <option value="">เลือกอาคาร</option>
                            <?php foreach ($buildings as $building): ?>
                            <option value="<?php echo $building['id']; ?>" 
                                <?php echo ($_POST['building_id'] ?? '') == $building['id'] ? 'selected' : ''; ?>>
                                <?php echo $building['building_name']; ?> - ฿<?php echo number_format($building['price_per_night'], 2); ?>/คืน
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div class="room-availability">
                            <h6>ห้องพักที่ว่าง</h6>
                            <div id="roomList">
                                <?php foreach ($availableRooms as $room): ?>
                                <div class="room-card" data-room-id="<?php echo $room['id']; ?>" 
                                     data-building-id="<?php echo $room['building_id']; ?>" 
                                     data-price="<?php echo $room['price_per_night']; ?>">
                                    <div class="room-info">
                                        <div>
                                            <h6 class="mb-1"><?php echo $room['building_name']; ?> - ห้อง <?php echo $room['room_number']; ?></h6>
                                            <p class="mb-1">
                                                <small class="text-muted">ประเภท: <?php echo $room['room_type']; ?> | ความจุ: <?php echo $room['max_capacity']; ?> คน</small>
                                            </p>
                                        </div>
                                        <div class="room-price">
                                            ฿<?php echo number_format($room['price_per_night'], 2); ?>/คืน
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="room_id" name="room_id" value="<?php echo $_POST['room_id'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="special_request" class="form-label">คำขอพิเศษ</label>
                        <textarea class="form-control" id="special_request" name="special_request" rows="3"><?php echo htmlspecialchars($_POST['special_request'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>หมายเหตุ:</strong> หลังจากส่งแบบฟอร์ม การจองจะอยู่ในสถานะ "รออนุมัติ" กรุณารอการอนุมัติจากเจ้าหน้าที่
                    </div>
                    
                    <div class="nav-buttons">
                        <button type="button" class="btn btn-outline-secondary prev-step" data-prev="2">
                            <i class="fas fa-arrow-left me-2"></i> ย้อนกลับ
                        </button>
                        <button type="button" class="btn btn-primary next-step" data-next="4">
                            ถัดไป <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 4: Confirmation -->
                <div class="step-section" id="step4">
                    <h4 class="mb-4">ยืนยันการจอง</h4>
                    
                    <div class="booking-summary mb-4 p-4 bg-light rounded">
                        <h5 class="mb-3">สรุปข้อมูลการจอง</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>ชื่อผู้พัก:</strong> <span id="summary_name"></span></p>
                                <p><strong>เบอร์ติดต่อ:</strong> <span id="summary_phone"></span></p>
                                <p><strong>อีเมล:</strong> <span id="summary_email"></span></p>
                                <p><strong>สังกัด:</strong> <span id="summary_department"></span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>วันที่เช็คอิน:</strong> <span id="summary_checkin"></span></p>
                                <p><strong>วันที่เช็คเอาต์:</strong> <span id="summary_checkout"></span></p>
                                <p><strong>จำนวนห้อง:</strong> <span id="summary_rooms"></span></p>
                                <p><strong>จำนวนผู้พัก:</strong> <span id="summary_guests"></span></p>
                                <p><strong>ห้องพัก:</strong> <span id="summary_room"></span></p>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <p><strong>วัตถุประสงค์:</strong> <span id="summary_purpose"></span></p>
                                <p><strong>คำขอพิเศษ:</strong> <span id="summary_request"></span></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="agree_terms" required>
                        <label class="form-check-label" for="agree_terms">
                            ข้าพเจ้ายอมรับข้อกำหนดและเงื่อนไขการจองบ้านพักรับรองกองบิน 7
                        </label>
                    </div>
                    
                    <div class="nav-buttons">
                        <button type="button" class="btn btn-outline-secondary prev-step" data-prev="3">
                            <i class="fas fa-arrow-left me-2"></i> ย้อนกลับ
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i> ยืนยันการจอง
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>กองบิน 7</h5>
                    <p>บ้านพักรับรองสำหรับเจ้าหน้าที่และผู้มาติดต่อราชการ</p>
                </div>
                <div class="col-md-4">
                    <h5>ติดต่อเรา</h5>
                    <p><i class="fas fa-phone me-2"></i> 0-1234-5678</p>
                    <p><i class="fas fa-envelope me-2"></i> booking@wing7.local</p>
                </div>
                <div class="col-md-4">
                    <h5>เวลาทำการ</h5>
                    <p>จันทร์ - ศุกร์: 08:30 - 16:30 น.</p>
                    <p>เสาร์ - อาทิตย์: 09:00 - 15:00 น.</p>
                </div>
            </div>
            <hr class="bg-light">
            <div class="text-center">
                <p class="mb-0">© 2024 ระบบจองบ้านพักรับรอง กองบิน 7</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/locales/bootstrap-datepicker.th.min.js" charset="UTF-8"></script>
    
    <script>
        // Multi-step form functionality
        $(document).ready(function() {
            // Set minimum date for check-in to today
            const today = new Date().toISOString().split('T')[0];
            $('#check_in_date').attr('min', today);
            
            // Update check-out minimum based on check-in
            $('#check_in_date').on('change', function() {
                $('#check_out_date').attr('min', $(this).val());
            });
            
            // Room selection
            $('.room-card').on('click', function() {
                $('.room-card').removeClass('selected');
                $(this).addClass('selected');
                $('#room_id').val($(this).data('room-id'));
                $('#building_id').val($(this).data('building-id'));
            });
            
            // Next step button
            $('.next-step').on('click', function() {
                const nextStep = $(this).data('next');
                const currentStep = nextStep - 1;
                
                // Validate current step
                if (!validateStep(currentStep)) {
                    return;
                }
                
                // Update summary for step 4
                if (nextStep === 4) {
                    updateSummary();
                }
                
                // Change step
                changeStep(nextStep);
            });
            
            // Previous step button
            $('.prev-step').on('click', function() {
                const prevStep = $(this).data('prev');
                changeStep(prevStep);
            });
            
            // Step change function
            function changeStep(step) {
                // Update step indicator
                $('.step').removeClass('active completed');
                $('.step').each(function() {
                    const stepNum = $(this).data('step');
                    if (stepNum < step) {
                        $(this).addClass('completed');
                    } else if (stepNum == step) {
                        $(this).addClass('active');
                    }
                });
                
                // Show corresponding section
                $('.step-section').removeClass('active');
                $(`#step${step}`).addClass('active');
                
                // Scroll to top
                $('html, body').animate({
                    scrollTop: $('.booking-header').offset().top
                }, 500);
            }
            
            // Validate step
            function validateStep(step) {
                let isValid = true;
                const stepElement = $(`#step${step}`);
                
                // Check required fields in current step
                stepElement.find('[required]').each(function() {
                    if (!$(this).val()) {
                        isValid = false;
                        $(this).addClass('is-invalid');
                        $(this).siblings('.invalid-feedback').remove();
                        $(this).after('<div class="invalid-feedback">กรุณากรอกข้อมูลนี้</div>');
                    } else {
                        $(this).removeClass('is-invalid');
                        $(this).siblings('.invalid-feedback').remove();
                    }
                });
                
                // Special validation for step 2 (dates)
                if (step === 2) {
                    const checkIn = $('#check_in_date').val();
                    const checkOut = $('#check_out_date').val();
                    
                    if (checkIn && checkOut) {
                        const checkInDate = new Date(checkIn);
                        const checkOutDate = new Date(checkOut);
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        
                        if (checkInDate < today) {
                            alert('วันที่เช็คอินต้องไม่ใช้วันที่ผ่านมาแล้ว');
                            isValid = false;
                        }
                        
                        if (checkOutDate <= checkInDate) {
                            alert('วันที่เช็คเอาต์ต้องมากกว่าวันที่เช็คอิน');
                            isValid = false;
                        }
                        
                        // Check maximum booking days (30)
                        const timeDiff = checkOutDate.getTime() - checkInDate.getTime();
                        const dayDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));
                        
                        if (dayDiff > 30) {
                            alert('ไม่สามารถจองเกิน 30 วัน');
                            isValid = false;
                        }
                    }
                }
                
                // Special validation for step 3 (room/building selection)
                if (step === 3) {
                    const buildingId = $('#building_id').val();
                    const roomId = $('#room_id').val();
                    
                    if (!buildingId && !roomId) {
                        alert('กรุณาเลือกอาคารหรือห้องพัก');
                        isValid = false;
                    }
                }
                
                return isValid;
            }
            
            // Update summary for confirmation step
            function updateSummary() {
                // Personal information
                $('#summary_name').text($('#guest_name').val());
                $('#summary_phone').text($('#guest_phone').val());
                $('#summary_email').text($('#guest_email').val() || '-');
                $('#summary_department').text($('#guest_department').val() || '-');
                
                // Dates and numbers
                $('#summary_checkin').text(formatDate($('#check_in_date').val()));
                $('#summary_checkout').text(formatDate($('#check_out_date').val()));
                $('#summary_rooms').text($('#number_of_rooms').val());
                $('#summary_guests').text($('#number_of_guests').val());
                
                // Room selection
                const buildingId = $('#building_id').val();
                const roomId = $('#room_id').val();
                let roomText = '-';
                
                if (roomId) {
                    const selectedRoom = $(`.room-card[data-room-id="${roomId}"]`);
                    if (selectedRoom.length) {
                        const buildingName = selectedRoom.find('h6').text().split(' - ')[0];
                        const roomNumber = selectedRoom.find('h6').text().split(' - ')[1];
                        const roomPrice = selectedRoom.data('price');
                        roomText = `${buildingName} - ${roomNumber} (฿${roomPrice.toLocaleString()}/คืน)`;
                    }
                } else if (buildingId) {
                    const selectedBuilding = $('#building_id option:selected').text();
                    roomText = selectedBuilding;
                }
                
                $('#summary_room').text(roomText);
                
                // Other information
                $('#summary_purpose').text($('#purpose').val() || '-');
                $('#summary_request').text($('#special_request').val() || '-');
            }
            
            // Format date to Thai format
            function formatDate(dateString) {
                if (!dateString) return '-';
                const date = new Date(dateString);
                const day = date.getDate().toString().padStart(2, '0');
                const month = (date.getMonth() + 1).toString().padStart(2, '0');
                const year = date.getFullYear() + 543;
                return `${day}/${month}/${year}`;
            }
            
            // Form submission
            $('#bookingForm').on('submit', function(e) {
                if (!validateStep(4)) {
                    e.preventDefault();
                    return false;
                }
                
                if (!$('#agree_terms').is(':checked')) {
                    e.preventDefault();
                    alert('กรุณายอมรับข้อกำหนดและเงื่อนไข');
                    return false;
                }
                
                // Show loading state
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>กำลังดำเนินการ...');
                submitBtn.prop('disabled', true);
                
                return true;
            });
        });
    </script>
</body>
</html>