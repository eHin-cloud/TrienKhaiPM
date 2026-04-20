<?php

namespace App\Service;

use PDO;
use Exception;
use App\Repository\OrderRepository;
use App\Repository\CartRepository;
use App\Repository\CouponRepository;

class CheckoutService {
    private PDO $db;
    private OrderRepository $orderRepo;
    private CartRepository $cartRepo;
    private CouponRepository $couponRepo;

    public function __construct(PDO $db, OrderRepository $orderRepo, CartRepository $cartRepo, CouponRepository $couponRepo) {
        $this->db = $db;
        $this->orderRepo = $orderRepo;
        $this->cartRepo = $cartRepo;
        $this->couponRepo = $couponRepo;
    }

    public function validateCouponCode(string $couponCode, array $cartItems) {
        $coupon = $this->couponRepo->findValidCoupon($couponCode);
        if (!$coupon) {
            return ['isValid' => false, 'discount' => 0.0, 'message' => 'Mã voucher không hợp lệ hoặc đã hết hạn.'];
        }
        return ['isValid' => true, 'discount' => (float)$coupon['discount_value'], 'message' => 'Voucher hợp lệ!'];
    }

    public function calculateFinalPrice(array $cartItems, string $couponCode) {
        $subTotal = array_sum(array_map(function($item) { return $item['price'] * $item['quantity']; }, $cartItems));
        $discount = 0.0;
        $message = '';

        if (!empty($couponCode)) {
            $couponResult = $this->validateCouponCode($couponCode, $cartItems);
            if ($couponResult['isValid']) {
                $discount = $couponResult['discount'];
                $message = "Giảm giá: " . number_format($discount, 2) . "\n";
            } else {
                $message = "(Voucher không khả dụng)";
            }
        }

        $finalTotal = $subTotal - $discount;
        return [
            'subTotal' => round($subTotal, 2),
            'discount' => round($discount, 2),
            'finalTotal' => max(0.0, round($finalTotal, 2)),
            'message' => $message
        ];
    }

    public function createOrderFromCart(int $userId, array $cartItems, string $fullname, string $phone, string $address, string $note, string $paymentMethod, string $voucherCode, float $discountAmount, float $finalTotal) {
        try {
            // Bao bọc toàn bộ hệ thống lưu trữ bằng Transaction
            $this->db->beginTransaction();

            $orderData = [
                'user_id' => $userId,
                'fullname' => $fullname,
                'phone' => $phone,
                'address' => $address,
                'note' => $note,
                'total_price' => $finalTotal,
                'voucher_code' => $voucherCode,
                'discount_amount' => $discountAmount,
                'payment_method' => $paymentMethod,
                'status' => 'pending'
            ];

            // 1. Tạo đơn hàng thông qua Repository
            $orderId = $this->orderRepo->createOrder($orderData);
            if (!$orderId) {
                throw new Exception("Không thể tạo đơn hàng.");
            }

            // 2. Tạo chi tiết đơn qua Repository
            $success = $this->orderRepo->createOrderDetails($orderId, $cartItems);
            if (!$success) {
                throw new Exception("Lỗi thiết lập chi tiết đơn hàng.");
            }

            // 3. Xoá giỏ hàng an toàn
            $this->cartRepo->clearCart($userId);

            // 4. Nếu toàn bộ trơn tru
            $this->db->commit();
            return [
                'success' => true, 
                'order_id' => $orderId, 
                'message' => "Đơn hàng ID $orderId đã được tạo thành công."
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false, 
                'order_id' => 0, 
                'message' => "Lỗi hệ thống khi đặt hàng: " . $e->getMessage()
            ];
        }
    }
}
