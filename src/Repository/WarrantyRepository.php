<?php

namespace App\Repository;

use PDO;

/**
 * WarrantyRepository
 * Quản lý các yêu cầu bảo hành (warranties) và truy vấn dữ liệu liên quan.
 */

class WarrantyRepository
{
    private PDO $db;

    /**
     * Constructor nhận PDO instance.
     * @param PDO $db
     */

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Tạo một yêu cầu bảo hành mới.
     * @param int $orderId ID đơn hàng.
     * @param int $productId ID sản phẩm.
     * @param int $userId ID người dùng.
     * @param string $reason Lý do bảo hành.
     * @param string|null $mediaJson Phương tiện đính kèm (nếu có).
     */
    public function addWarrantyRequest(int $orderId, int $productId, int $userId, string $reason, string $mediaJson = null)
    {
        $stmt = $this->db->prepare("INSERT INTO warranties (order_id, product_id, user_id, reason, media) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$orderId, $productId, $userId, $reason, $mediaJson]);
    }

    /**
     * Lấy danh sách yêu cầu bảo hành của người dùng.
     * @param int $userId ID người dùng.
     * @return array Danh sách yêu cầu bảo hành.
     */
    public function getUserWarranties(int $userId)
    {
        $stmt = $this->db->prepare("
            SELECT w.*, p.name as product_name, p.image as product_image 
            FROM warranties w 
            JOIN products p ON w.product_id = p.id 
            WHERE w.user_id = ? 
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy toàn bộ yêu cầu bảo hành (Dành cho Admin), tham chiếu với Products và Users.
     * @return array
     */
    public function getAllWarranties()
    {
        return $this->db->query("
            SELECT w.*, u.fullname, u.phone, p.name as product_name 
            FROM warranties w 
            JOIN users u ON w.user_id = u.id 
            JOIN products p ON w.product_id = p.id 
            ORDER BY w.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
