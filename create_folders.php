<?php
/**
 * Create Required Folders Script
 * สคริปต์สร้างโฟลเดอร์ที่จำเป็นสำหรับระบบ
 */

echo "<h3>กำลังสร้างโครงสร้างโฟลเดอร์...</h3>";

// รายการโฟลเดอร์ที่ต้องสร้าง
$folders = [
    'assets',
    'assets/css',
    'assets/js',
    'assets/images',
    'assets/images/uploads',
    'assets/plugins',
    'config',
    'admin',
    'staff',
    'approver',
    'commander',
    'user',
    'general',
    'reports',
    'notifications',
    'api'
];

$created_count = 0;
$error_count = 0;

foreach ($folders as $folder) {
    if (!file_exists($folder)) {
        if (mkdir($folder, 0755, true)) {
            echo "<p style='color: green;'>✓ สร้างโฟลเดอร์: {$folder}</p>";
            $created_count++;
            
            // สร้างไฟล์ .htaccess เพื่อป้องกันการเข้าถึง
            if (strpos($folder, 'assets') !== false || 
                strpos($folder, 'uploads') !== false) {
                $htaccess_content = "Options -Indexes\n";
                $htaccess_content .= "Deny from all\n";
                file_put_contents($folder . '/.htaccess', $htaccess_content);
                echo "<p style='color: blue;'>  ✓ สร้างไฟล์ .htaccess สำหรับ: {$folder}</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ ไม่สามารถสร้างโฟลเดอร์: {$folder}</p>";
            $error_count++;
        }
    } else {
        echo "<p style='color: orange;'>✓ โฟลเดอร์มีอยู่แล้ว: {$folder}</p>";
    }
}

// สร้างไฟล์ index.html ในแต่ละโฟลเดอร์เพื่อป้องกัน directory listing
$directories = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('.', RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($directories as $directory) {
    if ($directory->isDir()) {
        $dir_path = $directory->getPathname();
        $index_file = $dir_path . '/index.html';
        
        if (!file_exists($index_file)) {
            $html_content = "<!DOCTYPE html>\n<html>\n<head>\n    <title>403 Forbidden</title>\n</head>\n<body>\n    <h1>403 Forbidden</h1>\n    <p>Access to this directory is not allowed.</p>\n</body>\n</html>";
            file_put_contents($index_file, $html_content);
            echo "<p style='color: blue;'>  ✓ สร้าง index.html ใน: {$dir_path}</p>";
        }
    }
}

echo "<h3>สรุปผลการสร้างโฟลเดอร์:</h3>";
echo "<p>สร้างโฟลเดอร์ใหม่: {$created_count}</p>";
echo "<p>เกิดข้อผิดพลาด: {$error_count}</p>";
echo "<p>ดำเนินการเสร็จสิ้น!</p>";

// ให้ลิงก์ไปยังหน้า settings
echo "<p><a href='admin/settings.php'>คลิกที่นี่เพื่อไปยังหน้าการตั้งค่าระบบ</a></p>";
?>