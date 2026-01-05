# คู่มือการติดตั้งระบบจองบ้านพักรับรอง กองบิน7

## ข้อกำหนดระบบ

### 1. ระบบปฏิบัติการ
- Windows 7/8/10/11 หรือ Windows Server 2012+
- Linux (Ubuntu 18.04+, CentOS 7+)
- macOS 10.14+

### 2. ซอฟต์แวร์ที่ต้องการ
- XAMPP (Windows) หรือ MAMP (macOS) หรือ LAMP (Linux)
  - Apache 2.4+
  - PHP 7.4+
  - MySQL 5.7+ หรือ MariaDB 10.2+
- Web Browser: Chrome 80+, Firefox 75+, Safari 13+

### 3. ความต้องการฮาร์ดแวร์
- RAM: 4GB (ขั้นต่ำ), 8GB (แนะนำ)
- Storage: 500MB สำหรับระบบ + ข้อมูล
- Processor: 2-core (ขั้นต่ำ), 4-core (แนะนำ)

## ขั้นตอนการติดตั้งบน Windows (XAMPP)

### ขั้นตอนที่ 1: ติดตั้ง XAMPP
1. ดาวน์โหลด XAMPP จาก https://www.apachefriends.org/
2. ติดตั้ง XAMPP ตามค่า default (แนะนำให้ติดตั้งใน C:\xampp)
3. เปิด Control Panel และ Start Apache และ MySQL

### ขั้นตอนที่ 2: ติดตั้งระบบ
1. คัดลอกโฟลเดอร์ `wing7-guesthouse` ไปยัง `C:\xampp\htdocs\`
2. เปลี่ยนชื่อโฟลเดอร์เป็น `wing7` หรือตามต้องการ
3. เปิดโปรแกรม phpMyAdmin โดยเข้าที่ http://localhost/phpmyadmin

### ขั้นตอนที่ 3: สร้างฐานข้อมูล
1. คลิก "New" ในเมนูด้านซ้าย
2. ตั้งชื่อฐานข้อมูล: `wing7_guest_db`
3. เลือก Collation: `utf8mb4_general_ci`
4. คลิก Create

### ขั้นตอนที่ 4: นำเข้า SQL
1. เลือกฐานข้อมูล `wing7_guest_db`
2. คลิกเมนู "Import"
3. คลิก "Choose File" และเลือก `wing7_guest_db.sql` จากโฟลเดอร์ `database/`
4. คลิก "Go"

### ขั้นตอนที่ 5: ตั้งค่า Configuration
1. เปิดไฟล์ `config/database.php`
2. ตรวจสอบการตั้งค่า:
```php
const DB_HOST = 'localhost';
const DB_NAME = 'wing7_guest_db';
const DB_USER = 'root';    // สำหรับ XAMPP
const DB_PASS = '';        // สำหรับ XAMPP (ว่างเปล่า)