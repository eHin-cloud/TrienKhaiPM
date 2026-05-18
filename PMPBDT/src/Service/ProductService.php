<?php

namespace App\Service;

use App\Repository\ProductRepository;

/**
 * ProductService
 * Lớp này đóng vai trò là lớp dịch vụ (Service Layer), chứa logic nghiệp vụ phức tạp
 * liên quan đến sản phẩm. Nó sử dụng ProductRepository để thực hiện các thao tác truy vấn
 * và tổng hợp dữ liệu trước khi trả về cho tầng View/Controller.
 */
class ProductService {
    private ProductRepository $productRepo;

    /**
     * Constructor nhận ProductRepository qua Dependency Injection.
     * @param ProductRepository $productRepo Repository quản lý dữ liệu sản phẩm.
     */
    public function __construct(ProductRepository $productRepo) {
        $this->productRepo = $productRepo;
    }

    /**
     * Lấy chi tiết sản phẩm theo ID.
     * @param int $id ID của sản phẩm.
     * @return array|false Dữ liệu chi tiết sản phẩm.
     */
    public function getProductDetails(int $id) {
        return $this->productRepo->findById($id);
    }

    /**
     * Lấy danh sách các sản phẩm liên quan.
     * @param int $categoryId ID danh mục.
     * @param int $excludeId ID sản phẩm đang xem.
     * @return array Danh sách sản phẩm liên quan.
     */
    public function getRelatedProducts(int $categoryId, int $excludeId) {
        return $this->productRepo->getRelatedProducts($categoryId, $excludeId);
    }

    /**
     * Lấy sản phẩm tương tự (Alternative).
     * @param int $productId
     * @param int $limit
     * @param float $priceTolerance
     * @return array
     */
    public function getAlternativeProducts(int $productId, int $limit = 4, float $priceTolerance = 0.15) {
        return $this->productRepo->getAlternativeProducts($productId, $limit, $priceTolerance);
    }

    /**
     * Lấy sản phẩm mua kèm (Cross-sell).
     * @param int $productId
     * @param int $limit
     * @return array
     */
    public function getCrossSellProducts(int $productId, int $limit = 4) {
        return $this->productRepo->getCrossSellProducts($productId, $limit);
    }

    /**
     * Lấy sản phẩm từ danh sách ID đã xem.
     * @param array $productIds
     * @return array
     */
    public function getRecentlyViewedProducts(array $productIds): array {
        return $this->productRepo->getProductsByIds($productIds);
    }

    /**
     * Lưu lịch sử xem sản phẩm (cho user đăng nhập).
     */
    public function trackProductView(int $userId, int $productId) {
        return $this->productRepo->saveRecentlyViewedProduct($userId, $productId);
    }

    /**
     * Lấy lịch sử xem sản phẩm (từ Database).
     */
    public function getUserRecentlyViewed(int $userId, int $limit = 6): array {
        return $this->productRepo->getUserRecentlyViewedProducts($userId, $limit);
    }

    public function getHomeSuggestedProducts(int $catId = 0, int $brandId = 0, int $limit = 4, int $offset = 0) {
        return $this->productRepo->getHomeSuggestedProducts($catId, $brandId, $limit, $offset);
    }

    public function countProducts(int $catId, int $brandId, string $keyword, int $minPrice = 0, int $maxPrice = 0) {
        return $this->productRepo->countProducts($catId, $brandId, $keyword, $minPrice, $maxPrice);
    }

    /**
     * Lấy danh sách sản phẩm được phân trang cho trang chủ.
     * @param int $catId ID danh mục lọc.
     * @param int $brandId ID thương hiệu lọc.
     * @param string $keyword Từ khóa tìm kiếm.
     * @param int $minPrice Giá tối thiểu.
     * @param int $maxPrice Giá tối đa.
     * @param int $page Trang hiện tại.
     * @param int $limit Số lượng sản phẩm trên mỗi trang.
     * @return array Chứa danh sách sản phẩm, tổng số sản phẩm, tổng số trang và trang hiện tại.
     */
    public function getPaginatedHomeProducts(int $catId, int $brandId, string $keyword, int $minPrice, int $maxPrice, int $page, int $limit) {
        // Tính toán offset: (Trang hiện tại - 1) * Giới hạn
        $offset = ($page - 1) * $limit;
        
        // 1. Đếm tổng số sản phẩm khớp bộ lọc
        $totalProducts = $this->productRepo->countProducts($catId, $brandId, $keyword, $minPrice, $maxPrice);
        // 2. Tính tổng số trang (sử dụng hàm ceil để làm tròn lên)
        $totalPages = ceil($totalProducts / $limit);
        
        // 3. Lấy danh sách sản phẩm thực tế với phân trang
        $products = $this->productRepo->getPaginatedProducts($catId, $brandId, $keyword, $minPrice, $maxPrice, $limit, $offset);

        return [
            'products' => $products,
            'total_products' => $totalProducts,
            'total_pages' => $totalPages,
            'current_page' => $page
        ];
    }
}
