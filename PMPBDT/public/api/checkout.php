<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/lang.php';
require_once __DIR__ . '/../../core/api.php';

use App\Service\CheckoutService;
use App\Repository\OrderRepository;
use App\Repository\CartRepository;
use App\Repository\CouponRepository;
use App\Repository\UserRepository;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authUser = api_authenticated_user();
$userId = (int)($authUser['user_id'] ?? 0);
if ($userId <= 0) {
    api_json_response(false, 'Chưa đăng nhập.', [], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = api_request_data();
$action = $_GET['action'] ?? ($data['action'] ?? 'summary');

$orderRepo = new OrderRepository($db);
$cartRepo = new CartRepository($db);
$couponRepo = new CouponRepository($db);
$checkoutService = new CheckoutService($db, $orderRepo, $cartRepo, $couponRepo);
$userRepo = new UserRepository($db);

$normalizeSelectedItems = function ($items) {
    if (!is_array($items)) {
        return [];
    }
    return array_values(array_filter(array_map('intval', $items), fn($v) => $v > 0));
};

$getCartItemsBySelection = function (int $userId, array $selectedIds) use ($db) {
    $cartItems = getCartItems($db, $userId);
    if (empty($selectedIds)) {
        return $cartItems;
    }

    return array_values(array_filter($cartItems, function ($item) use ($selectedIds) {
        return in_array((int)$item['cart_id'], $selectedIds, true);
    }));
};

switch ($action) {
    case 'summary':
        if ($method !== 'POST' && $method !== 'GET') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $selectedIds = $normalizeSelectedItems($data['selected_items'] ?? $_GET['selected_items'] ?? []);
        $cartItems = $getCartItemsBySelection($userId, $selectedIds);

        if (empty($cartItems)) {
            api_json_response(false, 'Không có sản phẩm hợp lệ để thanh toán.', [], 422);
        }

        $bundleData = $checkoutService->calculateBundleDiscount($cartItems);
        $subTotal = array_sum(array_map(function ($item) {
            return $item['price'] * $item['quantity'];
        }, $cartItems));

        $voucherInput = $data['voucher'] ?? null;
        $voucherDiscount = 0.0;
        if (is_array($voucherInput)) {
            $voucherDiscount = (float) ($voucherInput['discount_amount'] ?? 0);
        }

        $finalTotal = max(0, $subTotal - $bundleData['discount'] - $voucherDiscount);

        api_json_response(true, 'Lấy thông tin thanh toán thành công.', [
            'user' => $userRepo->getUserById($userId),
            'items' => $cartItems,
            'subtotal' => $subTotal,
            'bundle_discount' => $bundleData['discount'],
            'bundle_message' => $bundleData['message'],
            'voucher' => is_array($voucherInput) ? $voucherInput : null,
            'final_total' => $finalTotal,
        ]);

    case 'create_order':
        if ($method !== 'POST') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $fullname = trim((string) ($data['fullname'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        $note = trim((string) ($data['note'] ?? ''));
        $paymentMethod = trim((string) ($data['payment_method'] ?? 'cod'));
        $selectedIds = $normalizeSelectedItems($data['selected_items'] ?? []);
        $voucherInput = is_array($data['voucher'] ?? null) ? $data['voucher'] : null;

        if ($fullname === '' || $phone === '' || $address === '') {
            api_json_response(false, 'Vui lòng nhập đầy đủ thông tin nhận hàng.', [], 422);
        }

        if (empty($selectedIds)) {
            api_json_response(false, 'Vui lòng gửi selected_items để tạo đơn hàng.', [], 422);
        }

        $cartItems = $getCartItemsBySelection($userId, $selectedIds);
        if (empty($cartItems)) {
            api_json_response(false, 'Không có sản phẩm hợp lệ để tạo đơn.', [], 422);
        }

        $bundleData = $checkoutService->calculateBundleDiscount($cartItems);
        $subTotal = array_sum(array_map(function ($item) {
            return $item['price'] * $item['quantity'];
        }, $cartItems));

        $voucherCode = '';
        $voucherDiscount = 0.0;
        if (is_array($voucherInput)) {
            $voucherCode = (string) ($voucherInput['code'] ?? '');
            $voucherDiscount = (float) ($voucherInput['discount_amount'] ?? 0);
        }

        $totalDiscount = $bundleData['discount'] + $voucherDiscount;
        $finalTotal = max(0, $subTotal - $totalDiscount);

        $result = $checkoutService->createOrderFromCart(
            $userId,
            $cartItems,
            $fullname,
            $phone,
            $address,
            $note,
            $paymentMethod,
            $voucherCode,
            $totalDiscount,
            $finalTotal
        );

        if (!$result['success']) {
            api_json_response(false, (string) $result['message'], [], 500);
        }

        api_json_response(true, 'Tạo đơn hàng thành công.', [
            'order_id' => (int) $result['order_id'],
            'payment_method' => $paymentMethod,
            'redirect_to' => $paymentMethod === 'qr' ? 'payment.php?order_id=' . (int) $result['order_id'] : 'track_order.php',
        ], 201);

    case 'apply_voucher':
        if ($method !== 'POST') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $totalPrice = (float) ($data['total_price'] ?? 0);
        $bundleDiscount = (float) ($data['bundle_discount'] ?? 0);

        if ($code === '') {
            api_json_response(false, 'Vui lòng nhập mã giảm giá.', [], 422);
        }

        $coupon = $couponRepo->findValidCoupon($code);
        if (!$coupon) {
            api_json_response(false, 'Mã giảm giá không hợp lệ hoặc đã hết hạn.', [], 422);
        }

        $discountValue = 0.0;
        if (($coupon['discount_type'] ?? '') === 'percent') {
            $discountValue = $totalPrice * ((float) $coupon['discount_amount'] / 100);
        } else {
            $discountValue = (float) $coupon['discount_amount'];
        }

        if ($discountValue > $totalPrice) {
            $discountValue = $totalPrice;
        }

        $voucherData = [
            'code' => $coupon['code'],
            'discount_amount' => $discountValue,
            'discount_type' => $coupon['discount_type'],
            'raw_discount' => $coupon['discount_amount'],
        ];

        $finalTotal = max(0, $totalPrice - $bundleDiscount - $discountValue);

        api_json_response(true, 'Áp dụng mã thành công!', [
            'voucher' => $voucherData,
            'discount_amount' => $discountValue,
            'new_total' => $finalTotal,
            'discount_text' => ($coupon['discount_type'] === 'percent') ? ('Giảm ' . $coupon['discount_amount'] . '%') : ('Giảm ' . number_format($coupon['discount_amount']) . 'đ'),
        ]);

    default:
        api_json_response(false, 'Action không hợp lệ.', [], 400);
}
