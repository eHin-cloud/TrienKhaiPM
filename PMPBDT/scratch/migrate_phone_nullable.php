<?php
require_once __DIR__ . '/../core/database.php';

try {
    echo "Bắt đầu migrate chuyển cột phone thành NULL cho đăng nhập Google...\n";

    // 1. Chuyển đổi cột phone thành NULL
    $db->exec("ALTER TABLE users MODIFY COLUMN phone VARCHAR(20) NULL");
    echo "- Đã chuyển cột phone thành công sang kiểu dữ liệu cho phép NULL.\n";

    // 2. Cập nhật dữ liệu cũ dạng 'google-%' thành NULL
    $stmtUpdate = $db->prepare("UPDATE users SET phone = NULL WHERE phone LIKE 'google-%'");
    $stmtUpdate->execute();
    $updatedRows = $stmtUpdate->rowCount();
    echo "- Đã cập nhật thành công $updatedRows tài khoản Google cũ có số điện thoại dạng mã google về NULL.\n";

    echo "Migrate HOÀN TẤT THÀNH CÔNG!\n";
} catch (Exception $e) {
    echo "LỖI MIGRATE: " . $e->getMessage() . "\n";
}
