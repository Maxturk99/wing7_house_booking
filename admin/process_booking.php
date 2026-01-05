<?php
// process_booking.php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit();
}

require_once '../config/database.php';
$db = getDatabaseConnection();

$action = $_POST['action'] ?? '';

function sendResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

try {
    switch ($action) {
        case 'update_booking':
            handleUpdateBooking($db);
            break;
            
        case 'update_booking_rooms':
            handleUpdateBookingRooms($db);
            break;
            
        case 'approve_booking':
            handleApproveBooking($db);
            break;
            
        case 'check_in_booking':
            handleCheckInBooking($db);
            break;
            
        case 'check_out_booking':
            handleCheckOutBooking($db);
            break;
            
        default:
            sendResponse(false, 'Action ไม่ถูกต้อง');
    }
} catch (Exception $e) {
    sendResponse(false, $e->getMessage());
}

function handleUpdateBooking($db) {
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['user_role'];
    
    $required = ['id', 'guest_name', 'guest_phone', 'check_in_date', 'check_out_date'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("กรุณากรอกข้อมูลให้ครบ: $field");
        }
    }
    
    $bookingId = $_POST['id'];
    
    // ตรวจสอบการจอง
    $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        throw new Exception("ไม่พบข้อมูลการจอง");
    }
    
    // ตรวจสอบสิทธิ์
    if ($userRole === 'staff' && ($booking['created_by'] != $userId || $booking['status'] !== 'pending')) {
        throw new Exception("ไม่มีสิทธิ์แก้ไขการจองนี้");
    }
    
    // ตรวจสอบวันที่
    $checkInDate = new DateTime($_POST['check_in_date']);
    $checkOutDate = new DateTime($_POST['check_out_date']);
    
    if ($checkInDate >= $checkOutDate) {
        throw new Exception("วันที่เช็คเอาต์ต้องมากกว่าวันที่เช็คอิน");
    }
    
    // สำหรับ admin สามารถเปลี่ยนสถานะได้
    $status = $booking['status'];
    $notes = '';
    
    if ($userRole === 'admin' && isset($_POST['status'])) {
        $status = $_POST['status'];
        $notes = $_POST['notes'] ?? '';
        
        if ($status === 'approved') {
            // ตรวจสอบว่ามีห้องพักหรือไม่
            $roomStmt = $db->prepare("SELECT COUNT(*) FROM booking_rooms WHERE booking_id = ?");
            $roomStmt->execute([$bookingId]);
            $roomCount = $roomStmt->fetchColumn();
            
            if ($roomCount == 0) {
                throw new Exception("ไม่สามารถอนุมัติได้ เนื่องจากยังไม่มีห้องพักที่จอง");
            }
            
            // อัพเดทสถานะห้องพัก
            $updateRoomStmt = $db->prepare("UPDATE rooms r 
                                           JOIN booking_rooms br ON r.id = br.room_id 
                                           SET r.status = 'occupied' 
                                           WHERE br.booking_id = ?");
            $updateRoomStmt->execute([$bookingId]);
        }
        
        if ($status !== $booking['status'] && $status !== 'pending') {
            $notesStmt = $db->prepare("INSERT INTO booking_notes (booking_id, note, created_by, created_at) 
                                      VALUES (?, ?, ?, NOW())");
            $notesStmt->execute([$bookingId, "เปลี่ยนสถานะจาก {$booking['status']} เป็น $status: $notes", $userId]);
        }
    }
    
    // อัพเดทข้อมูลการจอง
    $updateStmt = $db->prepare("UPDATE bookings SET 
        guest_name = ?,
        guest_phone = ?,
        guest_email = ?,
        guest_department = ?,
        check_in_date = ?,
        check_out_date = ?,
        number_of_guests = ?,
        purpose = ?,
        booking_type = ?,
        special_request = ?,
        status = ?,
        updated_at = NOW()
        WHERE id = ?");
    
    $success = $updateStmt->execute([
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
        $status,
        $bookingId
    ]);
    
    if ($success) {
        sendResponse(true, 'อัพเดทข้อมูลการจองสำเร็จ');
    } else {
        throw new Exception("ไม่สามารถอัพเดทข้อมูลการจองได้");
    }
}

function handleUpdateBookingRooms($db) {
    $bookingId = $_POST['booking_id'] ?? 0;
    $roomIds = isset($_POST['room_ids']) ? explode(',', $_POST['room_ids']) : [];
    
    if (!$bookingId) {
        throw new Exception("ไม่ระบุ ID การจอง");
    }
    
    // ตรวจสอบการจอง
    $stmt = $db->prepare("SELECT status FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        throw new Exception("ไม่พบข้อมูลการจอง");
    }
    
    if ($booking['status'] !== 'pending') {
        throw new Exception("ไม่สามารถเปลี่ยนแปลงห้องพักได้ เนื่องจากสถานะไม่ใช่รออนุมัติ");
    }
    
    // ลบห้องเดิม
    $deleteStmt = $db->prepare("DELETE FROM booking_rooms WHERE booking_id = ?");
    $deleteStmt->execute([$bookingId]);
    
    // เพิ่มห้องใหม่
    if (!empty($roomIds)) {
        $insertStmt = $db->prepare("INSERT INTO booking_rooms (booking_id, room_id, created_at) VALUES (?, ?, NOW())");
        
        foreach ($roomIds as $roomId) {
            // ตรวจสอบว่าห้องว่างในช่วงเวลานี้หรือไม่
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM rooms r 
                                      WHERE r.id = ? 
                                      AND r.status IN ('available', 'cleaning')
                                      AND r.id NOT IN (
                                          SELECT br.room_id 
                                          FROM booking_rooms br 
                                          JOIN bookings b ON br.booking_id = b.id 
                                          WHERE b.status IN ('approved', 'checked_in')
                                          AND b.check_out_date > (SELECT check_in_date FROM bookings WHERE id = ?)
                                          AND b.check_in_date < (SELECT check_out_date FROM bookings WHERE id = ?)
                                      )");
            $checkStmt->execute([$roomId, $bookingId, $bookingId]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $insertStmt->execute([$bookingId, $roomId]);
            }
        }
    }
    
    sendResponse(true, 'อัพเดทห้องพักสำเร็จ');
}

function handleApproveBooking($db) {
    if ($_SESSION['user_role'] !== 'admin') {
        throw new Exception("เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถอนุมัติได้");
    }
    
    $bookingId = $_POST['id'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    
    if (!$bookingId) {
        throw new Exception("ไม่ระบุ ID การจอง");
    }
    
    $db->beginTransaction();
    
    try {
        // อัพเดทสถานะการจอง
        $stmt = $db->prepare("UPDATE bookings SET 
            status = 'approved',
            approved_by = ?,
            approved_at = NOW(),
            updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $bookingId]);
        
        // อัพเดทสถานะห้องพัก
        $roomStmt = $db->prepare("UPDATE rooms r 
                                 JOIN booking_rooms br ON r.id = br.room_id 
                                 SET r.status = 'occupied' 
                                 WHERE br.booking_id = ?");
        $roomStmt->execute([$bookingId]);
        
        // บันทึกหมายเหตุ
        if ($notes) {
            $noteStmt = $db->prepare("INSERT INTO booking_notes (booking_id, note, created_by, created_at) 
                                     VALUES (?, ?, ?, NOW())");
            $noteStmt->execute([$bookingId, "อนุมัติการจอง: $notes", $_SESSION['user_id']]);
        }
        
        $db->commit();
        sendResponse(true, 'อนุมัติการจองสำเร็จ');
        
    } catch (Exception $e) {
        $db->rollBack();
        throw new Exception("ไม่สามารถอนุมัติการจองได้: " . $e->getMessage());
    }
}

function handleCheckInBooking($db) {
    $bookingId = $_POST['id'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    
    if (!$bookingId) {
        throw new Exception("ไม่ระบุ ID การจอง");
    }
    
    // ตรวจสอบว่าการจองอยู่ในสถานะ approved
    $checkStmt = $db->prepare("SELECT status FROM bookings WHERE id = ?");
    $checkStmt->execute([$bookingId]);
    $booking = $checkStmt->fetch();
    
    if (!$booking) {
        throw new Exception("ไม่พบข้อมูลการจอง");
    }
    
    if ($booking['status'] !== 'approved') {
        throw new Exception("ไม่สามารถเช็คอินได้ เนื่องจากสถานะไม่ใช่ 'อนุมัติแล้ว'");
    }
    
    $stmt = $db->prepare("UPDATE bookings SET 
        status = 'checked_in',
        checked_in_at = NOW(),
        checked_in_by = ?,
        updated_at = NOW()
        WHERE id = ?");
    
    $success = $stmt->execute([$_SESSION['user_id'], $bookingId]);
    
    if ($success) {
        // บันทึกหมายเหตุ
        if ($notes) {
            $noteStmt = $db->prepare("INSERT INTO booking_notes (booking_id, note, created_by, created_at) 
                                     VALUES (?, ?, ?, NOW())");
            $noteStmt->execute([$bookingId, "เช็คอิน: $notes", $_SESSION['user_id']]);
        }
        
        sendResponse(true, 'เช็คอินสำเร็จ');
    } else {
        throw new Exception("ไม่สามารถเช็คอินได้");
    }
}

function handleCheckOutBooking($db) {
    $bookingId = $_POST['id'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    
    if (!$bookingId) {
        throw new Exception("ไม่ระบุ ID การจอง");
    }
    
    // ตรวจสอบว่าการจองอยู่ในสถานะ checked_in
    $checkStmt = $db->prepare("SELECT status FROM bookings WHERE id = ?");
    $checkStmt->execute([$bookingId]);
    $booking = $checkStmt->fetch();
    
    if (!$booking) {
        throw new Exception("ไม่พบข้อมูลการจอง");
    }
    
    if ($booking['status'] !== 'checked_in') {
        throw new Exception("ไม่สามารถเช็คเอาต์ได้ เนื่องจากสถานะไม่ใช่ 'เช็คอินแล้ว'");
    }
    
    $db->beginTransaction();
    
    try {
        // อัพเดทสถานะการจอง
        $stmt = $db->prepare("UPDATE bookings SET 
            status = 'checked_out',
            checked_out_at = NOW(),
            checked_out_by = ?,
            updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $bookingId]);
        
        // เปลี่ยนสถานะห้องพักกลับเป็น cleaning
        $roomStmt = $db->prepare("UPDATE rooms r 
                                 JOIN booking_rooms br ON r.id = br.room_id 
                                 SET r.status = 'cleaning' 
                                 WHERE br.booking_id = ?");
        $roomStmt->execute([$bookingId]);
        
        // บันทึกหมายเหตุ
        if ($notes) {
            $noteStmt = $db->prepare("INSERT INTO booking_notes (booking_id, note, created_by, created_at) 
                                     VALUES (?, ?, ?, NOW())");
            $noteStmt->execute([$bookingId, "เช็คเอาต์: $notes", $_SESSION['user_id']]);
        }
        
        $db->commit();
        sendResponse(true, 'เช็คเอาต์สำเร็จ');
        
    } catch (Exception $e) {
        $db->rollBack();
        throw new Exception("ไม่สามารถเช็คเอาต์ได้: " . $e->getMessage());
    }
}
?>