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
            // Ưu tiên đọc cấu hình động từ biến môi trường .env
            if (getenv('DB_HOST') !== false || isset($_ENV['DB_HOST']) || isset($_SERVER['DB_HOST'])) {
                self::$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? 'localhost'));
                self::$dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? 'dienmay'));
                self::$user = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? ($_SERVER['DB_USERNAME'] ?? 'root'));
                self::$password = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? ($_SERVER['DB_PASSWORD'] ?? ''));
            } else {
                // Fallback tự động nhận diện theo HTTP_HOST
                if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1' && $_SERVER['HTTP_HOST'] !== '10.0.2.2') {
                    // Cấu hình cho Hosting (1Panel)
                    self::$host = 'localhost';
                    self::$dbname = 'cmaduaqhhosting_dienmay';
                    self::$user = 'cmaduaqhhosting_dienmay';
                    self::$password = '123456789Aa@';
                } else {
                    // Cấu hình cho Local (XAMPP)
                    self::$host = 'localhost';
                    self::$dbname = 'dienmay';
                    self::$user = 'root';
                    self::$password = '';
                }
            }

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
