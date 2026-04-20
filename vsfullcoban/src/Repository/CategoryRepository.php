<?php

namespace App\Repository;

use PDO;

/**
 * CategoryRepository
 * Quản lý truy vấn bảng `categories`.
 */

class CategoryRepository {
    private PDO $db;

    /**
     * Constructor nhận PDO instance.
     * @param PDO $db
     */

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Lấy toàn bộ danh sách danh mục (categories).
     * @return array Danh sách các danh mục.
     */
    public function findAll() {
        $stmt = $this->db->query("SELECT * FROM categories ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
