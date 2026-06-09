<?php
/**
 * ============================================================
 * MASTER_SEEDER.PHP - DỮ LIỆU MẪU TOÀN DIỆN
 * ============================================================
 * Công cụ này giúp tạo dữ liệu giả lập cho toàn bộ hệ thống
 * để kiểm thử tất cả các tính năng từ Giỏ hàng, Đơn hàng,
 * đến Wishlist, Thông báo và Bảo mật.
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../core/database.php';

// Tăng thời gian thực thi và bộ nhớ
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

echo "--- BẮT ĐẦU SEEDING DỮ LIỆU MẪU ---\n";

/**
 * Hàm hỗ trợ chèn hoặc cập nhật bản ghi
 */
function seedRow(PDO $db, string $table, array $uniqueCriteria, array $additionalData = []): int {
    $where = [];
    $params = [];
    foreach ($uniqueCriteria as $k => $v) {
        $where[] = "`$k` = ?";
        $params[] = $v;
    }
    
    $stmt = $db->prepare("SELECT id FROM `$table` WHERE " . implode(' AND ', $where) . " LIMIT 1");
    $stmt->execute($params);
    $id = $stmt->fetchColumn();

    $data = array_merge($uniqueCriteria, $additionalData);
    
    if ($id) {
        // Update
        $sets = [];
        $updateParams = [];
        foreach ($data as $k => $v) {
            if ($k === 'id') continue;
            $sets[] = "`$k` = ?";
            $updateParams[] = $v;
        }
        $updateParams[] = $id;
        $db->prepare("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE id = ?")->execute($updateParams);
        return (int)$id;
    } else {
        // Insert
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        $db->prepare($sql)->execute(array_values($data));
        return (int)$db->lastInsertId();
    }
}

try {
    // 1. SEED CATEGORIES
    echo "[1/10] Seeding Categories...\n";
    $categories = [
        ['name' => 'Máy lạnh', 'icon' => 'fa-solid fa-wind'],
        ['name' => 'Tủ lạnh', 'icon' => 'fa-solid fa-snowflake'],
        ['name' => 'Tivi', 'icon' => 'fa-solid fa-tv'],
        ['name' => 'Máy giặt', 'icon' => 'fa-solid fa-shirt'],
        ['name' => 'Laptop', 'icon' => 'fa-solid fa-laptop'],
        ['name' => 'Điện thoại', 'icon' => 'fa-solid fa-mobile-screen'],
    ];
    $catIds = [];
    foreach ($categories as $cat) {
        $catIds[$cat['name']] = seedRow($db, 'categories', ['name' => $cat['name']], ['icon' => $cat['icon']]);
    }

    // 2. SEED BRANDS
    echo "[2/10] Seeding Brands...\n";
    $brands = ['Samsung', 'Sony', 'LG', 'Panasonic', 'Apple', 'Asus', 'Dell', 'Xiaomi'];
    $brandIds = [];
    foreach ($brands as $brand) {
        $brandIds[$brand] = seedRow($db, 'brands', ['name' => $brand]);
    }

    // 3. SEED USERS
    echo "[3/10] Seeding Users...\n";
    $pass = password_hash('12345678', PASSWORD_BCRYPT);
    $users = [
        ['username' => 'admin', 'fullname' => 'Quản Trị Viên', 'role' => 'admin', 'email' => 'admin@dienmaypro.local', 'phone' => '0900000001'],
        ['username' => 'manager', 'fullname' => 'Quản Lý Cửa Hàng', 'role' => 'manager', 'email' => 'manager@dienmaypro.local', 'phone' => '0900000002'],
        ['username' => 'customer', 'fullname' => 'Nguyễn Văn Khách', 'role' => 'customer', 'email' => 'customer@gmail.com', 'phone' => '0988888888'],
        ['username' => 'linh_test', 'fullname' => 'Trần Thị Linh', 'role' => 'customer', 'email' => 'linh@test.com', 'phone' => '0977777777'],
    ];
    $userIds = [];
    foreach ($users as $u) {
        $userIds[$u['username']] = seedRow($db, 'users', ['username' => $u['username']], [
            'password' => $pass,
            'fullname' => $u['fullname'],
            'role' => $u['role'],
            'email' => $u['email'],
            'phone' => $u['phone'],
            'address' => '123 Đường Test, Quận 1, TP.HCM'
        ]);
    }

    // 4. SEED PRODUCTS
    echo "[4/10] Seeding Products...\n";
    $products = [
        [
            'name' => 'Smart TV Samsung 4K 55 inch', 
            'category_id' => $catIds['Tivi'], 
            'brand_id' => $brandIds['Samsung'], 
            'price' => 12500000, 
            'old_price' => 15000000,
            'image' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?auto=format&fit=crop&w=800&q=80',
            'description' => 'Màn hình 4K sắc nét, công nghệ QLED đỉnh cao.'
        ],
        [
            'name' => 'Tủ lạnh LG Side-by-Side 635L', 
            'category_id' => $catIds['Tủ lạnh'], 
            'brand_id' => $brandIds['LG'], 
            'price' => 25900000, 
            'old_price' => 30000000,
            'image' => 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?auto=format&fit=crop&w=800&q=80',
            'description' => 'Tiết kiệm điện, lấy nước ngoài tiện lợi.'
        ],
        [
            'name' => 'iPhone 15 Pro Max 256GB', 
            'category_id' => $catIds['Điện thoại'], 
            'brand_id' => $brandIds['Apple'], 
            'price' => 32900000, 
            'old_price' => 34900000,
            'image' => 'https://images.unsplash.com/photo-1696446701796-da61225697cc?auto=format&fit=crop&w=800&q=80',
            'description' => 'Chip A17 Pro, khung viền Titan cực bền.'
        ],
        [
            'name' => 'Laptop ASUS Vivobook 15', 
            'category_id' => $catIds['Laptop'], 
            'brand_id' => $brandIds['Asus'], 
            'price' => 15500000, 
            'old_price' => 18000000,
            'image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=800&q=80',
            'description' => 'Mỏng nhẹ, hiệu năng văn phòng mượt mà.'
        ],
        [
            'name' => 'Máy giặt Panasonic Inverter 9kg', 
            'category_id' => $catIds['Máy giặt'], 
            'brand_id' => $brandIds['Panasonic'], 
            'price' => 8900000, 
            'old_price' => 10500000,
            'image' => 'https://images.unsplash.com/photo-1626806819282-2c1dc01a5e0c?auto=format&fit=crop&w=800&q=80',
            'description' => 'Giặt sạch hiệu quả, kháng khuẩn Blue Ag+.'
        ],
    ];
    $prodIds = [];
    foreach ($products as $p) {
        $prodIds[] = seedRow($db, 'products', ['name' => $p['name']], [
            'category_id' => $p['category_id'],
            'brand_id' => $p['brand_id'],
            'price' => $p['price'],
            'old_price' => $p['old_price'],
            'image' => $p['image'],
            'description' => $p['description'],
            'gift_text' => 'Tặng quà hấp dẫn',
            'tags' => 'khuyen_mai, hot',
            'warranty_months' => 12
        ]);
    }

    // 5. SEED ADDRESSES
    echo "[5/10] Seeding Addresses...\n";
    foreach ($userIds as $user => $id) {
        seedRow($db, 'addresses', ['user_id' => $id, 'fullname' => 'Nhà riêng'], [
            'phone' => '0988777666',
            'address' => '456 Đường Số 2, Quận Bình Thạnh, TP.HCM',
            'is_default' => 1
        ]);
    }

    // 6. SEED WISHLIST
    echo "[6/10] Seeding Wishlist...\n";
    $cust_id = $userIds['customer'];
    foreach (array_slice($prodIds, 0, 3) as $pid) {
        $stmt = $db->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $stmt->execute([$cust_id, $pid]);
    }

    // 7. SEED NOTIFICATIONS
    echo "[7/10] Seeding Notifications...\n";
    $notis = [
        ['title' => 'Chào mừng bạn!', 'message' => 'Cảm ơn bạn đã gia nhập DIENMAYPRO.'],
        ['title' => 'Ưu đãi cực khủng', 'message' => 'Giảm giá 50% cho tất cả các dòng Tivi Samsung.'],
        ['title' => 'Đơn hàng đã đặt', 'message' => 'Đơn hàng #1001 của bạn đã được tiếp nhận.'],
    ];
    foreach ($notis as $n) {
        seedRow($db, 'notifications', ['user_id' => $cust_id, 'title' => $n['title']], [
            'message' => $n['message'],
            'type' => 'info',
            'is_read' => 0
        ]);
    }

    // 8. SEED VOUCHERS
    echo "[8/10] Seeding Vouchers...\n";
    $vouchers = [
        ['code' => 'HELLO2024', 'discount_amount' => 50000, 'discount_type' => 'fixed'],
        ['code' => 'GIAM10', 'discount_amount' => 10, 'discount_type' => 'percent'],
    ];
    foreach ($vouchers as $v) {
        seedRow($db, 'vouchers', ['code' => $v['code']], [
            'discount_amount' => $v['discount_amount'],
            'discount_type' => $v['discount_type'],
            'usage_limit' => 100,
            'expires_at' => '2026-12-31 23:59:59'
        ]);
    }

    // 9. SEED ORDERS
    echo "[9/10] Seeding Orders...\n";
    $order_id = seedRow($db, 'orders', ['user_id' => $cust_id, 'total_price' => 12500000], [
        'fullname' => 'Nguyễn Văn Khách',
        'phone' => '0988888888',
        'address' => '123 Đường Test, Quận 1, TP.HCM',
        'status' => 'completed',
        'payment_method' => 'cod'
    ]);
    // Order Detail
    seedRow($db, 'order_details', ['order_id' => $order_id, 'product_id' => $prodIds[0]], [
        'quantity' => 1,
        'price' => 12500000
    ]);

    // 10. SEED REVIEWS
    echo "[10/10] Seeding Reviews...\n";
    seedRow($db, 'reviews', ['product_id' => $prodIds[0], 'user_id' => $cust_id], [
        'rating' => 5,
        'comment' => 'Sản phẩm tuyệt vời, hình ảnh rất sắc nét!'
    ]);

    echo "--- HOÀN THÀNH SEEDING DỮ LIỆU MẪU ---\n";
    echo "Tài khoản test:\n";
    echo "- Admin: admin / 12345678\n";
    echo "- Khách: customer / 12345678\n";

} catch (Exception $e) {
    echo "LỖI: " . $e->getMessage() . "\n";
}
