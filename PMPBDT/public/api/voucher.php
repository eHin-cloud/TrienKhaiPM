<?php
ob_start();
error_reporting(0);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/api.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = api_request_data();
$action = $_GET['action'] ?? ($data['action'] ?? 'list');

switch ($action) {
    case 'list':
        // Lấy danh sách voucher còn hạn, đã kích hoạt và còn lượt dùng
        $stmt = $db->query('SELECT * FROM vouchers WHERE (usage_limit = 0 OR used_count < usage_limit) AND (starts_at IS NULL OR starts_at <= NOW()) AND (expires_at IS NULL OR expires_at >= NOW()) ORDER BY created_at DESC');
        $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($vouchers as &$v) {
            $v['id'] = (int)$v['id'];
            $v['discount_amount'] = (float)$v['discount_amount'];
            $v['usage_limit'] = (int)$v['usage_limit'];
            $v['used_count'] = (int)$v['used_count'];
        }
        
        api_json_response(true, 'Lấy danh sách voucher thành công.', $vouchers);

    case 'apply':
        if ($method !== 'POST') api_json_response(false, 'Method not allowed.', [], 405);
        $code = trim($data['code'] ?? '');
        $totalPrice = (float)($data['total_price'] ?? 0);

        if (empty($code)) api_json_response(false, 'Vui lòng nhập mã giảm giá.', [], 422);

        $stmt = $db->prepare("SELECT * FROM vouchers WHERE code = ?");
        $stmt->execute([$code]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$voucher) {
            api_json_response(false, 'Mã giảm giá không tồn tại.');
        }

        if ($voucher['usage_limit'] > 0 && $voucher['used_count'] >= $voucher['usage_limit']) {
            api_json_response(false, 'Mã giảm giá này đã hết lượt sử dụng.');
        }

        if ($voucher['starts_at'] && strtotime($voucher['starts_at']) > time()) {
            api_json_response(false, 'Mã giảm giá này chưa đến thời gian kích hoạt.');
        }

        if ($voucher['expires_at'] && strtotime($voucher['expires_at']) < time()) {
            api_json_response(false, 'Mã giảm giá đã hết hạn sử dụng.');
        }

        $discount_value = 0;
        if ($voucher['discount_type'] === 'percent') {
            $discount_value = $totalPrice * ($voucher['discount_amount'] / 100);
        } else {
            $discount_value = (float)$voucher['discount_amount'];
        }

        api_json_response(true, 'Áp dụng mã giảm giá thành công.', [
            'code' => $voucher['code'],
            'discount_amount' => $discount_value,
            'discount_type' => $voucher['discount_type'],
            'raw_discount' => $voucher['discount_amount']
        ]);

    default:
        api_json_response(false, 'Action không hợp lệ.', [], 400);
}
