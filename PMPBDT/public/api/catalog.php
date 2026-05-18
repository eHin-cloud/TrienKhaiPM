<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/lang.php';
require_once __DIR__ . '/../../core/api.php';

use App\Service\ProductService;
use App\Repository\CategoryRepository;
use App\Repository\BrandRepository;
use App\Repository\ProductRepository;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = api_request_data();
$action = $_GET['action'] ?? ($data['action'] ?? 'products');

$productService = new ProductService(new ProductRepository($db));
$categoryRepo = new CategoryRepository($db);
$brandRepo = new BrandRepository($db);

if ($method !== 'GET') {
    api_json_response(false, 'Catalog API chỉ hỗ trợ GET.', [], 405);
}

switch ($action) {
    case 'products':
        $catId = (int)($_GET['cat_id'] ?? 0);
        $brandId = (int)($_GET['brand_id'] ?? 0);
        $keyword = trim((string)($_GET['keyword'] ?? ''));
        $minPrice = (int)($_GET['min_price'] ?? 0);
        $maxPrice = (int)($_GET['max_price'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 12)));

        $paginated = $productService->getPaginatedHomeProducts($catId, $brandId, $keyword, $minPrice, $maxPrice, $page, $limit);
        api_json_response(true, 'Lấy danh sách sản phẩm thành công.', $paginated);

    case 'product-detail':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            api_json_response(false, 'Thiếu ID sản phẩm.', [], 422);
        }

        $product = $productService->getProductDetails($id);
        if (!$product) {
            api_json_response(false, 'Không tìm thấy sản phẩm.', [], 404);
        }

        $related = $productService->getRelatedProducts((int)$product['category_id'], $id, 6);
        $sameBrand = [];
        if (!empty($product['brand_id'])) {
            $sameBrand = $productService->getCrossSellProducts($id, 6);
        }

        api_json_response(true, 'Lấy chi tiết sản phẩm thành công.', [
            'product' => $product,
            'related_products' => $related,
            'cross_sell_products' => $sameBrand,
        ]);

    case 'related':
        $productId = (int)($_GET['product_id'] ?? 0);
        $limit = max(1, min(20, (int)($_GET['limit'] ?? 6)));
        if ($productId <= 0) {
            api_json_response(false, 'Thiếu product_id.', [], 422);
        }

        $product = $productService->getProductDetails($productId);
        if (!$product) {
            api_json_response(false, 'Không tìm thấy sản phẩm.', [], 404);
        }

        api_json_response(true, 'Lấy sản phẩm liên quan thành công.', [
            'items' => $productService->getRelatedProducts((int)$product['category_id'], $productId, $limit),
        ]);

    case 'same-brand':
        $productId = (int)($_GET['product_id'] ?? 0);
        $limit = max(1, min(20, (int)($_GET['limit'] ?? 6)));
        if ($productId <= 0) {
            api_json_response(false, 'Thiếu product_id.', [], 422);
        }

        $product = $productService->getProductDetails($productId);
        if (!$product) {
            api_json_response(false, 'Không tìm thấy sản phẩm.', [], 404);
        }

        api_json_response(true, 'Lấy sản phẩm cùng thương hiệu thành công.', [
            'items' => $productService->getCrossSellProducts($productId, $limit),
        ]);

    case 'suggested':
        $catId = (int)($_GET['cat_id'] ?? 0);
        $brandId = (int)($_GET['brand_id'] ?? 0);
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 12)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));

        api_json_response(true, 'Lấy sản phẩm đề xuất thành công.', [
            'items' => $productService->getHomeSuggestedProducts($catId, $brandId, $limit, $offset),
        ]);

    case 'categories':
        api_json_response(true, 'Lấy danh mục thành công.', [
            'items' => $categoryRepo->findAll(),
        ]);

    case 'brands':
        api_json_response(true, 'Lấy thương hiệu thành công.', [
            'items' => $brandRepo->findAll(),
        ]);

    default:
        api_json_response(false, 'Action không hợp lệ.', [], 400);
}
