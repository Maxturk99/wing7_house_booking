<?php
// export_report.php
session_start();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    header('Location: ../login.php?error=กรุณาล็อกอินเพื่อเข้าถึงระบบ');
    exit();
}

require_once '../config/database.php';
$db = getDatabaseConnection();

// รับค่าพารามิเตอร์
$format = $_GET['format'] ?? 'excel';
$reportType = $_GET['report_type'] ?? 'booking_summary';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
$status = $_GET['status'] ?? '';

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

// ดึงข้อมูลตามประเภทรายงาน
switch ($reportType) {
    case 'booking_summary':
        exportBookingSummary($db, $dateFrom, $dateTo, $status, $format);
        break;
        
    case 'booking_detail':
        exportBookingDetail($db, $dateFrom, $dateTo, $status, $format);
        break;
        
    case 'room_occupancy':
        exportRoomOccupancy($db, $dateFrom, $dateTo, $format);
        break;
        
    case 'revenue_report':
        exportRevenueReport($db, $dateFrom, $dateTo, $format);
        break;
        
    default:
        echo "ไม่พบประเภทรายงานที่ระบุ";
        exit();
}

function exportBookingSummary($db, $dateFrom, $dateTo, $status, $format) {
    // เตรียม query
    $sql = "SELECT 
                DATE(b.created_at) as booking_date,
                b.status,
                COUNT(*) as total_bookings,
                SUM(b.number_of_guests) as total_guests
            FROM bookings b
            WHERE DATE(b.created_at) BETWEEN :date_from AND :date_to";
    
    $params = [
        ':date_from' => $dateFrom,
        ':date_to' => $dateTo
    ];
    
    if ($status) {
        $sql .= " AND b.status = :status";
        $params[':status'] = $status;
    }
    
    $sql .= " GROUP BY DATE(b.created_at), b.status
              ORDER BY DATE(b.created_at) DESC, b.status";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'excel') {
        exportToExcel($data, 'booking_summary', [
            'booking_date' => 'วันที่จอง',
            'status' => 'สถานะ',
            'total_bookings' => 'จำนวนการจอง',
            'total_guests' => 'จำนวนผู้พัก'
        ]);
    } else {
        exportToPDF($data, 'booking_summary', [
            'booking_date' => 'วันที่จอง',
            'status' => 'สถานะ',
            'total_bookings' => 'จำนวนการจอง',
            'total_guests' => 'จำนวนผู้พัก'
        ]);
    }
}

function exportBookingDetail($db, $dateFrom, $dateTo, $status, $format) {
    $sql = "SELECT 
                b.booking_code,
                b.guest_name,
                b.guest_phone,
                b.guest_email,
                b.guest_department,
                b.check_in_date,
                b.check_out_date,
                b.number_of_guests,
                b.purpose,
                b.booking_type,
                b.status,
                b.created_at,
                u.full_name as created_by,
                a.full_name as approved_by
            FROM bookings b
            LEFT JOIN users u ON b.created_by = u.id
            LEFT JOIN users a ON b.approved_by = a.id
            WHERE DATE(b.created_at) BETWEEN :date_from AND :date_to";
    
    $params = [
        ':date_from' => $dateFrom,
        ':date_to' => $dateTo
    ];
    
    if ($status) {
        $sql .= " AND b.status = :status";
        $params[':status'] = $status;
    }
    
    $sql .= " ORDER BY b.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // แปลงข้อมูล
    foreach ($data as &$row) {
        $row['status'] = getStatusThai($row['status']);
        $row['booking_type'] = getBookingTypeThai($row['booking_type']);
        $row['check_in_date'] = date('d/m/Y', strtotime($row['check_in_date']));
        $row['check_out_date'] = date('d/m/Y', strtotime($row['check_out_date']));
        $row['created_at'] = date('d/m/Y H:i', strtotime($row['created_at']));
    }
    
    if ($format === 'excel') {
        exportToExcel($data, 'booking_detail', [
            'booking_code' => 'รหัสการจอง',
            'guest_name' => 'ชื่อผู้พัก',
            'guest_phone' => 'เบอร์โทร',
            'guest_email' => 'อีเมล',
            'guest_department' => 'สังกัด',
            'check_in_date' => 'วันที่เช็คอิน',
            'check_out_date' => 'วันที่เช็คเอาต์',
            'number_of_guests' => 'จำนวนผู้พัก',
            'purpose' => 'วัตถุประสงค์',
            'booking_type' => 'ประเภทการจอง',
            'status' => 'สถานะ',
            'created_at' => 'วันที่สร้าง',
            'created_by' => 'สร้างโดย',
            'approved_by' => 'ผู้อนุมัติ'
        ]);
    } else {
        exportToPDF($data, 'booking_detail', [
            'booking_code' => 'รหัสการจอง',
            'guest_name' => 'ชื่อผู้พัก',
            'guest_phone' => 'เบอร์โทร',
            'guest_email' => 'อีเมล',
            'guest_department' => 'สังกัด',
            'check_in_date' => 'วันที่เช็คอิน',
            'check_out_date' => 'วันที่เช็คเอาต์',
            'number_of_guests' => 'จำนวนผู้พัก',
            'purpose' => 'วัตถุประสงค์',
            'booking_type' => 'ประเภทการจอง',
            'status' => 'สถานะ',
            'created_at' => 'วันที่สร้าง',
            'created_by' => 'สร้างโดย',
            'approved_by' => 'ผู้อนุมัติ'
        ]);
    }
}

function exportRoomOccupancy($db, $dateFrom, $dateTo, $format) {
    $sql = "SELECT 
                b.building_name,
                b.building_code,
                r.room_number,
                r.room_name,
                r.room_type,
                r.price_per_night,
                r.status,
                COUNT(br.id) as total_bookings,
                COALESCE(SUM(DATEDIFF(
                    LEAST(bk.check_out_date, :date_to),
                    GREATEST(bk.check_in_date, :date_from)
                ) + 1), 0) as occupied_days,
                DATEDIFF(:date_to, :date_from) + 1 as total_days,
                ROUND(
                    COALESCE(SUM(DATEDIFF(
                        LEAST(bk.check_out_date, :date_to),
                        GREATEST(bk.check_in_date, :date_from)
                    ) + 1), 0) / 
                    (DATEDIFF(:date_to, :date_from) + 1) * 100, 2
                ) as occupancy_rate
            FROM rooms r
            JOIN buildings b ON r.building_id = b.id
            LEFT JOIN booking_rooms br ON r.id = br.room_id
            LEFT JOIN bookings bk ON br.booking_id = bk.id 
                AND bk.status IN ('approved', 'checked_in', 'checked_out')
                AND bk.check_out_date > :date_from 
                AND bk.check_in_date < :date_to
            GROUP BY r.id
            ORDER BY b.building_name, r.room_number";
    
    $params = [
        ':date_from' => $dateFrom,
        ':date_to' => $dateTo
    ];
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'excel') {
        exportToExcel($data, 'room_occupancy', [
            'building_name' => 'ชื่ออาคาร',
            'building_code' => 'รหัสอาคาร',
            'room_number' => 'เลขห้อง',
            'room_name' => 'ชื่อห้อง',
            'room_type' => 'ประเภทห้อง',
            'price_per_night' => 'ราคาต่อคืน',
            'status' => 'สถานะ',
            'total_bookings' => 'จำนวนการจอง',
            'occupied_days' => 'วันที่มีการจอง',
            'total_days' => 'วันทั้งหมด',
            'occupancy_rate' => 'อัตราการใช้งาน (%)'
        ]);
    } else {
        exportToPDF($data, 'room_occupancy', [
            'building_name' => 'ชื่ออาคาร',
            'building_code' => 'รหัสอาคาร',
            'room_number' => 'เลขห้อง',
            'room_name' => 'ชื่อห้อง',
            'room_type' => 'ประเภทห้อง',
            'price_per_night' => 'ราคาต่อคืน',
            'status' => 'สถานะ',
            'total_bookings' => 'จำนวนการจอง',
            'occupied_days' => 'วันที่มีการจอง',
            'total_days' => 'วันทั้งหมด',
            'occupancy_rate' => 'อัตราการใช้งาน (%)'
        ]);
    }
}

function exportRevenueReport($db, $dateFrom, $dateTo, $format) {
    $sql = "SELECT 
                DATE(b.created_at) as transaction_date,
                b.booking_code,
                b.guest_name,
                r.room_number,
                r.room_name,
                r.price_per_night,
                DATEDIFF(
                    LEAST(b.check_out_date, :date_to),
                    GREATEST(b.check_in_date, :date_from)
                ) + 1 as nights,
                r.price_per_night * (
                    DATEDIFF(
                        LEAST(b.check_out_date, :date_to),
                        GREATEST(b.check_in_date, :date_from)
                    ) + 1
                ) as total_amount,
                b.status
            FROM bookings b
            JOIN booking_rooms br ON b.id = br.booking_id
            JOIN rooms r ON br.room_id = r.id
            WHERE b.status IN ('approved', 'checked_in', 'checked_out')
                AND b.check_out_date > :date_from2 
                AND b.check_in_date < :date_to2
            ORDER BY b.created_at DESC";
    
    $params = [
        ':date_from' => $dateFrom,
        ':date_to' => $dateTo,
        ':date_from2' => $dateFrom,
        ':date_to2' => $dateTo
    ];
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // คำนวณสรุป
    $summary = [
        'total_rooms' => count($data),
        'total_nights' => array_sum(array_column($data, 'nights')),
        'total_revenue' => array_sum(array_column($data, 'total_amount'))
    ];
    
    if ($format === 'excel') {
        exportToExcelWithSummary($data, $summary, 'revenue_report', [
            'transaction_date' => 'วันที่',
            'booking_code' => 'รหัสการจอง',
            'guest_name' => 'ชื่อผู้พัก',
            'room_number' => 'เลขห้อง',
            'room_name' => 'ชื่อห้อง',
            'price_per_night' => 'ราคาต่อคืน',
            'nights' => 'จำนวนคืน',
            'total_amount' => 'ยอดรวม',
            'status' => 'สถานะ'
        ]);
    } else {
        exportToPDFWithSummary($data, $summary, 'revenue_report', [
            'transaction_date' => 'วันที่',
            'booking_code' => 'รหัสการจอง',
            'guest_name' => 'ชื่อผู้พัก',
            'room_number' => 'เลขห้อง',
            'room_name' => 'ชื่อห้อง',
            'price_per_night' => 'ราคาต่อคืน',
            'nights' => 'จำนวนคืน',
            'total_amount' => 'ยอดรวม',
            'status' => 'สถานะ'
        ]);
    }
}

function exportToExcel($data, $filename, $columnMap) {
    // ตั้งค่า header สำหรับ Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // เริ่มสร้างไฟล์ Excel
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns="http://www.w3.org/TR/REC-html40">
          <head>
          <meta charset="UTF-8">
          <!--[if gte mso 9]>
          <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>' . $filename . '</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
          </xml>
          <![endif]-->
          </head>
          <body>';
    
    echo '<table border="1">';
    
    // สร้าง header
    echo '<tr style="background-color:#f2f2f2;font-weight:bold;">';
    foreach ($columnMap as $thaiName) {
        echo '<td>' . htmlspecialchars($thaiName) . '</td>';
    }
    echo '</tr>';
    
    // สร้างข้อมูล
    foreach ($data as $row) {
        echo '<tr>';
        foreach (array_keys($columnMap) as $field) {
            echo '<td>' . htmlspecialchars($row[$field] ?? '') . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    
    exit();
}

function exportToExcelWithSummary($data, $summary, $filename, $columnMap) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns="http://www.w3.org/TR/REC-html40">
          <head>
          <meta charset="UTF-8">
          <!--[if gte mso 9]>
          <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>' . $filename . '</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
          </xml>
          <![endif]-->
          </head>
          <body>';
    
    // สรุปข้อมูล
    echo '<h3>สรุปยอดรายได้</h3>';
    echo '<table border="1" style="margin-bottom:20px;">';
    echo '<tr><td>จำนวนห้องที่จอง</td><td>' . $summary['total_rooms'] . ' ห้อง</td></tr>';
    echo '<tr><td>จำนวนคืนทั้งหมด</td><td>' . $summary['total_nights'] . ' คืน</td></tr>';
    echo '<tr><td>รายได้รวม</td><td>' . number_format($summary['total_revenue'], 2) . ' บาท</td></tr>';
    echo '</table>';
    
    // ตารางรายละเอียด
    echo '<table border="1">';
    
    // Header
    echo '<tr style="background-color:#f2f2f2;font-weight:bold;">';
    foreach ($columnMap as $thaiName) {
        echo '<td>' . htmlspecialchars($thaiName) . '</td>';
    }
    echo '</tr>';
    
    // Data
    foreach ($data as $row) {
        echo '<tr>';
        foreach (array_keys($columnMap) as $field) {
            if ($field === 'total_amount' || $field === 'price_per_night') {
                echo '<td>' . number_format($row[$field] ?? 0, 2) . '</td>';
            } else {
                echo '<td>' . htmlspecialchars($row[$field] ?? '') . '</td>';
            }
        }
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    
    exit();
}

function exportToPDF($data, $filename, $columnMap) {
    // ในกรณีจริงควรใช้ library เช่น TCPDF, DomPDF
    // นี้เป็นตัวอย่างแบบง่าย
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="' . $filename . '_' . date('Ymd_His') . '.pdf"');
    
    // สร้าง HTML สำหรับแปลงเป็น PDF (ในระบบจริงควรใช้ PDF library)
    $html = '<html><head><meta charset="UTF-8"><title>' . $filename . '</title></head><body>';
    $html .= '<h2>รายงาน: ' . $filename . '</h2>';
    $html .= '<p>วันที่ออกรายงาน: ' . date('d/m/Y H:i') . '</p>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;">';
    
    // Header
    $html .= '<thead><tr style="background-color:#f2f2f2;">';
    foreach ($columnMap as $thaiName) {
        $html .= '<th>' . htmlspecialchars($thaiName) . '</th>';
    }
    $html .= '</tr></thead>';
    
    // Data
    $html .= '<tbody>';
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach (array_keys($columnMap) as $field) {
            $html .= '<td>' . htmlspecialchars($row[$field] ?? '') . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $html .= '</body></html>';
    
    // ในระบบจริงควรใช้ PDF library แทน
    echo "เนื่องจากระบบยังไม่ได้ติดตั้ง PDF library กรุณาเลือก export เป็น Excel";
    exit();
}

function exportToPDFWithSummary($data, $summary, $filename, $columnMap) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="' . $filename . '_' . date('Ymd_His') . '.pdf"');
    
    $html = '<html><head><meta charset="UTF-8"><title>' . $filename . '</title></head><body>';
    $html .= '<h2>รายงาน: ' . $filename . '</h2>';
    $html .= '<p>วันที่ออกรายงาน: ' . date('d/m/Y H:i') . '</p>';
    
    // Summary
    $html .= '<h3>สรุปยอดรายได้</h3>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:20px;">';
    $html .= '<tr><td>จำนวนห้องที่จอง</td><td>' . $summary['total_rooms'] . ' ห้อง</td></tr>';
    $html .= '<tr><td>จำนวนคืนทั้งหมด</td><td>' . $summary['total_nights'] . ' คืน</td></tr>';
    $html .= '<tr><td>รายได้รวม</td><td>' . number_format($summary['total_revenue'], 2) . ' บาท</td></tr>';
    $html .= '</table>';
    
    // Detail table
    $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;">';
    
    // Header
    $html .= '<thead><tr style="background-color:#f2f2f2;">';
    foreach ($columnMap as $thaiName) {
        $html .= '<th>' . htmlspecialchars($thaiName) . '</th>';
    }
    $html .= '</tr></thead>';
    
    // Data
    $html .= '<tbody>';
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach (array_keys($columnMap) as $field) {
            if ($field === 'total_amount' || $field === 'price_per_night') {
                $html .= '<td>' . number_format($row[$field] ?? 0, 2) . '</td>';
            } else {
                $html .= '<td>' . htmlspecialchars($row[$field] ?? '') . '</td>';
            }
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $html .= '</body></html>';
    
    echo "เนื่องจากระบบยังไม่ได้ติดตั้ง PDF library กรุณาเลือก export เป็น Excel";
    exit();
}
?>