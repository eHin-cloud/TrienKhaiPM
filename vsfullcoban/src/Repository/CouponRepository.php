<?php

namespace App\Repository;

use PDO;

/**
 * CouponRepository
 * Quản lý truy vấn bảng `coupons` và tính toán voucher.
 */

class CouponRepository {
    private PDO $db;

    /**
     * Constructor nhận PDO instance.
     * @param PDO $db
     */

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * KIỂM TRA VÀ TÍNH TOÁN GIÁ VOUCHER/COUPON
     * @param string $couponCode Mã voucher
     * @return array|false
     */
    public function findValidCoupon(string $couponCode) {
        $stmt = $this->db->prepare("SELECT * FROM coupons WHERE coupon_code = ? AND is_active = 1 AND expiry_date >= NOW() AND usage_limit >= 0");
        $stmt->execute([$couponCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
