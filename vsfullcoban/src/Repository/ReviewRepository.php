<?php

namespace App\Repository;

use PDO;
use Exception;

/**
 * ReviewRepository
 * Quản lý các đánh giá (reviews) của sản phẩm, bao gồm lấy danh sách và thống kê.
 */
class ReviewRepository {
    private PDO $db;

    /**
     * Constructor nhận PDO instance.
     * @param PDO $db
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Lấy danh sách đánh giá của một sản phẩm.
     * @param int $productId ID sản phẩm.
     * @return array Danh sách đánh giá.
     */
    public function getProductReviews(int $productId) {
        try {
            $stmt = $this->db->prepare("
                SELECT r.*, u.fullname 
                FROM reviews r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.product_id = ? 
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$productId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Tính toán thống kê đánh giá sao cho sản phẩm.
     *
     * @algorithm
     * - Bước 1: Tính tổng số đánh giá.
     * - Bước 2: Duyệt từng đánh giá để cộng dồn sao và phân bổ vào tổng số từng mức sao.
     * - Bước 3: Tính số sao trung bình.
     *
     * @param array $reviews Mảng chứa chi tiết đánh giá.
     * @return array Mảng thống kê tổng số sao, trung bình và phân bố.
     */
    public function getReviewStats(array $reviews) {
        $stats = ['total' => count($reviews), 'avg' => 0, 'dist' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]];
        if ($stats['total'] === 0) return $stats;

        $sum = 0;
        foreach ($reviews as $r) {
            $sum += $r['rating'];
            $stats['dist'][$r['rating']]++;
        }
        $stats['avg'] = round($sum / $stats['total'], 1);
        return $stats;
    }
}
