<?php

namespace App\Repository;

use PDO;

/**
 * BrandRepository
 * Quản lý truy vấn bảng `brands`.
 */

class BrandRepository {
    private PDO $db;

    /**
     * Constructor nhận PDO instance.
     * @param PDO $db
     */

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Lấy toàn bộ danh sách thương hiệu (brands).
     * @return array Danh sách các thương hiệu.
     */
    public function findAll() {
        $stmt = $this->db->query("SELECT * FROM brands ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy thương hiệu theo ID.
     * @param int $id ID của thương hiệu.
     * @return array|false Dữ liệu thương hiệu hoặc false nếu không tồn tại.
     */
    public function findById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM brands WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
