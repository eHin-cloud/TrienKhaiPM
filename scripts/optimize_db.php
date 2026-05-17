<?php
/**
 * ============================================================
 * DATABASE OPTIMIZER - INDEXING
 * ============================================================
 * Thêm các chỉ mục (Index) vào các cột thường xuyên được truy vấn
 * để tăng tốc độ phản hồi của website.
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../core/database.php';

$queries = [
    // 1. Tối ưu tìm kiếm và lọc sản phẩm
    "CREATE INDEX IF NOT EXISTS idx_products_cat ON products(category_id)",
    "CREATE INDEX IF NOT EXISTS idx_products_brand ON products(brand_id)",
    "CREATE INDEX IF NOT EXISTS idx_products_price ON products(price)",
    "CREATE INDEX IF NOT EXISTS idx_products_name ON products(name)",

    // 2. Tối ưu quản lý đơn hàng
    "CREATE INDEX IF NOT EXISTS idx_orders_user ON orders(user_id)",
    "CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)",
    "CREATE INDEX IF NOT EXISTS idx_orders_date ON orders(created_at)",
    "CREATE INDEX IF NOT EXISTS idx_order_details_order ON order_details(order_id)",

    // 3. Tối ưu tương tác người dùng
    "CREATE INDEX IF NOT EXISTS idx_reviews_product ON reviews(product_id)",
    "CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id)",
    "CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read)",

    // 4. Tối ưu tài khoản và bảo mật
    "CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)",
    "CREATE INDEX IF NOT EXISTS idx_login_history_user ON login_history(user_id)",
    "CREATE INDEX IF NOT EXISTS idx_login_history_time ON login_history(login_time)"
];

echo "--- ĐANG TỐI ƯU HÓA DATABASE ---" . PHP_EOL;

foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        echo "OK: " . substr($sql, 0, 50) . "..." . PHP_EOL;
    } catch (PDOException $e) {
        // Một số DB không hỗ trợ CREATE INDEX IF NOT EXISTS, ta sẽ bắt lỗi nếu đã tồn tại
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "SKIP: Index đã tồn tại." . PHP_EOL;
        } else {
            echo "FAIL: " . $e->getMessage() . PHP_EOL;
        }
    }
}

echo "--- HOÀN THÀNH TỐI ƯU HÓA ---" . PHP_EOL;
