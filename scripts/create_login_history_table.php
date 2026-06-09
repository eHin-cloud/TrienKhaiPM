<?php
/**
 * Script tạo bảng login_history trong database.
 * Chạy 1 lần duy nhất để khởi tạo bảng.
 * 
 * Sử dụng: truy cập trực tiếp file này qua trình duyệt hoặc CLI.
 */

require_once __DIR__ . '/vendor/autoload.php';

try {
    $db = \App\Database\DatabaseConnection::getInstance();

    $sql = "CREATE TABLE IF NOT EXISTS `login_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `login_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'Hỗ trợ cả IPv4 và IPv6',
        `user_agent` TEXT DEFAULT NULL COMMENT 'Full User-Agent string từ trình duyệt',
        `status` ENUM('success', 'failed') NOT NULL DEFAULT 'success',
        INDEX `idx_user_id` (`user_id`),
        INDEX `idx_login_time` (`login_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lưu lịch sử đăng nhập người dùng'";

    $db->exec($sql);
    echo "<h2 style='color:green;'>✅ Bảng `login_history` đã được tạo thành công!</h2>";
    echo "<p>Bạn có thể xóa file này sau khi chạy.</p>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Lỗi: " . $e->getMessage() . "</h2>";
}
