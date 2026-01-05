<?php
/**
 * Process Building Operations
 * ประมวลผลการจัดการอาคารและห้องพัก
 */

session_start();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์ใช้งาน']);
    exit();
}

// เชื่อมต่อฐานข้อมูล
require_once '../config/database.php';
$db = getDatabaseConnection();

$action = $_POST['action'] ?? '';

// ตั้งค่า header สำหรับ JSON response
header('Content-Type: application/json; charset=utf-8');

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
                trim($_POST['building_code']),
                trim($_POST['building_name']),
                $_POST['building_type'],
                trim($_POST['location'] ?? ''),
                floatval($_POST['price_per_night']),
                intval($_POST['max_occupancy'] ?? 2),
                trim($_POST['description'] ?? ''),
                trim($_POST['amenities'] ?? ''),
                $_POST['status'] ?? 'active'
            ]);

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'เพิ่มอาคารสำเร็จ']);
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
            $stmt->execute([trim($_POST['building_code']), $_POST['building_id']]);
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
                trim($_POST['building_code']),
                trim($_POST['building_name']),
                $_POST['building_type'],
                trim($_POST['location'] ?? ''),
                floatval($_POST['price_per_night']),
                intval($_POST['max_occupancy'] ?? 2),
                trim($_POST['description'] ?? ''),
                trim($_POST['amenities'] ?? ''),
                $_POST['status'] ?? 'active',
                $_POST['building_id']
            ]);

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'อัปเดตอาคารสำเร็จ']);
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
            $stmt->execute([$_POST['building_id'], trim($_POST['room_number'])]);
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
                trim($_POST['room_number']),
                trim($_POST['room_name'] ?? ''),
                $_POST['room_type'] ?? 'standard',
                intval($_POST['floor'] ?? 1),
                intval($_POST['max_capacity'] ?? 2),
                floatval($_POST['price_per_night'] ?? 0),
                trim($_POST['amenities'] ?? ''),
                $_POST['status'] ?? 'available'
            ]);

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'เพิ่มห้องพักสำเร็จ']);
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
            $stmt->execute([$_POST['building_id'], trim($_POST['room_number']), $_POST['room_id']]);
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
                trim($_POST['room_number']),
                trim($_POST['room_name'] ?? ''),
                $_POST['room_type'] ?? 'standard',
                intval($_POST['floor'] ?? 1),
                intval($_POST['max_capacity'] ?? 2),
                floatval($_POST['price_per_night'] ?? 0),
                trim($_POST['amenities'] ?? ''),
                $_POST['status'] ?? 'available',
                $_POST['room_id']
            ]);

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'อัปเดตห้องพักสำเร็จ']);
            } else {
                throw new Exception("ไม่สามารถอัปเดตห้องพักได้");
            }
            break;

        case 'delete_building':
            if ($_SESSION['user_role'] !== 'admin') {
                throw new Exception("เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถลบอาคารได้");
            }

            if (empty($_POST['id'])) {
                throw new Exception("ไม่ระบุ ID อาคาร");
            }

            // ตรวจสอบว่ามีห้องพักที่กำลังใช้งานอยู่หรือไม่
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM rooms WHERE building_id = ? AND status = 'occupied'");
            $stmt->execute([$_POST['id']]);
            $result = $stmt->fetch();
            if ($result['count'] > 0) {
                throw new Exception("ไม่สามารถลบอาคารได้ เนื่องจากมีห้องพักที่กำลังใช้งานอยู่");
            }

            // ลบห้องพักทั้งหมดในอาคารก่อน
            $stmt = $db->prepare("DELETE FROM rooms WHERE building_id = ?");
            $stmt->execute([$_POST['id']]);

            // ลบอาคาร
            $stmt = $db->prepare("DELETE FROM buildings WHERE id = ?");
            $success = $stmt->execute([$_POST['id']]);

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'ลบอาคารสำเร็จ']);
            } else {
                throw new Exception("ไม่สามารถลบอาคารได้");
            }
            break;

        case 'delete_room':
            if ($_SESSION['user_role'] !== 'admin') {
                throw new Exception("เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถลบห้องพักได้");
            }

            if (empty($_POST['id'])) {
                throw new Exception("ไม่ระบุ ID ห้องพัก");
            }

            // ตรวจสอบว่าห้องพักกำลังใช้งานอยู่หรือไม่
            $stmt = $db->prepare("SELECT status FROM rooms WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $room = $stmt->fetch();
            
            if ($room && $room['status'] === 'occupied') {
                throw new Exception("ไม่สามารถลบห้องพักได้ เนื่องจากกำลังใช้งานอยู่");
            }

            $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
            $success = $stmt->execute([$_POST['id']]);

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'ลบห้องพักสำเร็จ']);
            } else {
                throw new Exception("ไม่สามารถลบห้องพักได้");
            }
            break;

        default:
            throw new Exception("ไม่ระบุการดำเนินการ");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>