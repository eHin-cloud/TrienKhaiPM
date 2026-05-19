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
                self::selfHealingSchema(self::$instance);

            } catch (PDOException $e) {
                // Tự động kết nối fallback tới database local nếu cấu hình thất bại khi phát triển local
                $shouldTryFallback = false;
                if (self::$host !== 'localhost' && self::$host !== '127.0.0.1') {
                    $shouldTryFallback = true;
                } else if (self::$user !== 'root' || self::$password !== '' || self::$dbname !== 'dienmay') {
                    $shouldTryFallback = true;
                }

                if ($shouldTryFallback) {
                    try {
                        // Thử với 127.0.0.1 trước
                        $dsn = "mysql:host=127.0.0.1;dbname=dienmay;charset=utf8mb4";
                        self::$instance = new PDO($dsn, 'root', '', $options);
                        self::selfHealingSchema(self::$instance);
                    } catch (PDOException $e2) {
                        try {
                            // Thử tiếp với localhost
                            $dsn = "mysql:host=localhost;dbname=dienmay;charset=utf8mb4";
                            self::$instance = new PDO($dsn, 'root', '', $options);
                            self::selfHealingSchema(self::$instance);
                        } catch (PDOException $e3) {
                            die("Lỗi kết nối cơ sở dữ liệu gốc: " . $e->getMessage() . " | Lỗi kết nối fallback (127.0.0.1): " . $e2->getMessage() . " | Lỗi kết nối fallback (localhost): " . $e3->getMessage());
                        }
                    }
                } else {
                    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
                }
            }
        }
        return self::$instance;
    }

    /**
     * Tự động sửa đổi cấu trúc bảng products nếu thiếu cột mới trên môi trường hosting.
     */
    private static function selfHealingSchema(PDO $pdo): void
    {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM products");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($columns)) {
                // 1. more_images
                if (!in_array('more_images', $columns)) {
                    $pdo->exec("ALTER TABLE products ADD COLUMN more_images TEXT NULL AFTER image");
                }
                // 2. warranty_months
                if (!in_array('warranty_months', $columns)) {
                    $pdo->exec("ALTER TABLE products ADD COLUMN warranty_months INT NULL DEFAULT 12");
                }
                // 3. gift_text
                if (!in_array('gift_text', $columns)) {
                    $pdo->exec("ALTER TABLE products ADD COLUMN gift_text VARCHAR(255) NULL");
                }
                // 4. tags
                if (!in_array('tags', $columns)) {
                    $pdo->exec("ALTER TABLE products ADD COLUMN tags VARCHAR(255) NULL");
                }
                // 5. stock
                if (!in_array('stock', $columns)) {
                    $pdo->exec("ALTER TABLE products ADD COLUMN stock INT NOT NULL DEFAULT 100");
                }
            }

            // Kiểm tra và bổ sung cột is_deducted cho bảng orders
            $stmtOrders = $pdo->query("SHOW COLUMNS FROM orders");
            $ordersColumns = $stmtOrders->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($ordersColumns) && !in_array('is_deducted', $ordersColumns)) {
                $pdo->exec("ALTER TABLE orders ADD COLUMN is_deducted TINYINT NOT NULL DEFAULT 0");
            }
        } catch (\Throwable $e) {
            // Không làm sập ứng dụng nếu quá trình kiểm tra cấu trúc DB lỗi
        }
    }
}
