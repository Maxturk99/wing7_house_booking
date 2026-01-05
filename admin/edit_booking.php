<?php
// edit_booking.php
session_start();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit();
}

require_once '../config/database.php';
$db = getDatabaseConnection();

$bookingId = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? '';

if (!is_numeric($bookingId) || $bookingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID การจองไม่ถูกต้อง']);
    exit();
}

// ดึงข้อมูลการจอง
$stmt = $db->prepare("SELECT b.*, u.full_name as creator_name, a.full_name as approver_name 
                      FROM bookings b 
                      LEFT JOIN users u ON b.created_by = u.id 
                      LEFT JOIN users a ON b.approved_by = a.id 
                      WHERE b.id = ?");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลการจอง']);
    exit();
}

// ตรวจสอบสิทธิ์ในการแก้ไข
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Staff สามารถแก้ไขได้เฉพาะการจองที่ตัวเองสร้างและยังอยู่ในสถานะ pending
if ($userRole === 'staff' && ($booking['created_by'] != $userId || $booking['status'] !== 'pending')) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์แก้ไขการจองนี้']);
    exit();
}

// ดึงข้อมูลห้องที่จองไว้
$roomStmt = $db->prepare("SELECT br.*, r.room_number, r.room_name, r.price_per_night, b.building_name, b.building_code 
                          FROM booking_rooms br 
                          JOIN rooms r ON br.room_id = r.id 
                          JOIN buildings b ON r.building_id = b.id 
                          WHERE br.booking_id = ?");
$roomStmt->execute([$bookingId]);
$bookedRooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงห้องทั้งหมดที่ว่างในช่วงเวลานี้
$availableRooms = [];
if ($booking['status'] === 'pending') {
    $availableStmt = $db->prepare("SELECT r.*, b.building_name, b.building_code 
                                   FROM rooms r 
                                   JOIN buildings b ON r.building_id = b.id 
                                   WHERE r.status IN ('available', 'cleaning') 
                                   AND r.id NOT IN (
                                       SELECT br.room_id 
                                       FROM booking_rooms br 
                                       JOIN bookings b ON br.booking_id = b.id 
                                       WHERE b.status IN ('approved', 'checked_in')
                                       AND b.check_out_date > ?
                                       AND b.check_in_date < ?
                                       AND b.id != ?
                                   )");
    $availableStmt->execute([$booking['check_in_date'], $booking['check_out_date'], $bookingId]);
    $availableRooms = $availableStmt->fetchAll(PDO::FETCH_ASSOC);
}

switch ($action) {
    case 'get_form':
        // ส่งฟอร์มแก้ไข
        echo generateEditForm($booking, $bookedRooms, $availableRooms);
        break;
        
    case 'get_rooms':
        // ส่งข้อมูลห้องทั้งหมด
        echo json_encode([
            'success' => true,
            'bookedRooms' => $bookedRooms,
            'availableRooms' => $availableRooms
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action ไม่ถูกต้อง']);
}

function generateEditForm($booking, $bookedRooms, $availableRooms) {
    ob_start();
    ?>
    <input type="hidden" name="action" value="update_booking">
    <input type="hidden" name="id" value="<?php echo $booking['id']; ?>">
    
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="edit_guest_name" class="form-label">ชื่อผู้พัก *</label>
            <input type="text" class="form-control" id="edit_guest_name" name="guest_name" 
                   value="<?php echo htmlspecialchars($booking['guest_name']); ?>" required>
        </div>
        <div class="col-md-6">
            <label for="edit_guest_phone" class="form-label">เบอร์ติดต่อ *</label>
            <input type="tel" class="form-control" id="edit_guest_phone" name="guest_phone" 
                   value="<?php echo htmlspecialchars($booking['guest_phone']); ?>" pattern="[0-9]{10}" required>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="edit_guest_email" class="form-label">อีเมล</label>
            <input type="email" class="form-control" id="edit_guest_email" name="guest_email" 
                   value="<?php echo htmlspecialchars($booking['guest_email']); ?>">
        </div>
        <div class="col-md-6">
            <label for="edit_guest_department" class="form-label">สังกัด/หน่วยงาน</label>
            <input type="text" class="form-control" id="edit_guest_department" name="guest_department" 
                   value="<?php echo htmlspecialchars($booking['guest_department']); ?>">
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-4">
            <label for="edit_check_in_date" class="form-label">วันที่เช็คอิน *</label>
            <div class="date-inputs">
                <input type="date" class="form-control" id="edit_check_in_date" name="check_in_date" 
                       value="<?php echo htmlspecialchars($booking['check_in_date']); ?>" required>
                <i class="fas fa-calendar-alt"></i>
            </div>
        </div>
        <div class="col-md-4">
            <label for="edit_check_out_date" class="form-label">วันที่เช็คเอาต์ *</label>
            <div class="date-inputs">
                <input type="date" class="form-control" id="edit_check_out_date" name="check_out_date" 
                       value="<?php echo htmlspecialchars($booking['check_out_date']); ?>" required>
                <i class="fas fa-calendar-alt"></i>
            </div>
        </div>
        <div class="col-md-4">
            <label for="edit_booking_type" class="form-label">ประเภทการจอง</label>
            <select class="form-select" id="edit_booking_type" name="booking_type">
                <option value="official" <?php echo $booking['booking_type'] == 'official' ? 'selected' : ''; ?>>ราชการ</option>
                <option value="personal" <?php echo $booking['booking_type'] == 'personal' ? 'selected' : ''; ?>>ส่วนตัว</option>
                <option value="training" <?php echo $booking['booking_type'] == 'training' ? 'selected' : ''; ?>>ฝึกอบรม</option>
                <option value="other" <?php echo $booking['booking_type'] == 'other' ? 'selected' : ''; ?>>อื่นๆ</option>
            </select>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-4">
            <label for="edit_number_of_guests" class="form-label">จำนวนผู้พัก</label>
            <input type="number" class="form-control" id="edit_number_of_guests" name="number_of_guests" 
                   value="<?php echo htmlspecialchars($booking['number_of_guests'] ?: 1); ?>" min="1">
        </div>
        <div class="col-md-8">
            <label for="edit_purpose" class="form-label">วัตถุประสงค์</label>
            <input type="text" class="form-control" id="edit_purpose" name="purpose" 
                   value="<?php echo htmlspecialchars($booking['purpose']); ?>" placeholder="เช่น ประชุม, ฝึกอบรม, เดินทางราชการ">
        </div>
    </div>
    
    <!-- Room Selection Section -->
    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label">ห้องพักที่จอง</label>
            <?php if ($booking['status'] === 'pending'): ?>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="showEditRoomSelection(<?php echo $booking['id']; ?>)">
                <i class="fas fa-edit me-1"></i>จัดการห้องพัก
            </button>
            <?php endif; ?>
        </div>
        <div id="edit_selectedRoomsContainer">
            <?php if (empty($bookedRooms)): ?>
            <p class="text-muted">ยังไม่มีห้องพักที่จอง</p>
            <?php else: ?>
            <div class="selected-rooms-list">
                <?php foreach ($bookedRooms as $room): ?>
                <div class="room-selection-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($room['room_number']); ?></strong> - <?php echo htmlspecialchars($room['building_name']); ?>
                            <?php if ($room['room_name']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($room['room_name']); ?></small>
                            <?php endif; ?>
                            <br><small>ราคาต่อคืน: ฿<?php echo number_format($room['price_per_night'], 2); ?></small>
                        </div>
                        <?php if ($booking['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                onclick="removeBookedRoom(<?php echo $booking['id']; ?>, <?php echo $room['id']; ?>)">
                            <i class="fas fa-times"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <input type="hidden" id="edit_selectedRoomIds" name="selected_room_ids" 
               value="<?php echo implode(',', array_column($bookedRooms, 'room_id')); ?>">
    </div>
    
    <div class="mb-3">
        <label for="edit_special_request" class="form-label">คำขอพิเศษ</label>
        <textarea class="form-control" id="edit_special_request" name="special_request" rows="3" 
                  placeholder="เช่น ต้องการเตียงเสริม, อาหารพิเศษ"><?php echo htmlspecialchars($booking['special_request']); ?></textarea>
    </div>
    
    <?php if ($booking['status'] === 'pending' && $_SESSION['user_role'] === 'admin'): ?>
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="edit_status" class="form-label">สถานะ</label>
            <select class="form-select" id="edit_status" name="status">
                <option value="pending" <?php echo $booking['status'] == 'pending' ? 'selected' : ''; ?>>รออนุมัติ</option>
                <option value="approved" <?php echo $booking['status'] == 'approved' ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                <option value="rejected" <?php echo $booking['status'] == 'rejected' ? 'selected' : ''; ?>>ไม่อนุมัติ</option>
            </select>
        </div>
        <div class="col-md-6">
            <label for="edit_notes" class="form-label">หมายเหตุ (สำหรับการอนุมัติ/ไม่อนุมัติ)</label>
            <textarea class="form-control" id="edit_notes" name="notes" rows="2" 
                      placeholder="ระบุเหตุผลหากไม่อนุมัติ"></textarea>
        </div>
    </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
?>