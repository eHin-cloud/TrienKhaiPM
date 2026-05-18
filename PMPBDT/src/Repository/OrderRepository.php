<?php

namespace App\Repository;

use App\Database\DatabaseConnection;
use PDO;
use PDOException;

/**
 * OrderRepository
 * Quản lý các thao tác liên quan đến đơn hàng (orders) như tạo, truy vấn chi tiết.
 */
class OrderRepository {
    private PDO $db;

    /**
     * Constructor nhận PDO object qua Dependency Injection.
     * @param PDO $db
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Lấy chi tiết đơn hàng theo ID.
     * @param int $orderId
     * @return array|false
     */
    public function findById(int $orderId) {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * Tạo một bản ghi đơn hàng mới (Đã đồng bộ với cấu trúc database hiện tại)
     * @param array $data
     * @return int|false Trả về ID của đơn hàng vừa tạo, hoặc false nếu lỗi.
     */
    public function createOrder(array $data) {
        // Cấu trúc bảng orders: id, user_id, fullname, phone, address, note, total_price, voucher_code, discount_amount, payment_method, status, created_at, completed_at
        $sql = "INSERT INTO orders (user_id, fullname, phone, address, note, total_price, voucher_code, discount_amount, payment_method, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        try {
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([
                $data['user_id'], 
                $data['fullname'], 
                $data['phone'], 
                $data['address'], 
                $data['note'] ?? '', 
                $data['total_price'], 
                $data['voucher_code'] ?? null, 
                $data['discount_amount'] ?? 0.00, 
                $data['payment_method'] ?? 'cod',
                $data['status'] ?? 'pending'
            ]);
            
            if ($success) {
                return (int) $this->db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Lỗi khi tạo đơn hàng (OrderRepository): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lưu chi tiết các sản phẩm trong đơn hàng
     * @param int $orderId
     * @param array $cartItems
     * @return bool
     */
    public function createOrderDetails(int $orderId, array $cartItems): bool {
        // Cấu trúc bảng order_details: id, order_id, product_id, price, quantity
        $sql = "INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
        try {
            $stmt = $this->db->prepare($sql);
            foreach ($cartItems as $item) {
                $stmt->execute([
                    $orderId, 
                    $item['product_id'], 
                    $item['quantity'], 
                    $item['price']
                ]);
            }
            return true;
        } catch (PDOException $e) {
            error_log("Lỗi khi tạo chi tiết đơn hàng (OrderRepository): " . $e->getMessage());
            return false;
        }
    }
}