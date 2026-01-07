<?php
class DatabaseConfig {
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo === null) {
            // 1. ตรวจสอบพาธ .env (ใช้ __DIR__ เพื่ออ้างอิงตำแหน่งไฟล์ที่แน่นอน)
            $envPath = dirname(__DIR__) . '/.env'; 
            
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        putenv(trim($parts[0]) . "=" . trim($parts[1]));
                    }
                }
            }

            try {
                // 2. ดึงค่ามาเช็คก่อนเชื่อมต่อ
                $host = getenv('DB_HOST') ?: 'localhost';
                $dbname = getenv('DB_NAME');
                $user = getenv('DB_USER');
                $pass = getenv('DB_PASS');
                
                if (!$dbname || !$user) {
                    throw new Exception("Config Error: กรุณาตรวจสอบไฟล์ .env (DB_NAME หรือ DB_USER ว่างเปล่า)");
                }

                $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                self::$pdo = new PDO($dsn, $user, $pass, $options);

                // 3. แสดงข้อความถ้าเชื่อมต่อสำเร็จ
               // echo "Database connection successful!<br>";

            } catch (Exception $e) {
                // ถ้าพลาด ให้หยุดและบอกสาเหตุชัดๆ
                die("เกิดข้อผิดพลาดในการเชื่อมต่อ: " . $e->getMessage());
            }
        }
        return self::$pdo;
    }
}