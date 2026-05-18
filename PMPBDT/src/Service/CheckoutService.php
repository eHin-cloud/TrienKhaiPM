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

    public function calculateBundleDiscount(array $cartItems): array {
        $bundleDiscount = 0.0;
        $bundleMessages = [];
        $productIds = array_column($cartItems, 'product_id');
        if (empty($productIds)) {
            return ['discount' => 0.0, 'message' => ''];
        }

        try {
            $stmtCheck = $this->db->query("SHOW TABLES LIKE 'product_cross_sell'");
            if ($stmtCheck && $stmtCheck->rowCount() > 0) {
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                $sql = "SELECT cs.*, p.price as accessory_price, p.name as accessory_name
                        FROM product_cross_sell cs
                        JOIN products p ON p.id = cs.accessory_product_id
                        WHERE cs.product_id IN ($placeholders) AND cs.accessory_product_id IN ($placeholders)";
                $stmt = $this->db->prepare($sql);
                $params = array_merge($productIds, $productIds);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $addedKeys = [];
                foreach ($rows as $row) {
                    $productKey = $row['product_id'] . '_' . $row['accessory_product_id'];
                    if (isset($addedKeys[$productKey])) {
                        continue;
                    }
                    $addedKeys[$productKey] = true;

                    $discountValue = 0.0;
                    if (isset($row['discount_percent']) && $row['discount_percent'] > 0) {
                        $discountValue = $row['accessory_price'] * ($row['discount_percent'] / 100);
                        $bundleMessages[] = "Mua kèm {$row['accessory_name']} giảm {$row['discount_percent']}%";
                    } elseif (isset($row['discount_amount']) && $row['discount_amount'] > 0) {
                        $discountValue = $row['discount_amount'];
                        $bundleMessages[] = "Mua kèm {$row['accessory_name']} giảm " . number_format($row['discount_amount']) . "đ";
                    }

                    $bundleDiscount += max(0, $discountValue);
                }
            }
        } catch (Exception $e) {
            // Nếu không có bảng hoặc cấu trúc khác, bỏ qua bundle
        }

        return [
            'discount' => round($bundleDiscount, 2),
            'message' => implode(' • ', array_unique($bundleMessages))
        ];
    }

    public function calculateFinalPrice(array $cartItems, string $couponCode) {
        $subTotal = array_sum(array_map(function($item) { return $item['price'] * $item['quantity']; }, $cartItems));
        $bundleData = $this->calculateBundleDiscount($cartItems);
        $bundleDiscount = $bundleData['discount'];
        $discount = 0.0;
        $message = '';

        if (!empty($couponCode)) {
            $couponResult = $this->validateCouponCode($couponCode, $cartItems);
            if ($couponResult['isValid']) {
                $discount = $couponResult['discount'];
                $message = "Voucher: " . $couponResult['message'] . "\n";
            } else {
                $message = "(Voucher không khả dụng)";
            }
        }

        if ($bundleDiscount > 0) {
            $discount += $bundleDiscount;
            $message .= "Bundle: " . $bundleData['message'] . "\n";
        }

        $finalTotal = $subTotal - $discount;
        return [
            'subTotal' => round($subTotal, 2),
            'discount' => round($discount, 2),
            'bundle_discount' => round($bundleDiscount, 2),
            'finalTotal' => max(0.0, round($finalTotal, 2)),
            'message' => trim($message)
        ];
    }

    public function createOrderFromCart(int $userId, array $cartItems, string $fullname, string $phone, string $address, string $note, string $paymentMethod, string $voucherCode, float $discountAmount, float $finalTotal) {
        try {
            // Bao bọc toàn bộ hệ thống lưu trữ bằng Transaction
            $this->db->beginTransaction();

            // CHỐNG SPAM TRỤC LỢI MÃ Ở TẦNG CORE:
            if (!empty($voucherCode) && (str_starts_with(strtoupper($voucherCode), 'GUEST') || str_starts_with(strtoupper($voucherCode), 'NEWS'))) {
                $stmt_spam = $this->db->prepare("SELECT id FROM orders WHERE user_id = ? AND (voucher_code LIKE 'GUEST%' OR voucher_code LIKE 'NEWS%') AND status != 'cancelled'");
                $stmt_spam->execute([$userId]);
                if ($stmt_spam->fetch()) {
                    throw new Exception("Tài khoản của bạn đã hết lượt sử dụng mã ưu đãi đăng ký mới!");
                }
            }

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

            // 3. Tăng bộ đếm sử dụng (used_count) cho voucher (nếu có)
            if (!empty($voucherCode)) {
                $stmt = $this->db->prepare("UPDATE vouchers SET used_count = used_count + 1 WHERE code = ?");
                $stmt->execute([$voucherCode]);
            }

            // 4. Xoá giỏ hàng an toàn
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
