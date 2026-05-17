<?php

namespace App\Repository;

use PDO;

/**
 * ReturnRepository
 * Quản lý các yêu cầu trả hàng (returns) và truy vấn dữ liệu liên quan.
 */

class ReturnRepository
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
     * Tạo một yêu cầu trả hàng mới, nếu chưa có.
     * @param int $orderId ID đơn hàng.
     * @param int $userId ID người dùng.
     * @param string $reason Lý do trả hàng.
     * @param string|null $mediaJson File đính kèm dạng JSON (nếu có).
     */
    public function addReturnRequest(int $orderId, int $userId, string $reason, string $mediaJson = null)
    {
        $check = $this->db->prepare("SELECT id FROM returns WHERE order_id = ? AND user_id = ?");
        $check->execute([$orderId, $userId]);
        if (!$check->fetch()) {
            $stmt = $this->db->prepare("INSERT INTO returns (order_id, user_id, reason, media) VALUES (?, ?, ?, ?)");
            $stmt->execute([$orderId, $userId, $reason, $mediaJson]);
        }
    }

    /**
     * Lấy danh sách trả hàng của một người dùng cụ thể.
     * @param int $userId ID người dùng.
     * @return array Danh sách yêu cầu trả hàng.
     */
    public function getUserReturns(int $userId)
    {
        $stmt = $this->db->prepare("SELECT * FROM returns WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy tất cả yêu cầu trả hàng trên hệ thống (dành cho Admin).
     * @return array
     */
    public function getAllReturns()
    {
        return $this->db->query("
            SELECT r.*, u.fullname, u.phone, o.total_price 
            FROM returns r 
            JOIN users u ON r.user_id = u.id 
            JOIN orders o ON r.order_id = o.id 
            ORDER BY r.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
