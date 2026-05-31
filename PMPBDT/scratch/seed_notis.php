<?php
require 'core/database.php';

$userId = 1;
$notis = [
    ['title' => 'Chào mừng bạn!', 'message' => 'Chào mừng bạn đến với Điện Máy Pro. Chúc bạn có trải nghiệm mua sắm tuyệt vời!', 'type' => 'system'],
    ['title' => 'Khuyến mãi cực sốc', 'message' => 'Giảm giá lên đến 50% cho các dòng máy lạnh Inverter trong hôm nay.', 'type' => 'promo'],
    ['title' => 'Đơn hàng thành công', 'message' => 'Đơn hàng #12345 của bạn đã được giao thành công.', 'type' => 'order']
];

foreach ($notis as $n) {
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)");
    $stmt->execute([$userId, $n['title'], $n['message'], $n['type']]);
}

echo "SUCCESS: Seeded 3 notifications for user 1.\n";
