<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../core/database.php';

// Dọn dẹp thông báo cũ của user 2
$db->prepare("DELETE FROM notifications WHERE user_id = 2")->execute();

// Tạo các thông báo mẫu
$notis = [
    [
        'title' => 'Đơn hàng #98741467 đã được xác nhận',
        'message' => 'Đơn hàng mua Tivi Sony của bạn đã được xác nhận thanh toán thành công và đang chuyển sang bộ phận đóng gói.',
        'type' => 'order',
        'redirect_url' => 'track_order.php?order_id=98741467'
    ],
    [
        'title' => 'Voucher giảm giá 20% chỉ dành riêng cho bạn!',
        'message' => 'Nhập mã GIAM20 khi thanh toán để được giảm ngay 20% cho đơn hàng tiếp theo. Hạn dùng đến hết tuần này.',
        'type' => 'promo',
        'redirect_url' => 'index.php'
    ],
    [
        'title' => 'Bảo trì hệ thống định kỳ',
        'message' => 'Hệ thống sẽ bảo trì từ 2:00 đến 4:00 ngày mai. Giao dịch mua sắm có thể bị gián đoạn trong thời gian này.',
        'type' => 'system',
        'redirect_url' => ''
    ]
];

foreach ($notis as $n) {
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, redirect_url, is_read, created_at) VALUES (2, ?, ?, ?, ?, 0, NOW())");
    $stmt->execute([$n['title'], $n['message'], $n['type'], $n['redirect_url']]);
}

echo "SUCCESS: Seeded 3 custom notifications for user 2 (khachhang).\n";
