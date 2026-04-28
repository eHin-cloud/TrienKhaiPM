<?php

namespace App\Repository;

use PDO;

/**
 * ProductRepository
 * Lớp này chịu trách nhiệm toàn bộ các thao tác truy vấn (CRUD) liên quan đến bảng sản phẩm (products).
 * Nó đóng vai trò là lớp trừu tượng hóa (Repository Pattern) giữa logic nghiệp vụ và cơ sở dữ liệu.
 */
class ProductRepository {
    private PDO $db;

    /**
     * Constructor nhận PDO object qua Dependency Injection.
     * @param PDO $db Kết nối cơ sở dữ liệu PDO.
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Tìm kiếm sản phẩm theo ID.
     * Truy vấn JOIN 3 bảng (products, categories, brands) để lấy thông tin đầy đủ
     * và hiển thị tên danh mục/thương hiệu ngay tại đây.
     * @param int $id ID của sản phẩm cần tìm.
     * @return array|false Dữ liệu sản phẩm hoặc false nếu không tìm thấy.
     */
    public function findById(int $id) {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name AS category_name, b.name AS brand_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN brands b ON p.brand_id = b.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách các sản phẩm liên quan (Related Products).
     * Thuật toán: Truy vấn các sản phẩm khác cùng danh mục, loại trừ sản phẩm hiện tại,
     * và giới hạn số lượng theo thứ tự giảm dần của ID (để ưu tiên sản phẩm mới).
     * @param int $categoryId ID của danh mục cha.
     * @param int $excludeId ID của sản phẩm đang xem (để loại trừ).
     * @param int $limit Số lượng sản phẩm tối đa trả về.
     * @return array Danh sách các sản phẩm liên quan.
     */
    public function getRelatedProducts(int $categoryId, int $excludeId, int $limit = 5) {
        $stmt = $this->db->prepare("
            SELECT * FROM products 
            WHERE category_id = ? AND id != ? 
            ORDER BY id DESC LIMIT ?
        ");
        $stmt->bindValue(1, $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(2, $excludeId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách các sản phẩm cùng thương hiệu (Same Brand Products).
     * Tương tự getRelatedProducts, nhưng chỉ lọc theo brand_id.
     * @param int $brandId ID của thương hiệu.
     * @param int $excludeId ID của sản phẩm đang xem.
     * @param int $limit Số lượng sản phẩm tối đa trả về.
     * @return array Danh sách các sản phẩm cùng thương hiệu.
     */
    public function getSameBrandProducts(int $brandId, int $excludeId, int $limit = 5) {
        $stmt = $this->db->prepare("
            SELECT * FROM products 
            WHERE brand_id = ? AND id != ? 
            ORDER BY id DESC LIMIT ?
        ");
        $stmt->bindValue(1, $brandId, PDO::PARAM_INT);
        $stmt->bindValue(2, $excludeId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Đếm tổng số sản phẩm theo bộ lọc (Category, Brand, Keyword, Price Range).
     * @param int $catId ID danh mục.
     * @param int $brandId ID thương hiệu.
     * @param string $keyword Từ khóa tìm kiếm.
     * @param int $minPrice Giá tối thiểu.
     * @param int $maxPrice Giá tối đa.
     * @return int Tổng số sản phẩm khớp với bộ lọc.
     */
    public function countProducts(int $catId, int $brandId, string $keyword, int $minPrice = 0, int $maxPrice = 0) {
        $query = "SELECT COUNT(*) FROM products p WHERE 1=1";
        $params = [];

        if ($catId > 0) {
            $query .= " AND p.category_id = ?";
            $params[] = $catId;
        }
        if ($brandId > 0) {
            $query .= " AND p.brand_id = ?";
            $params[] = $brandId;
        }
        if ($keyword !== '') {
            $query .= " AND p.name LIKE ?";
            $params[] = "%" . $keyword . "%";
        }
        if ($minPrice > 0) {
            $query .= " AND p.price >= ?";
            $params[] = $minPrice;
        }
        if ($maxPrice > 0) {
            $query .= " AND p.price <= ?";
            $params[] = $maxPrice;
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Lấy danh sách sản phẩm được phân trang (Pagination).
     * @param int $catId ID danh mục.
     * @param int $brandId ID thương hiệu.
     * @param string $keyword Từ khóa tìm kiếm.
     * @param int $minPrice Giá tối thiểu.
     * @param int $maxPrice Giá tối đa.
     * @param int $limit Số lượng sản phẩm trên mỗi trang.
     * @param int $offset Số lượng sản phẩm cần bỏ qua.
     * @return array Danh sách sản phẩm đã được lọc và phân trang.
     */
    public function getPaginatedProducts(int $catId, int $brandId, string $keyword, int $minPrice, int $maxPrice, int $limit, int $offset) {
        $query = "SELECT p.*, b.name as brand_name FROM products p LEFT JOIN brands b ON p.brand_id = b.id WHERE 1=1";
        $params = [];

        if ($catId > 0) {
            $query .= " AND p.category_id = ?";
            $params[] = $catId;
        }
        if ($brandId > 0) {
            $query .= " AND p.brand_id = ?";
            $params[] = $brandId;
        }
        if ($keyword !== '') {
            $query .= " AND p.name LIKE ?";
            $params[] = "%" . $keyword . "%";
        }
        if ($minPrice > 0) {
            $query .= " AND p.price >= ?";
            $params[] = $minPrice;
        }
        if ($maxPrice > 0) {
            $query .= " AND p.price <= ?";
            $params[] = $maxPrice;
        }

        // Áp dụng phân trang
        $query .= " ORDER BY p.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
