<?php
require_once __DIR__ . '/../core/database.php';

try {
    echo "Bắt đầu migrate thêm các cột OTP vào bảng users...\n";

    // 1. Thêm các cột cho Quên mật khẩu
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_password_otp VARCHAR(10) NULL");
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_password_otp_expires_at INT NULL");
    echo "- Đã thêm thành công các cột reset_password_otp và reset_password_otp_expires_at.\n";

    // 2. Thêm các cột cho Bật 2FA
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_otp VARCHAR(10) NULL");
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_otp_expires_at INT NULL");
    echo "- Đã thêm thành công các cột two_factor_otp và two_factor_otp_expires_at.\n";

    echo "Migrate HOÀN TẤT THÀNH CÔNG!\n";
} catch (Exception $e) {
    echo "LỖI MIGRATE: " . $e->getMessage() . "\n";
}
