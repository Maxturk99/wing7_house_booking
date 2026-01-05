<?php
/**
 * Booking Model Class
 * คลาสสำหรับจัดการข้อมูลการจอง
 */

class BookingModel {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Generate booking code
    private function generateBookingCode() {
        $prefix = 'BK';
        $year = date('Y');
        $month = date('m');
        
        $sql = "SELECT COUNT(*) as count FROM bookings WHERE YEAR(created_at) = :year AND MONTH(created_at) = :month";
        $result = $this->db->fetch($sql, [':year' => $year, ':month' => $month]);
        
        $sequence = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
        return $prefix . $year . $month . $sequence;
    }
    
    // Create new booking
    public function createBooking($data) {
        $bookingCode = $this->generateBookingCode();
        
        $sql = "INSERT INTO bookings (
                booking_code, user_id, guest_name, guest_phone, guest_email, 
                guest_department, guest_rank, booking_type, purpose, 
                check_in_date, check_out_date, number_of_rooms, number_of_guests,
                total_amount, discount_amount, net_amount, special_request,
                status, created_by
            ) VALUES (
                :booking_code, :user_id, :guest_name, :guest_phone, :guest_email,
                :guest_department, :guest_rank, :booking_type, :purpose,
                :check_in_date, :check_out_date, :number_of_rooms, :number_of_guests,
                :total_amount, :discount_amount, :net_amount, :special_request,
                :status, :created_by
            )";
        
        $params = [
            ':booking_code' => $bookingCode,
            ':user_id' => $data['user_id'] ?? null,
            ':guest_name' => $data['guest_name'],
            ':guest_phone' => $data['guest_phone'],
            ':guest_email' => $data['guest_email'] ?? null,
            ':guest_department' => $data['guest_department'] ?? null,
            ':guest_rank' => $data['guest_rank'] ?? null,
            ':booking_type' => $data['booking_type'] ?? 'official',
            ':purpose' => $data['purpose'] ?? null,
            ':check_in_date' => $data['check_in_date'],
            ':check_out_date' => $data['check_out_date'],
            ':number_of_rooms' => $data['number_of_rooms'] ?? 1,
            ':number_of_guests' => $data['number_of_guests'] ?? 1,
            ':total_amount' => $data['total_amount'] ?? 0,
            ':discount_amount' => $data['discount_amount'] ?? 0,
            ':net_amount' => $data['net_amount'] ?? 0,
            ':special_request' => $data['special_request'] ?? null,
            ':status' => $data['status'] ?? 'pending',
            ':created_by' => $data['created_by'] ?? null
        ];
        
        if ($this->db->query($sql, $params)) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    // Add room to booking
    public function addRoomToBooking($bookingId, $roomId, $buildingId, $price, $checkIn, $checkOut) {
        $sql = "INSERT INTO booking_rooms (booking_id, room_id, building_id, price_per_night, check_in_date, check_out_date) 
                VALUES (:booking_id, :room_id, :building_id, :price, :check_in, :check_out)";
        
        $params = [
            ':booking_id' => $bookingId,
            ':room_id' => $roomId,
            ':building_id' => $buildingId,
            ':price' => $price,
            ':check_in' => $checkIn,
            ':check_out' => $checkOut
        ];
        
        return $this->db->query($sql, $params) !== false;
    }
    
    // Get booking by ID
    public function getBookingById($id) {
        $sql = "SELECT b.*, u.full_name as creator_name, a.full_name as approver_name 
                FROM bookings b 
                LEFT JOIN users u ON b.created_by = u.id 
                LEFT JOIN users a ON b.approved_by = a.id 
                WHERE b.id = :id";
        return $this->db->fetch($sql, [':id' => $id]);
    }
    
    // Get booking by code
    public function getBookingByCode($code) {
        $sql = "SELECT b.*, u.full_name as creator_name, a.full_name as approver_name 
                FROM bookings b 
                LEFT JOIN users u ON b.created_by = u.id 
                LEFT JOIN users a ON b.approved_by = a.id 
                WHERE b.booking_code = :code";
        return $this->db->fetch($sql, [':code' => $code]);
    }
    
    // Get all bookings with filters
    public function getAllBookings($filters = []) {
        $sql = "SELECT b.*, u.full_name as creator_name, a.full_name as approver_name 
                FROM bookings b 
                LEFT JOIN users u ON b.created_by = u.id 
                LEFT JOIN users a ON b.approved_by = a.id 
                WHERE 1=1";
        $params = [];
        
        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND b.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['booking_type'])) {
            $sql .= " AND b.booking_type = :booking_type";
            $params[':booking_type'] = $filters['booking_type'];
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
        
        // For approvers, show only pending bookings
        if (!empty($filters['for_approver']) && $filters['for_approver'] === true) {
            $sql .= " AND b.status = 'pending'";
        }
        
        $sql .= " ORDER BY b.created_at DESC";
        
        // Pagination
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = (int)$filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET :offset";
                $params[':offset'] = (int)$filters['offset'];
            }
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // Get bookings by user
    public function getBookingsByUser($userId) {
        $sql = "SELECT * FROM bookings 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, [':user_id' => $userId]);
    }
    
    // Update booking status
    public function updateBookingStatus($id, $status, $approverId = null, $reason = null) {
        $sql = "UPDATE bookings SET 
                status = :status,
                approved_by = :approved_by,
                approved_at = :approved_at,
                rejection_reason = :reason,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        
        $params = [
            ':id' => $id,
            ':status' => $status,
            ':approved_by' => $approverId,
            ':approved_at' => $status === 'approved' || $status === 'rejected' ? date('Y-m-d H:i:s') : null,
            ':reason' => $reason
        ];
        
        return $this->db->query($sql, $params) !== false;
    }
    
    // Update booking
    public function updateBooking($id, $data) {
        $sql = "UPDATE bookings SET 
                guest_name = :guest_name,
                guest_phone = :guest_phone,
                guest_email = :guest_email,
                guest_department = :guest_department,
                guest_rank = :guest_rank,
                booking_type = :booking_type,
                purpose = :purpose,
                check_in_date = :check_in_date,
                check_out_date = :check_out_date,
                number_of_rooms = :number_of_rooms,
                number_of_guests = :number_of_guests,
                total_amount = :total_amount,
                discount_amount = :discount_amount,
                net_amount = :net_amount,
                special_request = :special_request,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        
        $params = [
            ':id' => $id,
            ':guest_name' => $data['guest_name'],
            ':guest_phone' => $data['guest_phone'],
            ':guest_email' => $data['guest_email'],
            ':guest_department' => $data['guest_department'],
            ':guest_rank' => $data['guest_rank'],
            ':booking_type' => $data['booking_type'],
            ':purpose' => $data['purpose'],
            ':check_in_date' => $data['check_in_date'],
            ':check_out_date' => $data['check_out_date'],
            ':number_of_rooms' => $data['number_of_rooms'],
            ':number_of_guests' => $data['number_of_guests'],
            ':total_amount' => $data['total_amount'],
            ':discount_amount' => $data['discount_amount'],
            ':net_amount' => $data['net_amount'],
            ':special_request' => $data['special_request']
        ];
        
        return $this->db->query($sql, $params) !== false;
    }
    
    // Delete booking
    public function deleteBooking($id) {
        $sql = "DELETE FROM bookings WHERE id = :id AND status = 'pending'";
        return $this->db->query($sql, [':id' => $id]) !== false;
    }
    
    // Get booking rooms
    public function getBookingRooms($bookingId) {
        $sql = "SELECT br.*, r.room_number, r.room_name, b.building_name
                FROM booking_rooms br
                JOIN rooms r ON br.room_id = r.id
                JOIN buildings b ON br.building_id = b.id
                WHERE br.booking_id = :booking_id";
        return $this->db->fetchAll($sql, [':booking_id' => $bookingId]);
    }
    
    // Check room availability
    public function checkRoomAvailability($roomId, $checkIn, $checkOut, $excludeBookingId = null) {
        $sql = "SELECT COUNT(*) as count FROM booking_rooms br
                JOIN bookings b ON br.booking_id = b.id
                WHERE br.room_id = :room_id
                AND b.status IN ('pending', 'approved', 'checked_in')
                AND NOT (br.check_out_date <= :check_in OR br.check_in_date >= :check_out)";
        
        $params = [
            ':room_id' => $roomId,
            ':check_in' => $checkIn,
            ':check_out' => $checkOut
        ];
        
        if ($excludeBookingId) {
            $sql .= " AND br.booking_id != :exclude_id";
            $params[':exclude_id'] = $excludeBookingId;
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result['count'] == 0;
    }
    
    // Get statistics
    public function getBookingStats($period = 'month') {
        $stats = [];
        
        // Total bookings
        $sql = "SELECT COUNT(*) as total FROM bookings";
        $result = $this->db->fetch($sql);
        $stats['total_bookings'] = $result['total'];
        
        // Pending bookings
        $sql = "SELECT COUNT(*) as pending FROM bookings WHERE status = 'pending'";
        $result = $this->db->fetch($sql);
        $stats['pending_bookings'] = $result['pending'];
        
        // Approved bookings
        $sql = "SELECT COUNT(*) as approved FROM bookings WHERE status = 'approved'";
        $result = $this->db->fetch($sql);
        $stats['approved_bookings'] = $result['approved'];
        
        // Revenue this month
        $sql = "SELECT SUM(net_amount) as revenue FROM bookings 
                WHERE status = 'approved' 
                AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                AND YEAR(created_at) = YEAR(CURRENT_DATE())";
        $result = $this->db->fetch($sql);
        $stats['monthly_revenue'] = $result['revenue'] ?? 0;
        
        // Monthly chart data
        $sql = "SELECT MONTH(created_at) as month, COUNT(*) as count, SUM(net_amount) as revenue
                FROM bookings 
                WHERE YEAR(created_at) = YEAR(CURRENT_DATE()) 
                AND status = 'approved'
                GROUP BY MONTH(created_at)
                ORDER BY month";
        $stats['monthly_data'] = $this->db->fetchAll($sql);
        
        return $stats;
    }
}
?>