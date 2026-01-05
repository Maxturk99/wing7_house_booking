<?php
session_start();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit();
}

require_once '../config/database.php';
$db = getDatabaseConnection();

$action = $_GET['action'] ?? '';
$bookingId = $_GET['id'] ?? 0;

// ตรวจสอบว่า ID เป็นตัวเลข
if (!is_numeric($bookingId)) {
    echo json_encode(['success' => false, 'message' => 'ID ไม่ถูกต้อง']);
    exit();
}

// ดึงข้อมูลการจอง
$stmt = $db->prepare("SELECT b.*, u.full_name as creator_name 
                     FROM bookings b 
                     LEFT JOIN users u ON b.created_by = u.id 
                     WHERE b.id = ?");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลการจอง']);
    exit();
}

// ตรวจสอบสิทธิ์การแก้ไข
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

if ($booking['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'ไม่สามารถแก้ไขการจองที่ไม่อยู่ในสถานะรออนุมัติได้']);
    exit();
}

if ($userRole !== 'admin' && $booking['created_by'] != $userId) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์แก้ไขการจองนี้']);
    exit();
}

// ดึงห้องพักที่ถูกจองแล้ว
$stmt = $db->prepare("SELECT br.*, r.room_number, r.room_name, r.price_per_night, b.building_name 
                     FROM booking_rooms br 
                     JOIN rooms r ON br.room_id = r.id 
                     JOIN buildings b ON r.building_id = b.id 
                     WHERE br.booking_id = ?");
$stmt->execute([$bookingId]);
$bookedRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงห้องพักทั้งหมดที่ว่าง
$availableRoomsStmt = $db->query("SELECT r.*, b.building_name, b.building_code 
                                 FROM rooms r 
                                 JOIN buildings b ON r.building_id = b.id 
                                 WHERE r.status = 'available' 
                                 ORDER BY b.building_name, r.room_number");
$availableRooms = $availableRoomsStmt->fetchAll(PDO::FETCH_ASSOC);

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

switch ($action) {
    case 'view':
        // แสดงรายละเอียดการจอง
        $html = '
        <div class="row">
            <div class="col-md-6">
                <h5>' . htmlspecialchars($booking['booking_code']) . '</h5>
                <p><strong>ผู้พัก:</strong> ' . htmlspecialchars($booking['guest_name']) . '</p>
                <p><strong>เบอร์โทร:</strong> ' . htmlspecialchars($booking['guest_phone']) . '</p>
                ' . ($booking['guest_email'] ? '<p><strong>อีเมล:</strong> ' . htmlspecialchars($booking['guest_email']) . '</p>' : '') . '
                ' . ($booking['guest_department'] ? '<p><strong>สังกัด/หน่วยงาน:</strong> ' . htmlspecialchars($booking['guest_department']) . '</p>' : '') . '
            </div>
            <div class="col-md-6">
                <p><strong>วันที่เช็คอิน:</strong> ' . date('d/m/Y', strtotime($booking['check_in_date'])) . '</p>
                <p><strong>วันที่เช็คเอาต์:</strong> ' . date('d/m/Y', strtotime($booking['check_out_date'])) . '</p>
                <p><strong>จำนวนผู้พัก:</strong> ' . $booking['number_of_guests'] . ' คน</p>
                <p><strong>วัตถุประสงค์:</strong> ' . htmlspecialchars($booking['purpose'] ?: '-') . '</p>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <p><strong>ประเภทการจอง:</strong> ' . getBookingTypeThai($booking['booking_type']) . '</p>
                <p><strong>สถานะ:</strong> <span class="badge bg-warning">' . getStatusThai($booking['status']) . '</span></p>
                <p><strong>สร้างโดย:</strong> ' . htmlspecialchars($booking['creator_name']) . '</p>
            </div>
            <div class="col-md-6">
                <p><strong>วันที่สร้าง:</strong> ' . date('d/m/Y H:i', strtotime($booking['created_at'])) . '</p>
            </div>
        </div>';
        
        if ($booking['special_request']) {
            $html .= '
            <div class="mt-3">
                <strong>คำขอพิเศษ:</strong>
                <p>' . htmlspecialchars($booking['special_request']) . '</p>
            </div>';
        }
        
        // แสดงห้องพักที่จอง
        if (count($bookedRooms) > 0) {
            $html .= '
            <div class="mt-3">
                <h6>ห้องพักที่จอง:</h6>
                <div class="row">';
            
            foreach ($bookedRooms as $room) {
                $html .= '
                <div class="col-md-6 mb-2">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">ห้อง ' . htmlspecialchars($room['room_number']) . '</h6>
                            <p class="card-text">
                                <small>อาคาร: ' . htmlspecialchars($room['building_name']) . '</small><br>
                                ' . ($room['room_name'] ? '<small>ชื่อห้อง: ' . htmlspecialchars($room['room_name']) . '</small><br>' : '') . '
                                <small>ราคาต่อคืน: ฿' . number_format($room['price_per_night'], 2) . '</small>
                            </p>
                        </div>
                    </div>
                </div>';
            }
            
            $html .= '
                </div>
            </div>';
        }
        
        echo $html;
        break;
        
    case 'edit':
        // แสดงฟอร์มแก้ไข
        $bookedRoomIds = array_column($bookedRooms, 'room_id');
        $selectedRoomIds = implode(',', $bookedRoomIds);
        
        echo '
        <form id="editBookingForm" action="bookings.php" method="POST">
            <input type="hidden" name="action" value="edit_booking">
            <input type="hidden" name="id" value="' . $booking['id'] . '">
            <input type="hidden" id="selectedRoomIds" name="selected_room_ids" value="' . $selectedRoomIds . '">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="edit_guest_name" class="form-label">ชื่อผู้พัก *</label>
                    <input type="text" class="form-control" id="edit_guest_name" name="guest_name" value="' . htmlspecialchars($booking['guest_name']) . '" required>
                </div>
                <div class="col-md-6">
                    <label for="edit_guest_phone" class="form-label">เบอร์ติดต่อ *</label>
                    <input type="tel" class="form-control" id="edit_guest_phone" name="guest_phone" value="' . htmlspecialchars($booking['guest_phone']) . '" pattern="[0-9]{10}" required>
                    <div class="form-text">กรุณากรอกเบอร์โทรศัพท์ 10 หลัก</div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="edit_guest_email" class="form-label">อีเมล</label>
                    <input type="email" class="form-control" id="edit_guest_email" name="guest_email" value="' . htmlspecialchars($booking['guest_email']) . '">
                </div>
                <div class="col-md-6">
                    <label for="edit_guest_department" class="form-label">สังกัด/หน่วยงาน</label>
                    <input type="text" class="form-control" id="edit_guest_department" name="guest_department" value="' . htmlspecialchars($booking['guest_department']) . '">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="edit_check_in_date" class="form-label">วันที่เช็คอิน *</label>
                    <input type="date" class="form-control" id="edit_check_in_date" name="check_in_date" value="' . $booking['check_in_date'] . '" required>
                </div>
                <div class="col-md-4">
                    <label for="edit_check_out_date" class="form-label">วันที่เช็คเอาต์ *</label>
                    <input type="date" class="form-control" id="edit_check_out_date" name="check_out_date" value="' . $booking['check_out_date'] . '" required>
                </div>
                <div class="col-md-4">
                    <label for="edit_booking_type" class="form-label">ประเภทการจอง</label>
                    <select class="form-select" id="edit_booking_type" name="booking_type">
                        <option value="official" ' . ($booking['booking_type'] == 'official' ? 'selected' : '') . '>ราชการ</option>
                        <option value="personal" ' . ($booking['booking_type'] == 'personal' ? 'selected' : '') . '>ส่วนตัว</option>
                        <option value="training" ' . ($booking['booking_type'] == 'training' ? 'selected' : '') . '>ฝึกอบรม</option>
                        <option value="other" ' . ($booking['booking_type'] == 'other' ? 'selected' : '') . '>อื่นๆ</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="edit_number_of_guests" class="form-label">จำนวนผู้พัก</label>
                    <input type="number" class="form-control" id="edit_number_of_guests" name="number_of_guests" min="1" value="' . $booking['number_of_guests'] . '">
                </div>
                <div class="col-md-6">
                    <label for="edit_purpose" class="form-label">วัตถุประสงค์</label>
                    <input type="text" class="form-control" id="edit_purpose" name="purpose" value="' . htmlspecialchars($booking['purpose']) . '" placeholder="เช่น ประชุม, ฝึกอบรม, เดินทางราชการ">
                </div>
            </div>
            
            <!-- Room Selection Section -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label">ห้องพักที่เลือก</label>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="showEditRoomSelectionModal()">
                        <i class="fas fa-edit me-1"></i>แก้ไขห้องพัก
                    </button>
                </div>
                <div id="editSelectedRoomsContainer">';
        
        if (count($bookedRooms) > 0) {
            foreach ($bookedRooms as $room) {
                echo '
                <div class="room-selection-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>' . htmlspecialchars($room['room_number']) . '</strong> - ' . htmlspecialchars($room['building_name']) . '
                            ' . ($room['room_name'] ? '<br><small class="text-muted">' . htmlspecialchars($room['room_name']) . '</small>' : '') . '
                            <br><small>ราคาต่อคืน: ฿' . number_format($room['price_per_night'], 2) . '</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEditRoom(' . $room['room_id'] . ')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>';
            }
        } else {
            echo '<p class="text-muted">ยังไม่ได้เลือกห้องพัก</p>';
        }
        
        echo '
                </div>
            </div>
            
            <div class="mb-3">
                <label for="edit_special_request" class="form-label">คำขอพิเศษ</label>
                <textarea class="form-control" id="edit_special_request" name="special_request" rows="3" placeholder="เช่น ต้องการเตียงเสริม, อาหารพิเศษ">' . htmlspecialchars($booking['special_request']) . '</textarea>
            </div>
        </form>';
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action ไม่ถูกต้อง']);
}
?>