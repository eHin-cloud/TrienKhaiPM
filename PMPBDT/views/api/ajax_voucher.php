<?php
// session_start() removed by Router
// database.php is auto-loaded by Router

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => __('method_not_supported')]);
    exit;
}

// Bắt buộc xử lý theo JSON body gửi tới từ fetch API
$data = json_decode(file_get_contents('php://input'), true);
$code = isset($data['code']) ? strtoupper(trim($data['code'])) : '';
$total_price = isset($data['total_price']) ? floatval($data['total_price']) : 0;
$bundle_discount = isset($data['bundle_discount']) ? floatval($data['bundle_discount']) : 0;

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => __('please_enter_coupon')]);
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM vouchers WHERE code = ?");
    $stmt->execute([$code]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        echo json_encode(['success' => false, 'message' => __('coupon_not_exist')]);
        exit;
    }

    // Kiểm tra giới hạn số lượng (usage_limit = 0 nghĩa là vô hạn)
    if ($voucher['usage_limit'] > 0 && $voucher['used_count'] >= $voucher['usage_limit']) {
        echo json_encode(['success' => false, 'message' => __('coupon_limit_reached')]);
        exit;
    }

    // CHỐNG SPAM TRỤC LỢI MÃ: Mỗi tài khoản chỉ được xài duy nhất 1 mã loại Đăng ký Newsletter (NEWS/GUEST)
    if (isset($_SESSION['user_id']) && (str_starts_with(strtoupper($voucher['code']), 'GUEST') || str_starts_with(strtoupper($voucher['code']), 'NEWS'))) {
        $stmt_spam = $db->prepare("
            SELECT id FROM orders 
            WHERE user_id = ? 
            AND (voucher_code LIKE 'GUEST%' OR voucher_code LIKE 'NEWS%') 
            AND status != 'cancelled'
        ");
        $stmt_spam->execute([$_SESSION['user_id']]);
        if ($stmt_spam->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Tài khoản của bạn đã hết lượt sử dụng mã ưu đãi đăng ký mới!']);
            exit;
        }
    }
    
    // Kiểm tra tính toán số tiền giảm
    $discount_value = 0;
    if ($voucher['discount_type'] === 'percent') {
        $discount_value = $total_price * ($voucher['discount_amount'] / 100);
        // Có thể bổ sung giới hạn giảm tối đa ở đây nếu cần trong tương lai
    } else {
        $discount_value = $voucher['discount_amount'];
    }

    // Nếu số tiền giảm lớn hơn tổng tiền -> chỉ giảm bằng tổng tiền (hoá đơn 0đ)
    if ($discount_value > $total_price) {
        $discount_value = $total_price;
    }

    // Lưu vào session để lúc thanh toán thật (checkout submit) gọi ra dùng
    $_SESSION['applied_voucher'] = [
        'code' => $voucher['code'],
        'discount_amount' => $discount_value,
        'discount_type' => $voucher['discount_type'], // Để show ra UI
        'raw_discount' => $voucher['discount_amount'] // 10% hay 10000đ
    ];

    $final_total = $total_price - $bundle_discount - $discount_value;
    if ($final_total < 0) {
        $final_total = 0;
    }

    echo json_encode([
        'success' => true,
        'message' => __('coupon_applied'),
        'discount_amount' => $discount_value,
        'new_total' => $final_total,
        'discount_text' => ($voucher['discount_type'] === 'percent') ? __("discount_prefix") . " {$voucher['discount_amount']}%" : __("discount_prefix") . " " . number_format($voucher['discount_amount']) . "đ"
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => __('system_error') . ': ' . $e->getMessage()]);
}
?>
