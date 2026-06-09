<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Database\DatabaseConnection;

ini_set('display_errors', 1);
error_reporting(E_ALL);

function ensureRow(PDO $db, string $table, array $criteria, array $data = [], string $primaryKey = 'id'): int {
    $whereClauses = [];
    $params = [];
    foreach ($criteria as $field => $value) {
        $whereClauses[] = "`$field` = ?";
        $params[] = $value;
    }
    $where = implode(' AND ', $whereClauses);
    $stmt = $db->prepare("SELECT `$primaryKey` FROM `$table` WHERE $where LIMIT 1");
    $stmt->execute($params);
    $existingKey = $stmt->fetchColumn();
    if ($existingKey) {
        if (!empty($data)) {
            $updateClauses = [];
            $updateParams = [];
            foreach ($data as $field => $value) {
                $updateClauses[] = "`$field` = ?";
                $updateParams[] = $value;
            }
            if (!empty($updateClauses)) {
                $updateParams[] = $existingKey;
                $updateSql = "UPDATE `$table` SET " . implode(', ', $updateClauses) . " WHERE `$primaryKey` = ?";
                $updateStmt = $db->prepare($updateSql);
                $updateStmt->execute($updateParams);
            }
        }
        return (int)$existingKey;
    }

    $payload = array_merge($criteria, $data);
    $columns = implode(', ', array_map(fn($key) => "`$key`", array_keys($payload)));
    $placeholders = implode(', ', array_fill(0, count($payload), '?'));
    $stmt = $db->prepare("INSERT INTO `$table` ($columns) VALUES ($placeholders)");
    $stmt->execute(array_values($payload));
    return (int)$db->lastInsertId();
}

function ensureCrossSell(PDO $db, int $productId, int $accessoryId, int $priority = 0, float $discountPercent = 0.0, float $discountAmount = 0.0, string $relationType = 'accessory'): void {
    $stmt = $db->prepare("SELECT id FROM product_cross_sell WHERE product_id = ? AND accessory_product_id = ? LIMIT 1");
    $stmt->execute([$productId, $accessoryId]);
    if ($stmt->fetchColumn()) {
        return;
    }
    $stmt = $db->prepare("INSERT INTO product_cross_sell (product_id, accessory_product_id, relation_type, priority, discount_percent, discount_amount) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$productId, $accessoryId, $relationType, $priority, $discountPercent, $discountAmount]);
}

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT);
}

try {
    $db = DatabaseConnection::getInstance();
    echo "[INFO] Đã kết nối DB thành công.\n";

    $categories = [
        ['name' => 'Tivi', 'icon' => 'fa-solid fa-tv'],
        ['name' => 'Tủ lạnh', 'icon' => 'fa-solid fa-snowflake'],
        ['name' => 'Máy giặt', 'icon' => 'fa-solid fa-shirt'],
        ['name' => 'Laptop', 'icon' => 'fa-solid fa-laptop'],
        ['name' => 'Điện thoại', 'icon' => 'fa-solid fa-mobile-screen'],
        ['name' => 'Máy lạnh', 'icon' => 'fa-solid fa-wind']
    ];

    $brands = [
        ['name' => 'Samsung'], ['name' => 'LG'], ['name' => 'Sony'], ['name' => 'Panasonic'],
        ['name' => 'Apple'], ['name' => 'Asus'], ['name' => 'Xiaomi'], ['name' => 'Dell']
    ];

    $categoryIds = [];
    foreach ($categories as $category) {
        $categoryIds[$category['name']] = ensureRow($db, 'categories', ['name' => $category['name']], ['icon' => $category['icon']]);
    }

    $brandIds = [];
    foreach ($brands as $brand) {
        $brandIds[$brand['name']] = ensureRow($db, 'brands', ['name' => $brand['name']]);
    }

    $products = [
        ['name' => 'Smart TV Samsung 55 inch QLED', 'category_id' => $categoryIds['Tivi'], 'brand_id' => $brandIds['Samsung'], 'price' => 24990000, 'old_price' => 29990000, 'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=80', 'gift_text' => 'Tặng HDMI 2m', 'tags' => 'smart,tivi,qled', 'description' => 'Smart TV Samsung 55 inch, cho hình ảnh sống động, âm thanh chuẩn rạp hát.', 'specifications' => 'Độ phân giải 4K, HDR10+, Tizen OS', 'warranty_months' => 24],
        ['name' => 'Tủ lạnh LG Inverter 260L', 'category_id' => $categoryIds['Tủ lạnh'], 'brand_id' => $brandIds['LG'], 'price' => 8590000, 'old_price' => 10990000, 'image' => 'https://images.unsplash.com/photo-1578898885447-0d2d963d91dd?auto=format&fit=crop&w=800&q=80', 'gift_text' => 'Tặng bình giữ nhiệt', 'tags' => 'tu-lanh,inverter', 'description' => 'Tủ lạnh LG Inverter tiết kiệm điện, làm lạnh nhanh với công nghệ DoorCooling+.', 'specifications' => 'Dung tích 260L, Inverter, kháng khuẩn Hygiene Fresh', 'warranty_months' => 24],
        ['name' => 'Máy giặt Samsung Inverter 9kg', 'category_id' => $categoryIds['Máy giặt'], 'brand_id' => $brandIds['Samsung'], 'price' => 7690000, 'old_price' => 9490000, 'image' => 'https://images.unsplash.com/photo-1517960413843-0aee7d7df23d?auto=format&fit=crop&w=800&q=80', 'gift_text' => 'Tặng 5kg bột giặt', 'tags' => 'may-giat,inverter', 'description' => 'Máy giặt Samsung 9kg phù hợp gia đình 4-5 người, hoạt động êm ái.', 'specifications' => '9kg, công nghệ EcoBubble, giặt chăn ga', 'warranty_months' => 24],
        ['name' => 'Laptop Apple MacBook Air M2 13 inch', 'category_id' => $categoryIds['Laptop'], 'brand_id' => $brandIds['Apple'], 'price' => 31990000, 'old_price' => 34990000, 'image' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=800&q=80', 'gift_text' => 'Tặng ốp lưng chính hãng', 'tags' => 'laptop,macbook,m2', 'description' => 'MacBook Air M2 nhỏ gọn, hiệu năng mạnh mẽ cho sinh viên và văn phòng.', 'specifications' => 'Chip M2, 8GB RAM, 256GB SSD', 'warranty_months' => 12],
        ['name' => 'Điện thoại Apple iPhone 14 128GB', 'category_id' => $categoryIds['Điện thoại'], 'brand_id' => $brandIds['Apple'], 'price' => 23990000, 'old_price' => 25990000, 'image' => 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?auto=format&fit=crop&w=800&q=80', 'gift_text' => 'Tặng cường lực 3D', 'tags' => 'dien-thoai,iphone,apple', 'description' => 'iPhone 14 chính hãng, camera siêu nét, hiệu suất A15 Bionic.', 'specifications' => '128GB, màn hình 6.1 inch, iOS 17', 'warranty_months' => 12],
        ['name' => 'Laptop Asus Vivobook 14', 'category_id' => $categoryIds['Laptop'], 'brand_id' => $brandIds['Asus'], 'price' => 12990000, 'old_price' => 14990000, 'image' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=800&q=80', 'gift_text' => 'Tặng balo laptop', 'tags' => 'laptop,asus', 'description' => 'Asus Vivobook 14 phù hợp học tập và văn phòng, thiết kế nhẹ nhàng.', 'specifications' => 'i5, 8GB RAM, 512GB SSD', 'warranty_months' => 12],
        ['name' => 'Điện thoại Xiaomi Redmi Note 12', 'category_id' => $categoryIds['Điện thoại'], 'brand_id' => $brandIds['Xiaomi'], 'price' => 5990000, 'old_price' => 6990000, 'image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=800&q=80', 'gift_text' => 'Tặng tai nghe Bluetooth', 'tags' => 'dien-thoai,xiaomi', 'description' => 'Xiaomi Redmi Note 12 pin trâu, camera 108MP, giá rẻ nhưng mạnh mẽ.', 'specifications' => '6GB RAM, 128GB ROM, sạc nhanh 33W', 'warranty_months' => 12],
        ['name' => 'Smart TV Sony 43 inch 4K', 'category_id' => $categoryIds['Tivi'], 'brand_id' => $brandIds['Sony'], 'price' => 11990000, 'old_price' => 14990000, 'image' => 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=800&q=80', 'gift_text' => 'Tặng tư vấn lắp đặt miễn phí', 'tags' => 'tivi,sony,4k', 'description' => 'Smart TV Sony 43 inch cho màu sắc chân thực và âm thanh sống động.', 'specifications' => '4K HDR, Android TV', 'warranty_months' => 24],
        ['name' => 'Tủ lạnh Panasonic 180L', 'category_id' => $categoryIds['Tủ lạnh'], 'brand_id' => $brandIds['Panasonic'], 'price' => 6890000, 'old_price' => 7890000, 'image' => 'https://images.unsplash.com/photo-1556909198-2c7b3cddfaec?auto=format&fit=crop&w=800&q=80', 'gift_text' => 'Tặng bộ 3 hộp bảo quản', 'tags' => 'tu-lanh,panasonic', 'description' => 'Tủ lạnh Panasonic thiết kế nhỏ gọn, phù hợp gia đình 2-3 người.', 'specifications' => '180L, làm lạnh nhanh, kháng khuẩn', 'warranty_months' => 24],
        ['name' => 'Máy giặt Panasonic 8kg', 'category_id' => $categoryIds['Máy giặt'], 'brand_id' => $brandIds['Panasonic'], 'price' => 7190000, 'old_price' => 8290000, 'image' => 'https://images.unsplash.com/photo-1515378791036-0648a3ef77b2?auto=format&fit=crop&w=800&q=80', 'gift_text' => 'Tặng khăn lau cao cấp', 'tags' => 'may-giat,panasonic', 'description' => 'Máy giặt Panasonic 8kg tiết kiệm điện với lồng giặt mềm mại.', 'specifications' => '8kg, inverter, giặt nhanh', 'warranty_months' => 24],
        ['name' => 'Điện thoại Samsung Galaxy A54', 'category_id' => $categoryIds['Điện thoại'], 'brand_id' => $brandIds['Samsung'], 'price' => 9990000, 'old_price' => 11990000, 'image' => 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?auto=format&fit=crop&w=800&q=80', 'gift_text' => 'Tặng ốp lưng chính hãng', 'tags' => 'dien-thoai,samsung', 'description' => 'Samsung Galaxy A54 pin khỏe, camera ổn định, trải nghiệm mượt mà.', 'specifications' => '8GB RAM, 128GB ROM, IP67', 'warranty_months' => 12],
        ['name' => 'Laptop Dell Inspiron 15', 'category_id' => $categoryIds['Laptop'], 'brand_id' => $brandIds['Dell'], 'price' => 15990000, 'old_price' => 17990000, 'image' => 'https://images.unsplash.com/photo-1522199710521-72d69614c702?auto=format&fit=crop&w=800&q=80', 'gift_text' => 'Tặng chuột không dây', 'tags' => 'laptop,dell', 'description' => 'Dell Inspiron 15 mạnh mẽ, phù hợp làm việc và giải trí.', 'specifications' => 'i5, 16GB RAM, 512GB SSD', 'warranty_months' => 12]
    ];

    $productIds = [];
    foreach ($products as $product) {
        $productIds[$product['name']] = ensureRow($db, 'products', ['name' => $product['name']], $product);
    }

    $users = [
        ['phone' => '0123456789', 'username' => 'admin', 'password' => hashPassword('12345678'), 'fullname' => 'Admin Demo', 'role' => 'admin', 'email' => 'admin@test.local', 'address' => 'Hà Nội', 'is_banned' => 0],
        ['phone' => '0987654321', 'username' => 'customer', 'password' => hashPassword('customer123'), 'fullname' => 'Khách Hàng', 'role' => 'customer', 'email' => 'customer@test.local', 'address' => 'TP. Hồ Chí Minh', 'is_banned' => 0],
        ['phone' => '0911111111', 'username' => 'linh', 'password' => hashPassword('linh12345'), 'fullname' => 'Linh Nguyễn', 'role' => 'customer', 'email' => 'linh@test.local', 'address' => 'Đà Nẵng', 'is_banned' => 0],
        ['phone' => '0922222222', 'username' => 'minh', 'password' => hashPassword('minh12345'), 'fullname' => 'Minh Trần', 'role' => 'customer', 'email' => 'minh@test.local', 'address' => 'Hải Phòng', 'is_banned' => 0]
    ];

    $userIds = [];
    foreach ($users as $user) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR phone = ? LIMIT 1");
        $stmt->execute([$user['username'], $user['phone']]);
        $existingId = $stmt->fetchColumn();
        if ($existingId) {
            $stmt = $db->prepare("UPDATE users SET password = ?, fullname = ?, role = ?, email = ?, address = ?, is_banned = ? WHERE id = ?");
            $stmt->execute([$user['password'], $user['fullname'], $user['role'], $user['email'], $user['address'], $user['is_banned'], $existingId]);
            $userIds[$user['username']] = (int)$existingId;
            continue;
        }
        $stmt = $db->prepare("INSERT INTO users (phone, username, password, fullname, role, email, address, is_banned) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user['phone'], $user['username'], $user['password'], $user['fullname'], $user['role'], $user['email'], $user['address'], $user['is_banned']]);
        $userIds[$user['username']] = (int)$db->lastInsertId();
    }

    $vouchers = [
        ['code' => 'SAVE50', 'discount_amount' => 50000, 'discount_type' => 'fixed', 'usage_limit' => 100],
        ['code' => 'NEWUSER10', 'discount_amount' => 10, 'discount_type' => 'percent', 'usage_limit' => 100],
        ['code' => 'HOLIDAY20', 'discount_amount' => 20, 'discount_type' => 'percent', 'usage_limit' => 50],
        ['code' => 'FLASH100', 'discount_amount' => 100000, 'discount_type' => 'fixed', 'usage_limit' => 20]
    ];
    foreach ($vouchers as $voucher) {
        ensureRow($db, 'vouchers', ['code' => $voucher['code']], $voucher);
    }

    ensureRow($db, 'site_settings', ['setting_key' => 'site_name'], ['setting_value' => 'PMVSCuoi Test Shop'], 'setting_key');
    ensureRow($db, 'site_settings', ['setting_key' => 'support_email'], ['setting_value' => 'support@test.local'], 'setting_key');
    ensureRow($db, 'site_settings', ['setting_key' => 'support_phone'], ['setting_value' => '19001234'], 'setting_key');

    $crossSellPairs = [
        ['product' => 'Smart TV Samsung 55 inch QLED', 'accessory' => 'Laptop Apple MacBook Air M2 13 inch', 'priority' => 1, 'discount_percent' => 10, 'relation_type' => 'combo'],
        ['product' => 'Smart TV Samsung 55 inch QLED', 'accessory' => 'Điện thoại Samsung Galaxy A54', 'priority' => 2, 'discount_percent' => 8, 'relation_type' => 'accessory'],
        ['product' => 'Tủ lạnh LG Inverter 260L', 'accessory' => 'Máy giặt Samsung Inverter 9kg', 'priority' => 1, 'discount_amount' => 150000, 'relation_type' => 'combo'],
        ['product' => 'Laptop Apple MacBook Air M2 13 inch', 'accessory' => 'Điện thoại Apple iPhone 14 128GB', 'priority' => 1, 'discount_percent' => 12, 'relation_type' => 'accessory']
    ];
    foreach ($crossSellPairs as $pair) {
        ensureCrossSell($db, $productIds[$pair['product']], $productIds[$pair['accessory']], $pair['priority'] ?? 0, $pair['discount_percent'] ?? 0.0, $pair['discount_amount'] ?? 0.0, $pair['relation_type'] ?? 'accessory');
    }

    $customerId = $userIds['customer'];
    $linhId = $userIds['linh'];
    $minhId = $userIds['minh'];

    $orders = [
        ['user_id' => $customerId, 'fullname' => 'Khách Hàng', 'phone' => '0987654321', 'address' => 'TP. Hồ Chí Minh', 'note' => 'Giao giờ hành chính', 'total_price' => 32680000, 'voucher_code' => 'SAVE50', 'discount_amount' => 50000, 'payment_method' => 'cod', 'status' => 'completed', 'created_at' => '2026-05-01 09:15:00', 'completed_at' => '2026-05-02 15:30:00'],
        ['user_id' => $customerId, 'fullname' => 'Khách Hàng', 'phone' => '0987654321', 'address' => 'TP. Hồ Chí Minh', 'note' => 'Gọi trước khi giao', 'total_price' => 7690000, 'voucher_code' => null, 'discount_amount' => 0, 'payment_method' => 'banking', 'status' => 'shipping', 'created_at' => '2026-05-04 11:20:00', 'completed_at' => null],
        ['user_id' => $linhId, 'fullname' => 'Linh Nguyễn', 'phone' => '0911111111', 'address' => 'Đà Nẵng', 'note' => 'Cần lắp đặt', 'total_price' => 24590000, 'voucher_code' => 'NEWUSER10', 'discount_amount' => 2399000, 'payment_method' => 'cod', 'status' => 'pending', 'created_at' => '2026-05-05 14:45:00', 'completed_at' => null],
        ['user_id' => $minhId, 'fullname' => 'Minh Trần', 'phone' => '0922222222', 'address' => 'Hải Phòng', 'note' => 'Liên hệ ngoài giờ', 'total_price' => 9990000, 'voucher_code' => 'HOLIDAY20', 'discount_amount' => 1998000, 'payment_method' => 'banking', 'status' => 'completed', 'created_at' => '2026-04-28 18:05:00', 'completed_at' => '2026-04-29 10:00:00']
    ];

    $orderIds = [];
    foreach ($orders as $order) {
        $stmt = $db->prepare("SELECT id FROM orders WHERE user_id = ? AND created_at = ? LIMIT 1");
        $stmt->execute([$order['user_id'], $order['created_at']]);
        $existingId = $stmt->fetchColumn();
        if ($existingId) {
            $orderIds[] = (int)$existingId;
            continue;
        }
        $stmt = $db->prepare("INSERT INTO orders (user_id, fullname, phone, address, note, total_price, voucher_code, discount_amount, payment_method, status, created_at, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$order['user_id'], $order['fullname'], $order['phone'], $order['address'], $order['note'], $order['total_price'], $order['voucher_code'], $order['discount_amount'], $order['payment_method'], $order['status'], $order['created_at'], $order['completed_at']]);
        $orderIds[] = (int)$db->lastInsertId();
    }

    $orderDetails = [
        [$orderIds[0], 'Smart TV Samsung 55 inch QLED', 1, 24990000],
        [$orderIds[0], 'Laptop Apple MacBook Air M2 13 inch', 1, 31990000],
        [$orderIds[1], 'Máy giặt Samsung Inverter 9kg', 1, 7690000],
        [$orderIds[2], 'Điện thoại Apple iPhone 14 128GB', 1, 23990000],
        [$orderIds[2], 'Điện thoại Xiaomi Redmi Note 12', 1, 5990000],
        [$orderIds[3], 'Điện thoại Samsung Galaxy A54', 1, 9990000]
    ];
    foreach ($orderDetails as [$orderId, $productName, $qty, $price]) {
        $productId = $productIds[$productName] ?? null;
        if (!$productId) continue;
        $stmt = $db->prepare("SELECT id FROM order_details WHERE order_id = ? AND product_id = ? LIMIT 1");
        $stmt->execute([$orderId, $productId]);
        if (!$stmt->fetchColumn()) {
            $stmt = $db->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$orderId, $productId, $qty, $price]);
        }
    }

    $reviews = [
        ['product' => 'Smart TV Samsung 55 inch QLED', 'user_id' => $customerId, 'rating' => 5, 'comment' => 'Hình ảnh rất đẹp, giao hàng nhanh.', 'created_at' => '2026-05-03 10:00:00'],
        ['product' => 'Laptop Apple MacBook Air M2 13 inch', 'user_id' => $customerId, 'rating' => 5, 'comment' => 'Máy mượt, pin tốt.', 'created_at' => '2026-05-03 11:00:00'],
        ['product' => 'Điện thoại Apple iPhone 14 128GB', 'user_id' => $linhId, 'rating' => 4, 'comment' => 'Camera ổn, máy đẹp.', 'created_at' => '2026-05-06 09:30:00'],
        ['product' => 'Điện thoại Samsung Galaxy A54', 'user_id' => $minhId, 'rating' => 4, 'comment' => 'Dùng ổn, pin khá tốt.', 'created_at' => '2026-04-30 15:40:00']
    ];
    foreach ($reviews as $review) {
        $productId = $productIds[$review['product']] ?? null;
        if (!$productId) continue;
        $stmt = $db->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$productId, $review['user_id']]);
        if (!$stmt->fetchColumn()) {
            $stmt = $db->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$productId, $review['user_id'], $review['rating'], $review['comment'], $review['created_at']]);
        }
    }

    $loginHistory = [
        [$customerId, '2026-05-08 08:20:00', '127.0.0.1', 'Ho Chi Minh, Vietnam', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0', 'success'],
        [$customerId, '2026-05-07 21:10:00', '127.0.0.1', 'Ho Chi Minh, Vietnam', 'Mozilla/5.0 (Android 14; Mobile) AppleWebKit/537.36 Chrome/124.0', 'failed'],
        [$linhId, '2026-05-08 09:05:00', '127.0.0.1', 'Da Nang, Vietnam', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0', 'success'],
        [$minhId, '2026-05-08 09:30:00', '127.0.0.1', 'Hai Phong, Vietnam', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/605.1.15 Safari/605.1.15', 'success']
    ];
    foreach ($loginHistory as [$userId, $time, $ip, $location, $agent, $status]) {
        $stmt = $db->prepare("SELECT id FROM login_history WHERE user_id = ? AND login_time = ? LIMIT 1");
        $stmt->execute([$userId, $time]);
        if (!$stmt->fetchColumn()) {
            $stmt = $db->prepare("INSERT INTO login_history (user_id, login_time, ip_address, location, user_agent, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $time, $ip, $location, $agent, $status]);
        }
    }

    $recentViews = [
        [$customerId, 'Smart TV Samsung 55 inch QLED', '2026-05-08 10:00:00'],
        [$customerId, 'Điện thoại Xiaomi Redmi Note 12', '2026-05-08 10:05:00'],
        [$linhId, 'Laptop Apple MacBook Air M2 13 inch', '2026-05-08 10:10:00'],
        [$minhId, 'Điện thoại Samsung Galaxy A54', '2026-05-08 10:15:00']
    ];
    foreach ($recentViews as [$userId, $productName, $viewedAt]) {
        $productId = $productIds[$productName] ?? null;
        if (!$productId) continue;
        $stmt = $db->prepare("SELECT id FROM user_recently_viewed WHERE user_id = ? AND product_id = ? LIMIT 1");
        $stmt->execute([$userId, $productId]);
        if (!$stmt->fetchColumn()) {
            $stmt = $db->prepare("INSERT INTO user_recently_viewed (user_id, product_id, viewed_at) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $productId, $viewedAt]);
        }
    }

    $cartItems = [
        [$customerId, 'Smart TV Samsung 55 inch QLED', 1],
        [$customerId, 'Điện thoại Xiaomi Redmi Note 12', 2],
        [$linhId, 'Laptop Apple MacBook Air M2 13 inch', 1],
        [$minhId, 'Điện thoại Samsung Galaxy A54', 1]
    ];
    foreach ($cartItems as [$userId, $productName, $quantity]) {
        $productId = $productIds[$productName] ?? null;
        if (!$productId) continue;
        $stmt = $db->prepare("SELECT cart_id FROM cart_items WHERE user_id = ? AND product_id = ? LIMIT 1");
        $stmt->execute([$userId, $productId]);
        if (!$stmt->fetchColumn()) {
            $stmt = $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $productId, $quantity]);
        }
    }

    $installments = [
        ['product' => 'Laptop Apple MacBook Air M2 13 inch', 'user_id' => $customerId, 'fullname' => 'Khách Hàng', 'phone' => '0987654321', 'installment_term' => '12 tháng', 'created_at' => '2026-05-04 12:00:00'],
        ['product' => 'Điện thoại Apple iPhone 14 128GB', 'user_id' => $linhId, 'fullname' => 'Linh Nguyễn', 'phone' => '0911111111', 'installment_term' => '6 tháng', 'created_at' => '2026-05-06 14:30:00']
    ];
    foreach ($installments as $item) {
        $productId = $productIds[$item['product']] ?? null;
        if (!$productId) continue;
        $stmt = $db->prepare("SELECT id FROM installment_requests WHERE product_id = ? AND phone = ? LIMIT 1");
        $stmt->execute([$productId, $item['phone']]);
        if (!$stmt->fetchColumn()) {
            $stmt = $db->prepare("INSERT INTO installment_requests (product_id, user_id, fullname, phone, installment_term, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$productId, $item['user_id'], $item['fullname'], $item['phone'], $item['installment_term'], $item['created_at']]);
        }
    }

    $warranties = [
        ['order_id' => $orderIds[0], 'product' => 'Smart TV Samsung 55 inch QLED', 'user_id' => $customerId, 'reason' => 'Màn hình bị sọc nhẹ khi khởi động', 'created_at' => '2026-05-07 09:00:00'],
        ['order_id' => $orderIds[2], 'product' => 'Điện thoại Apple iPhone 14 128GB', 'user_id' => $linhId, 'reason' => 'Kiểm tra pin và camera', 'created_at' => '2026-05-08 11:15:00']
    ];
    foreach ($warranties as $item) {
        $productId = $productIds[$item['product']] ?? null;
        if (!$productId) continue;
        $stmt = $db->prepare("SELECT id FROM warranties WHERE order_id = ? AND product_id = ? LIMIT 1");
        $stmt->execute([$item['order_id'], $productId]);
        if (!$stmt->fetchColumn()) {
            $stmt = $db->prepare("INSERT INTO warranties (order_id, product_id, user_id, reason, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$item['order_id'], $productId, $item['user_id'], $item['reason'], $item['created_at']]);
        }
    }

    $returns = [
        ['order_id' => $orderIds[1], 'user_id' => $customerId, 'reason' => 'Đổi sang model khác', 'created_at' => '2026-05-08 12:30:00'],
        ['order_id' => $orderIds[3], 'user_id' => $minhId, 'reason' => 'Hàng nhận bị móp hộp', 'created_at' => '2026-05-01 16:30:00']
    ];
    foreach ($returns as $item) {
        $stmt = $db->prepare("SELECT id FROM returns WHERE order_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$item['order_id'], $item['user_id']]);
        if (!$stmt->fetchColumn()) {
            $stmt = $db->prepare("INSERT INTO returns (order_id, user_id, reason, created_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$item['order_id'], $item['user_id'], $item['reason'], $item['created_at']]);
        }
    }

    echo "[SUCCESS] Dữ liệu ảo test đã được tạo xong.\n";
    echo "- Danh mục: " . count($categories) . " mục\n";
    echo "- Thương hiệu: " . count($brands) . " mục\n";
    echo "- Sản phẩm: " . count($products) . " mục\n";
    echo "- Voucher: " . count($vouchers) . " mã\n";
    echo "- Người dùng test: " . count($users) . " tài khoản\n";
    echo "- Đơn hàng test: " . count($orders) . "\n";
    echo "- Đánh giá, lịch sử đăng nhập, trả hàng, bảo hành, trả góp và lịch sử xem đã được gắn dữ liệu mẫu.\n";
} catch (Exception $e) {
    echo "[ERROR] Không thể tạo dữ liệu test: " . $e->getMessage() . "\n";
    exit(1);
}
