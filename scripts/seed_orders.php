<?php
/**
 * ============================================================
 * ORDER DATA GENERATOR (FAKE DATA FOR CHARTS)
 * ============================================================
 * Tạo ra khoảng 50-100 đơn hàng trải dài trong 30 ngày qua
 * để biểu đồ trông đẹp mắt và thực tế hơn.
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../core/database.php';

echo "--- ĐANG TẠO DỮ LIỆU ĐƠN HÀNG ẢO ---" . PHP_EOL;

// 1. Lấy danh sách User và Product để làm mẫu
$users = $db->query("SELECT id, fullname, phone, address FROM users WHERE role = 'customer'")->fetchAll(PDO::FETCH_ASSOC);
$products = $db->query("SELECT id, name, price FROM products")->fetchAll(PDO::FETCH_ASSOC);

if (empty($users) || empty($products)) {
    die("LỖI: Cần có ít nhất 1 khách hàng và 1 sản phẩm trong DB để tạo đơn hàng." . PHP_EOL);
}

$statuses = ['pending', 'delivering', 'completed', 'cancelled'];
$payments = ['cod', 'bank_transfer', 'momo'];

for ($i = 0; $i < 100; $i++) {
    $user = $users[array_rand($users)];
    $address = $user['address'] ?: 'Số 123, Đường ABC, TP.HCM';
    $status = $statuses[array_rand($statuses)];
    // Ưu tiên trạng thái hoàn thành để biểu đồ doanh thu đẹp
    if (rand(1, 10) > 4) $status = 'completed'; 
    
    $payment = $payments[array_rand($payments)];
    
    // Ngày ngẫu nhiên trong 30 ngày qua
    $daysAgo = rand(0, 30);
    $date = date('Y-m-d H:i:s', strtotime("-$daysAgo days" . " +" . rand(0, 23) . " hours"));
    
    // Chọn ngẫu nhiên 1-3 sản phẩm cho mỗi đơn
    $numItems = rand(1, 3);
    $totalPrice = 0;
    $orderItems = [];
    
    for ($j = 0; $j < $numItems; $j++) {
        $p = $products[array_rand($products)];
        $qty = rand(1, 2);
        $totalPrice += ($p['price'] * $qty);
        $orderItems[] = [
            'id' => $p['id'],
            'price' => $p['price'],
            'qty' => $qty
        ];
    }

    try {
        // Insert Order
        $stmt = $db->prepare("INSERT INTO orders (user_id, fullname, phone, address, total_price, payment_method, status, created_at, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $completedAt = ($status === 'completed') ? $date : null;
        $stmt->execute([
            $user['id'], 
            $user['fullname'], 
            $user['phone'], 
            $address, 
            $totalPrice, 
            $payment, 
            $status, 
            $date,
            $completedAt
        ]);
        
        $orderId = $db->lastInsertId();
        
        // Insert Order Details
        foreach ($orderItems as $item) {
            $db->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)")
               ->execute([$orderId, $item['id'], $item['qty'], $item['price']]);
        }
        
    } catch (Exception $e) {
        echo "Lỗi tại vòng lặp $i: " . $e->getMessage() . PHP_EOL;
    }
}

echo "--- HOÀN THÀNH: Đã tạo 100 đơn hàng ảo thành công! ---" . PHP_EOL;
