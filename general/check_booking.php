<?php
/**
 * Check Booking Status Page
 * หน้าตรวจสอบสถานะการจอง (สำหรับบุคคลทั่วไป)
 */

session_start();
$pageTitle = "ตรวจสอบสถานะการจอง";

// เชื่อมต่อฐานข้อมูล
require_once '../config/database.php';
$db = getDatabaseConnection();

// ตรวจสอบการค้นหา
$bookingData = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_booking'])) {
    $bookingCode = trim($_POST['booking_code']);
    $guestPhone = trim($_POST['guest_phone']);
    
    if (empty($bookingCode) || empty($guestPhone)) {
        $error = "กรุณากรอกรหัสการจองและเบอร์โทรศัพท์";
    } else {
        try {
            $stmt = $db->prepare("SELECT 
                b.*, 
                u.full_name as creator_name,
                GROUP_CONCAT(r.room_number SEPARATOR ', ') as room_numbers,
                GROUP_CONCAT(bld.building_name SEPARATOR ', ') as building_names
            FROM bookings b
            LEFT JOIN users u ON b.created_by = u.id
            LEFT JOIN booking_rooms br ON b.id = br.booking_id
            LEFT JOIN rooms r ON br.room_id = r.id
            LEFT JOIN buildings bld ON r.building_id = bld.id
            WHERE b.booking_code = ? AND b.guest_phone = ?
            GROUP BY b.id");
            
            $stmt->execute([$bookingCode, $guestPhone]);
            $bookingData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bookingData) {
                $error = "ไม่พบข้อมูลการจอง กรุณาตรวจสอบรหัสการจองและเบอร์โทรศัพท์อีกครั้ง";
            }
        } catch (Exception $e) {
            $error = "เกิดข้อผิดพลาดในการค้นหา: " . $e->getMessage();
        }
    }
}

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
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Sarabun', sans-serif;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .header h1 {
            color: white;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .header p {
            color: rgba(255,255,255,0.9);
            font-size: 1.1rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 25px;
            border-bottom: none;
        }
        .card-body {
            padding: 30px;
        }
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #1e3c72;
            box-shadow: 0 0 0 0.25rem rgba(30, 60, 114, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3);
        }
        .result-card {
            margin-top: 30px;
            animation: fadeIn 0.5s ease-in;
        }
        .booking-detail {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .info-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .info-label {
            color: #666;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .info-value {
            color: #333;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .nav-buttons {
            margin-top: 30px;
            text-align: center;
        }
        .nav-buttons .btn {
            margin: 0 10px;
            padding: 10px 25px;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            .card-body {
                padding: 20px;
            }
            .nav-buttons .btn {
                display: block;
                width: 100%;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-search me-3"></i><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p>ตรวจสอบสถานะการจองบ้านพักรับรอง กองบิน7</p>
        </div>

        <!-- Search Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-search me-2"></i>ค้นหาการจอง</h3>
                <p class="mb-0 mt-2 opacity-75">กรุณากรอกรหัสการจองและเบอร์โทรศัพท์ที่ใช้ในการจอง</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="booking_code" class="form-label">รหัสการจอง *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-qrcode"></i></span>
                                <input type="text" class="form-control" id="booking_code" name="booking_code" 
                                       placeholder="ตัวอย่าง: BK20240115001" 
                                       value="<?php echo isset($_POST['booking_code']) ? htmlspecialchars($_POST['booking_code']) : ''; ?>"
                                       required>
                            </div>
                            <div class="form-text">รหัสการจองที่ได้รับเมื่อทำการจองสำเร็จ</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="guest_phone" class="form-label">เบอร์โทรศัพท์ *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="guest_phone" name="guest_phone" 
                                       placeholder="ตัวอย่าง: 0812345678" 
                                       value="<?php echo isset($_POST['guest_phone']) ? htmlspecialchars($_POST['guest_phone']) : ''; ?>"
                                       pattern="[0-9]{10}" required>
                            </div>
                            <div class="form-text">เบอร์โทรศัพท์ที่ใช้ในการจอง (10 หลัก)</div>
                        </div>
                        
                        <div class="col-12">
                            <div class="d-grid">
                                <button type="submit" name="search_booking" class="btn btn-primary btn-lg">
                                    <i class="fas fa-search me-2"></i>ตรวจสอบสถานะ
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Booking Results -->
        <?php if ($bookingData): ?>
        <div class="card result-card">
            <div class="card-header bg-success">
                <h3 class="mb-0"><i class="fas fa-check-circle me-2"></i>พบข้อมูลการจอง</h3>
            </div>
            <div class="card-body">
                <!-- Booking Status -->
                <div class="booking-detail">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4><?php echo htmlspecialchars($bookingData['guest_name']); ?></h4>
                            <p class="mb-0">
                                <span class="status-badge badge bg-<?php echo getStatusBadge($bookingData['status']); ?>">
                                    <?php echo getStatusThai($bookingData['status']); ?>
                                </span>
                                <span class="ms-3">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    <?php echo date('d/m/Y', strtotime($bookingData['check_in_date'])); ?> - 
                                    <?php echo date('d/m/Y', strtotime($bookingData['check_out_date'])); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <h5 class="text-primary"><?php echo htmlspecialchars($bookingData['booking_code']); ?></h5>
                        </div>
                    </div>
                </div>

                <!-- Booking Details -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-item">
                            <div class="info-label">ข้อมูลผู้พัก</div>
                            <div class="info-value"><?php echo htmlspecialchars($bookingData['guest_name']); ?></div>
                            <div class="mt-2">
                                <i class="fas fa-phone me-2 text-muted"></i>
                                <?php echo htmlspecialchars($bookingData['guest_phone']); ?>
                                <?php if ($bookingData['guest_email']): ?>
                                <br><i class="fas fa-envelope me-2 text-muted"></i>
                                <?php echo htmlspecialchars($bookingData['guest_email']); ?>
                                <?php endif; ?>
                                <?php if ($bookingData['guest_department']): ?>
                                <br><i class="fas fa-building me-2 text-muted"></i>
                                <?php echo htmlspecialchars($bookingData['guest_department']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">วันที่และเวลา</div>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">เช็คอิน</small><br>
                                    <strong><?php echo date('d/m/Y', strtotime($bookingData['check_in_date'])); ?></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">เช็คเอาต์</small><br>
                                    <strong><?php echo date('d/m/Y', strtotime($bookingData['check_out_date'])); ?></strong>
                                </div>
                            </div>
                            <?php 
                            $checkIn = new DateTime($bookingData['check_in_date']);
                            $checkOut = new DateTime($bookingData['check_out_date']);
                            $nights = $checkIn->diff($checkOut)->days;
                            ?>
                            <div class="mt-2">
                                <i class="fas fa-moon me-1 text-muted"></i>
                                <span class="text-muted">จำนวนคืน:</span>
                                <strong class="ms-1"><?php echo $nights; ?> คืน</strong>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-item">
                            <div class="info-label">ข้อมูลการจอง</div>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">ประเภท</small><br>
                                    <strong><?php echo getBookingTypeThai($bookingData['booking_type']); ?></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">จำนวนผู้พัก</small><br>
                                    <strong><?php echo $bookingData['number_of_guests'] ?? 1; ?> คน</strong>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($bookingData['room_numbers']): ?>
                        <div class="info-item">
                            <div class="info-label">ห้องพักที่จอง</div>
                            <div class="info-value">
                                <i class="fas fa-bed me-2 text-primary"></i>
                                <?php echo htmlspecialchars($bookingData['room_numbers']); ?>
                            </div>
                            <?php if ($bookingData['building_names']): ?>
                            <div class="mt-2">
                                <i class="fas fa-building me-2 text-muted"></i>
                                <span class="text-muted">อาคาร:</span>
                                <strong class="ms-1"><?php echo htmlspecialchars($bookingData['building_names']); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($bookingData['purpose']): ?>
                        <div class="info-item">
                            <div class="info-label">วัตถุประสงค์</div>
                            <div class="info-value">
                                <i class="fas fa-bullseye me-2 text-info"></i>
                                <?php echo htmlspecialchars($bookingData['purpose']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Additional Information -->
                <?php if ($bookingData['special_request']): ?>
                <div class="info-item mt-3">
                    <div class="info-label">คำขอพิเศษ</div>
                    <div class="alert alert-info mt-2">
                        <i class="fas fa-comment-dots me-2"></i>
                        <?php echo nl2br(htmlspecialchars($bookingData['special_request'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Status Information -->
                <div class="alert alert-<?php echo getStatusBadge($bookingData['status']); ?> mt-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle fa-2x"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="alert-heading">ข้อมูลสถานะ</h5>
                            <?php 
                            $statusMessages = [
                                'pending' => 'การจองของท่านอยู่ในสถานะรออนุมัติ กรุณารอการตรวจสอบจากเจ้าหน้าที่',
                                'approved' => 'การจองของท่านได้รับการอนุมัติเรียบร้อยแล้ว',
                                'rejected' => 'การจองของท่านไม่ได้รับการอนุมัติ กรุณาติดต่อเจ้าหน้าที่สำหรับข้อมูลเพิ่มเติม',
                                'checked_in' => 'ท่านได้ทำการเช็คอินเรียบร้อยแล้ว',
                                'checked_out' => 'ท่านได้ทำการเช็คเอาต์เรียบร้อยแล้ว',
                                'cancelled' => 'การจองของท่านถูกยกเลิกแล้ว'
                            ];
                            echo '<p>' . ($statusMessages[$bookingData['status']] ?? '') . '</p>';
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="nav-buttons">
                    <a href="booking_public.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>จองเพิ่มเติม
                    </a>
                    <button type="button" class="btn btn-success" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>พิมพ์ข้อมูล
                    </button>
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-home me-2"></i>กลับหน้าหลัก
                    </a>
                </div>
            </div>
        </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)): ?>
        <!-- No Results Found -->
        <div class="card result-card mt-4">
            <div class="card-header bg-danger">
                <h3 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>ไม่พบข้อมูล</h3>
            </div>
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-search fa-4x text-muted mb-3"></i>
                    <h4>ไม่พบข้อมูลการจองที่ตรงกับข้อมูลที่ระบุ</h4>
                    <p class="text-muted mb-4">กรุณาตรวจสอบรหัสการจองและเบอร์โทรศัพท์อีกครั้ง</p>
                </div>
                <div class="d-flex justify-content-center gap-3">
                    <a href="booking_public.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>จองใหม่
                    </a>
                    <button type="button" class="btn btn-outline-primary" onclick="window.history.back()">
                        <i class="fas fa-arrow-left me-2"></i>กลับไปค้นหา
                    </button>
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-home me-2"></i>หน้าหลัก
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer Information -->
        <div class="text-center text-white mt-5">
            <p class="mb-2">มีปัญหาเกี่ยวกับการจองหรือต้องการความช่วยเหลือ?</p>
            <div class="d-flex justify-content-center gap-3">
                <div>
                    <i class="fas fa-phone-alt me-2"></i>
                    <span>โทร: 0XX-XXX-XXXX</span>
                </div>
                <div>
                    <i class="fas fa-envelope me-2"></i>
                    <span>อีเมล: support@wing7.com</span>
                </div>
                <div>
                    <i class="fas fa-clock me-2"></i>
                    <span>เวลาให้บริการ: 08:30 - 16:30 น.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto format phone number
        document.getElementById('guest_phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            e.target.value = value;
        });

        // Auto format booking code
        document.getElementById('booking_code').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });

        // Focus on first input when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const bookingCodeInput = document.getElementById('booking_code');
            if (bookingCodeInput) {
                bookingCodeInput.focus();
            }
        });

        // Print function
        function printBookingDetails() {
            const printContent = document.querySelector('.result-card').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>รายละเอียดการจอง - ระบบจองบ้านพักรับรอง กองบิน7</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        @media print {
                            body { padding: 20px; }
                            .no-print { display: none; }
                            .card { border: 1px solid #ddd; box-shadow: none; }
                            .status-badge { color: white; padding: 5px 10px; border-radius: 15px; }
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="text-center mb-4">
                            <h3>ระบบจองบ้านพักรับรอง กองบิน7</h3>
                            <h4>รายละเอียดการจอง</h4>
                            <p>พิมพ์เมื่อ: ${new Date().toLocaleDateString('th-TH')} ${new Date().toLocaleTimeString('th-TH')}</p>
                        </div>
                        ${printContent}
                        <div class="mt-4 text-center text-muted small">
                            <p>เอกสารนี้เป็นเอกสารอ้างอิง ไม่ใช่เอกสารรับรองการจอง</p>
                        </div>
                    </div>
                </body>
                </html>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }

        // Share function (if needed)
        function shareBooking() {
            if (navigator.share) {
                navigator.share({
                    title: 'รายละเอียดการจองบ้านพักรับรอง กองบิน7',
                    text: 'ตรวจสอบรายละเอียดการจองของท่านได้ที่นี่',
                    url: window.location.href
                })
                .then(() => console.log('Shared successfully'))
                .catch((error) => console.log('Error sharing:', error));
            } else {
                alert('ฟังก์ชันการแชร์ไม่รองรับบนเบราว์เซอร์นี้');
            }
        }
    </script>
</body>
</html>