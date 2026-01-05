<?php
/**
 * Booking Controller
 * ควบคุมการจัดการการจอง
 */

class BookingController {
    private $bookingModel;
    private $buildingModel;
    
    public function __construct() {
        $this->bookingModel = new BookingModel();
        $this->buildingModel = new BuildingModel();
    }
    
    // Create new booking
    public function createBooking($data, $userId = null) {
        // Validate required fields
        $required = ['guest_name', 'guest_phone', 'check_in_date', 'check_out_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "กรุณากรอก{$this->getFieldName($field)}"];
            }
        }
        
        // Validate dates
        $checkIn = strtotime($data['check_in_date']);
        $checkOut = strtotime($data['check_out_date']);
        $today = strtotime(date('Y-m-d'));
        
        if ($checkIn < $today) {
            return ['success' => false, 'message' => 'วันที่เช็คอินต้องไม่ใช้วันที่ผ่านมาแล้ว'];
        }
        
        if ($checkOut <= $checkIn) {
            return ['success' => false, 'message' => 'วันที่เช็คเอาต์ต้องมากกว่าวันที่เช็คอิน'];
        }
        
        // Check maximum booking days
        $days = ($checkOut - $checkIn) / (60 * 60 * 24);
        if ($days > AppConfig::MAX_BOOKING_DAYS) {
            return ['success' => false, 'message' => "ไม่สามารถจองเกิน " . AppConfig::MAX_BOOKING_DAYS . " วัน"];
        }
        
        // Calculate amount if not provided
        if (empty($data['total_amount'])) {
            $data['total_amount'] = $this->calculateBookingAmount($data);
            $data['net_amount'] = $data['total_amount'] - ($data['discount_amount'] ?? 0);
        }
        
        // Set created by
        $data['created_by'] = $userId;
        
        // Start transaction
        $db = Database::getInstance();
        $db->beginTransaction();
        
        try {
            // Create booking
            $bookingId = $this->bookingModel->createBooking($data);
            
            if (!$bookingId) {
                throw new Exception('ไม่สามารถสร้างการจองได้');
            }
            
            // Add rooms if specified
            if (!empty($data['rooms']) && is_array($data['rooms'])) {
                foreach ($data['rooms'] as $room) {
                    // Check room availability
                    if (!$this->bookingModel->checkRoomAvailability($room['room_id'], $data['check_in_date'], $data['check_out_date'])) {
                        throw new Exception("ห้อง {$room['room_number']} ไม่ว่างในช่วงวันที่เลือก");
                    }
                    
                    // Add room to booking
                    $this->bookingModel->addRoomToBooking(
                        $bookingId,
                        $room['room_id'],
                        $room['building_id'],
                        $room['price'],
                        $data['check_in_date'],
                        $data['check_out_date']
                    );
                }
            }
            
            // Commit transaction
            $db->commit();
            
            // Get booking details
            $booking = $this->bookingModel->getBookingById($bookingId);
            
            // Send notifications
            $this->sendBookingNotification($booking);
            
            return [
                'success' => true,
                'message' => 'สร้างการจองสำเร็จ รอการอนุมัติ',
                'booking_id' => $bookingId,
                'booking_code' => $booking['booking_code']
            ];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // Calculate booking amount
    private function calculateBookingAmount($data) {
        $amount = 0;
        
        // Calculate based on rooms
        if (!empty($data['rooms']) && is_array($data['rooms'])) {
            foreach ($data['rooms'] as $room) {
                $nights = $this->calculateNights($data['check_in_date'], $data['check_out_date']);
                $amount += $room['price'] * $nights;
            }
        } else {
            // Default calculation
            $nights = $this->calculateNights($data['check_in_date'], $data['check_out_date']);
            $amount = 1000 * $nights * ($data['number_of_rooms'] ?? 1);
        }
        
        return $amount;
    }
    
    // Calculate number of nights
    private function calculateNights($checkIn, $checkOut) {
        $checkInDate = new DateTime($checkIn);
        $checkOutDate = new DateTime($checkOut);
        $interval = $checkInDate->diff($checkOutDate);
        return $interval->days;
    }
    
    // Get field name in Thai
    private function getFieldName($field) {
        $names = [
            'guest_name' => 'ชื่อผู้พัก',
            'guest_phone' => 'เบอร์ติดต่อ',
            'check_in_date' => 'วันที่เช็คอิน',
            'check_out_date' => 'วันที่เช็คเอาต์'
        ];
        
        return $names[$field] ?? $field;
    }
    
    // Update booking status
    public function updateBookingStatus($bookingId, $status, $approverId, $reason = null) {
        $booking = $this->bookingModel->getBookingById($bookingId);
        
        if (!$booking) {
            return ['success' => false, 'message' => 'ไม่พบข้อมูลการจอง'];
        }
        
        // Validate status transition
        if (!$this->isValidStatusTransition($booking['status'], $status)) {
            return ['success' => false, 'message' => 'ไม่สามารถเปลี่ยนสถานะได้'];
        }
        
        // Update status
        if ($this->bookingModel->updateBookingStatus($bookingId, $status, $approverId, $reason)) {
            // Send notification
            $this->sendStatusNotification($booking, $status, $reason);
            
            return ['success' => true, 'message' => 'อัปเดตสถานะสำเร็จ'];
        }
        
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปเดตสถานะ'];
    }
    
    // Validate status transition
    private function isValidStatusTransition($currentStatus, $newStatus) {
        $validTransitions = [
            'pending' => ['approved', 'rejected', 'cancelled'],
            'approved' => ['checked_in', 'cancelled'],
            'checked_in' => ['checked_out'],
            'rejected' => ['pending'],
            'cancelled' => []
        ];
        
        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }
    
    // Get available rooms
    public function getAvailableRooms($checkIn, $checkOut, $buildingType = null) {
        return $this->buildingModel->getAvailableRooms($checkIn, $checkOut, $buildingType);
    }
    
    // Get booking statistics
    public function getStatistics($period = 'month') {
        return $this->bookingModel->getBookingStats($period);
    }
    
    // Send booking notification
    private function sendBookingNotification($booking) {
        // Send to approvers
        $userModel = new UserModel();
        $approvers = $userModel->getUsersByRole('approver');
        
        foreach ($approvers as $approver) {
            $this->sendNotification(
                $approver['id'],
                'มีคำขอจองใหม่',
                "มีคำขอจองใหม่จาก {$booking['guest_name']} ({$booking['booking_code']}) รอการอนุมัติ",
                'booking'
            );
            
            // Send Line notification if token exists
            if (!empty($approver['line_token'])) {
                $this->sendLineNotification(
                    $approver['line_token'],
                    "มีคำขอจองใหม่จาก {$booking['guest_name']}"
                );
            }
        }
        
        // Send to creator if exists
        if ($booking['created_by']) {
            $this->sendNotification(
                $booking['created_by'],
                'สร้างการจองสำเร็จ',
                "การจอง {$booking['booking_code']} ของคุณถูกสร้างสำเร็จ รอการอนุมัติ",
                'booking'
            );
        }
    }
    
    // Send status notification
    private function sendStatusNotification($booking, $status, $reason = null) {
        $statusText = $this->getStatusText($status);
        $message = "การจอง {$booking['booking_code']} ถูก{$statusText}";
        
        if ($status === 'rejected' && $reason) {
            $message .= "\nเหตุผล: {$reason}";
        }
        
        // Send to creator
        if ($booking['created_by']) {
            $this->sendNotification($booking['created_by'], "การจองถูก{$statusText}", $message, 'approval');
        }
        
        // Send to guest email if exists
        if ($booking['guest_email']) {
            $this->sendEmailNotification($booking['guest_email'], "การจองถูก{$statusText}", $message);
        }
    }
    
    // Get status text in Thai
    private function getStatusText($status) {
        $texts = [
            'pending' => 'รออนุมัติ',
            'approved' => 'อนุมัติ',
            'rejected' => 'ไม่อนุมัติ',
            'checked_in' => 'เช็คอิน',
            'checked_out' => 'เช็คเอาต์',
            'cancelled' => 'ยกเลิก'
        ];
        
        return $texts[$status] ?? $status;
    }
    
    // Send notification
    private function sendNotification($userId, $title, $message, $type = 'system') {
        $db = Database::getInstance();
        
        $sql = "INSERT INTO notifications (user_id, title, message, type) 
                VALUES (:user_id, :title, :message, :type)";
        
        $params = [
            ':user_id' => $userId,
            ':title' => $title,
            ':message' => $message,
            ':type' => $type
        ];
        
        return $db->query($sql, $params) !== false;
    }
    
    // Send Line notification
    private function sendLineNotification($token, $message) {
        // Implement Line Notify API call
        // This is a placeholder - implement actual Line API integration
        return true;
    }
    
    // Send email notification
    private function sendEmailNotification($email, $subject, $message) {
        // Implement email sending
        // This is a placeholder - implement actual email sending
        return true;
    }
}
?>