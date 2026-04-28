<?php

namespace App\Database;

use PDO;
use PDOException;

/**
 * DatabaseConnection
 * Singleton pattern để quản lý kết nối PDO tới database.
 */

class DatabaseConnection
{
    private static ?PDO $instance = null;
    private static ?string $host = null;
    private static ?string $dbname = null;
    private static ?string $user = null;
    private static ?string $password = null;
    /**
     * Lấy một thể hiện Singleton của DatabaseConnection.
     * @return PDO
     * @throws \Exception Nếu không thể kết nối cơ sở dữ liệu.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // *** LƯU Ý QUAN TRỌNG: Cần cập nhật các thông tin này ***
                        self::$host = 'localhost'; // Thay thế bằng host thực tế nếu khác
            self::$dbname = 'dienmay'; // Tên DB của bạn
            self::$user = 'root';    // User DB của bạn
            self::$password = '';    // Password DB của bạn
            // self::$host = 'sql303.infinityfree.com'; // InfinityFree DB Host
            // self::$dbname = 'if0_41311865_dienmay'; // InfinityFree DB Name
            // self::$user = 'if0_41311865';    // InfinityFree DB User
            // self::$password = '123000321Aa';    // InfinityFree DB Password

            try {
                $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Bắt lỗi PDO thành Exception
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Mặc định trả về mảng kết hợp
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                self::$instance = new PDO($dsn, self::$user, self::$password, $options);

            } catch (PDOException $e) {
                // Trong môi trường production, không nên in chi tiết lỗi này ra người dùng
                die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}
