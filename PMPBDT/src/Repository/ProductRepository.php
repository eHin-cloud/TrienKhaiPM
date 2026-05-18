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
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
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
            SELECT p.*, b.name as brand_name, c.name as category_name 
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.category_id = ? AND p.id != ? 
            ORDER BY p.id DESC LIMIT ?
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
            SELECT p.*, b.name as brand_name, c.name as category_name 
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.brand_id = ? AND p.id != ? 
            ORDER BY p.id DESC LIMIT ?
        ");
        $stmt->bindValue(1, $brandId, PDO::PARAM_INT);
        $stmt->bindValue(2, $excludeId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy sản phẩm tương tự (Alternative Recommendations).
     * Sử dụng cùng category_id, giới hạn giá trong khoảng ±15%, ưu tiên cùng thương hiệu và rating cao.
     * @param int $productId ID sản phẩm đang xem.
     * @param int $limit Số sản phẩm trả về.
     * @param float $priceTolerance Tỷ lệ giới hạn giá so với giá gốc.
     * @return array
     */
    public function getAlternativeProducts(int $productId, int $limit = 4, float $priceTolerance = 0.15) {
        $product = $this->findById($productId);
        if (!$product) {
            return [];
        }

        $minPrice = max(0, (int)round($product['price'] * (1 - $priceTolerance)));
        $maxPrice = (int)round($product['price'] * (1 + $priceTolerance));

        $stmt = $this->db->prepare("SELECT p.*, b.name as brand_name
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.category_id = ?
              AND p.id != ?
              AND p.price BETWEEN ? AND ?
            ORDER BY
              CASE WHEN p.brand_id = ? THEN 0 ELSE 1 END,
              p.rate_star DESC,
              p.price ASC,
              p.id DESC
            LIMIT ?");

        $stmt->bindValue(1, $product['category_id'], PDO::PARAM_INT);
        $stmt->bindValue(2, $productId, PDO::PARAM_INT);
        $stmt->bindValue(3, $minPrice, PDO::PARAM_INT);
        $stmt->bindValue(4, $maxPrice, PDO::PARAM_INT);
        $stmt->bindValue(5, $product['brand_id'], PDO::PARAM_INT);
        $stmt->bindValue(6, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy sản phẩm mua kèm (Cross-sell Recommendations).
     * Ưu tiên dùng bảng pivot product_cross_sell, nếu chưa có thì fallback sang sản phẩm liên quan cùng danh mục.
     * @param int $productId
     * @param int $limit
     * @return array
     */
    public function getCrossSellProducts(int $productId, int $limit = 4) {
        $product = $this->findById($productId);
        if (!$product) {
            return [];
        }

        $stmtCheck = $this->db->query("SHOW TABLES LIKE 'product_cross_sell'");
        if ($stmtCheck && $stmtCheck->rowCount() > 0) {
            $stmt = $this->db->prepare("SELECT p.*, b.name as brand_name,
                cs.relation_type, cs.discount_percent, cs.discount_amount
                FROM product_cross_sell cs
                JOIN products p ON cs.accessory_product_id = p.id
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE cs.product_id = ?
                ORDER BY cs.priority ASC, p.rate_star DESC, p.id DESC
                LIMIT ?");
            $stmt->bindValue(1, $productId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($result)) {
                return $result;
            }
        }

        // Fallback: nếu chưa có bảng pivot, dùng products cùng category như gợi ý tạm.
        $stmt = $this->db->prepare("SELECT p.*, b.name as brand_name
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.category_id = ?
              AND p.id != ?
            ORDER BY p.rate_star DESC, p.price ASC, p.id DESC
            LIMIT ?");
        $stmt->bindValue(1, $product['category_id'], PDO::PARAM_INT);
        $stmt->bindValue(2, $productId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy sản phẩm theo danh sách ID. Dùng cho Recently Viewed hoặc Wishlist.
     * @param array $ids
     * @return array
     */
    public function getProductsByIds(array $ids): array {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT p.*, b.name as brand_name
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.id IN ($placeholders)
            ORDER BY FIELD(p.id, $placeholders)");

        $params = array_merge($ids, $ids);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy sản phẩm gợi ý cho trang chủ theo bộ lọc.
     * Sử dụng thuật toán trọng số: cùng danh mục/brand, rating cao, giá gần giá tham chiếu.
     * @param int $catId
     * @param int $brandId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getHomeSuggestedProducts(int $catId = 0, int $brandId = 0, int $limit = 4, int $offset = 0): array {
        $refPrice = $this->getReferencePrice($catId, $brandId);
        $query = "SELECT p.*, b.name as brand_name";
        if ($refPrice > 0) {
            $query .= ", ABS(p.price - ?) AS price_diff";
        }
        $query .= " FROM products p LEFT JOIN brands b ON p.brand_id = b.id WHERE 1=1";

        $params = [];
        if ($refPrice > 0) {
            $params[] = $refPrice;
        }
        if ($catId > 0) {
            $query .= " AND p.category_id = ?";
            $params[] = $catId;
        }
        if ($brandId > 0) {
            $query .= " AND p.brand_id = ?";
            $params[] = $brandId;
        }

        $orderParts = [];
        if ($brandId > 0) {
            $orderParts[] = "CASE WHEN p.brand_id = ? THEN 0 ELSE 1 END";
            $params[] = $brandId;
        }
        $orderParts[] = "p.rate_star DESC";
        if ($refPrice > 0) {
            $orderParts[] = "price_diff ASC";
        }
        $orderParts[] = "p.price ASC";
        $orderParts[] = "p.id DESC";

        $query .= " ORDER BY " . implode(', ', $orderParts) . " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy giá tham chiếu của danh mục/brand để sắp xếp gợi ý theo mức giá gần tương tự.
     * @param int $catId
     * @param int $brandId
     * @return int
     */
    private function getReferencePrice(int $catId = 0, int $brandId = 0): int {
        $query = "SELECT AVG(price) AS avg_price FROM products WHERE 1=1";
        $params = [];
        if ($catId > 0) {
            $query .= " AND category_id = ?";
            $params[] = $catId;
        }
        if ($brandId > 0) {
            $query .= " AND brand_id = ?";
            $params[] = $brandId;
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $avgPrice = $stmt->fetchColumn();
        return $avgPrice ? (int)round($avgPrice) : 0;
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
        return (int)$stmt->fetchColumn();
    }

    /**
     * Lấy danh sách sản phẩm phân trang cho trang chủ.
     * @param int $catId ID danh mục.
     * @param int $brandId ID thương hiệu.
     * @param string $keyword Từ khóa tìm kiếm.
     * @param int $minPrice Giá tối thiểu.
     * @param int $maxPrice Giá tối đa.
     * @param int $limit Số lượng sản phẩm/trang.
     * @param int $offset Vị trí bắt đầu.
     * @return array
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

    /**
     * Lưu lịch sử xem sản phẩm của người dùng (Database).
     * @param int $userId
     * @param int $productId
     * @return bool
     */
    public function saveRecentlyViewedProduct(int $userId, int $productId): bool {
        $stmt = $this->db->prepare("
            INSERT INTO user_recently_viewed (user_id, product_id)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE viewed_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$userId, $productId]);
    }

    /**
     * Lấy danh sách sản phẩm vừa xem của người dùng từ CSDL (Database).
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserRecentlyViewedProducts(int $userId, int $limit = 6): array {
        $stmt = $this->db->prepare("
            SELECT p.*, b.name as brand_name
            FROM user_recently_viewed urv
            JOIN products p ON urv.product_id = p.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE urv.user_id = ?
            ORDER BY urv.viewed_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
