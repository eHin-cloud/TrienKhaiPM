<?php
/**
 * ============================================================
 * API SO SÁNH SẢN PHẨM DÀNH CHO MOBILE APP & WEB
 * ============================================================
 */

ob_start();
error_reporting(0);

require_once __DIR__ . '/../../vendor/autoload.php';

// Nạp biến môi trường từ file .env
if (class_exists('\App\Support\Env')) {
    \App\Support\Env::load(__DIR__ . '/../../.env');
}

require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/api.php';
require_once __DIR__ . '/../../core/lang.php';

use App\Repository\ProductRepository;
use App\Service\ProductService;

$productRepo = new ProductRepository($db);
$productService = new ProductService($productRepo);

// Lấy danh sách ID sản phẩm từ URL (ví dụ: ?ids=1,2,3)
$ids_str = trim((string)($_GET['ids'] ?? ''));

if (empty($ids_str)) {
    api_json_response(false, 'Vui lòng cung cấp danh sách ID sản phẩm để so sánh!', [
        'products' => [],
        'specifications' => []
    ], 400);
}

// Chuyển đổi chuỗi ID sang mảng số nguyên sạch sẽ
$compare_ids = array_filter(array_map('intval', explode(',', $ids_str)));

if (empty($compare_ids)) {
    api_json_response(false, 'Danh sách ID sản phẩm không hợp lệ!', [
        'products' => [],
        'specifications' => []
    ], 400);
}

if (count($compare_ids) > 3) {
    api_json_response(false, 'Bạn chỉ có thể so sánh tối đa 3 sản phẩm cùng lúc!', [
        'products' => [],
        'specifications' => []
    ], 400);
}

try {
    // 1. Lấy thông tin cơ bản của các sản phẩm cần so sánh
    $products = $productService->getRecentlyViewedProducts($compare_ids);

    if (empty($products)) {
        api_json_response(true, 'Không tìm thấy sản phẩm nào khớp với ID cung cấp.', [
            'products' => [],
            'specifications' => []
        ]);
    }

    // 2. Phân tích các thông số kỹ thuật (Specifications) của từng sản phẩm
    $specs_by_product = [];
    $all_spec_keys = [];

    foreach ($products as $p) {
        $raw_specs = $p['specifications'] ?? '';
        $decoded = json_decode($raw_specs, true);
        $items = [];

        if ($decoded && is_array($decoded)) {
            $items = $decoded;
        } else {
            // Thử parse từ HTML Table
            preg_match_all('/<tr>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<\/tr>/is', $raw_specs, $table_matches);
            if (!empty($table_matches[1])) {
                foreach ($table_matches[1] as $idx => $label) {
                    $k = trim(strip_tags($label));
                    $v = trim(strip_tags($table_matches[2][$idx]));
                    if ($k) $items[$k] = $v;
                }
            } 
            
            // Thử parse từ danh sách <li>
            if (empty($items)) {
                preg_match_all('/<li>(.*?)<\/li>/i', $raw_specs, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $li_content) {
                        $parts = explode(':', $li_content, 2);
                        if (count($parts) == 2) {
                            $k = trim(strip_tags($parts[0]));
                            $v = trim(strip_tags($parts[1]));
                            $items[$k] = $v;
                        } else {
                            $items['Tính năng khác'] = ($items['Tính năng khác'] ?? '') . strip_tags($li_content) . '; ';
                        }
                    }
                }
            }

            // Fallback cuối cùng
            if (empty($items) && trim($raw_specs)) {
                $items['Chi tiết kỹ thuật'] = strip_tags($raw_specs);
            }
        }

        $specs_by_product[$p['id']] = $items;
        foreach ($items as $k => $v) {
            if (!in_array($k, $all_spec_keys)) {
                $all_spec_keys[] = $k;
            }
        }
    }

    // 3. Đóng gói dữ liệu so sánh chi tiết
    $specifications_rows = [];
    
    // Thêm các tiêu chí so sánh chung trước
    // A. Thương hiệu
    $brand_values = [];
    foreach ($products as $p) {
        $brand_values[(string)$p['id']] = $p['brand_name'] ?? 'Không rõ';
    }
    $specifications_rows[] = [
        'key' => 'Thương hiệu',
        'is_general' => true,
        'values' => $brand_values
    ];

    // B. Đánh giá sao
    $rate_values = [];
    foreach ($products as $p) {
        $rate_values[(string)$p['id']] = ($p['rate_star'] ?? '0') . ' ★ (' . ($p['total_reviews'] ?? '0') . ' đánh giá)';
    }
    $specifications_rows[] = [
        'key' => 'Đánh giá',
        'is_general' => true,
        'values' => $rate_values
    ];

    // C. Bảo hành
    $warranty_values = [];
    foreach ($products as $p) {
        $warranty_values[(string)$p['id']] = ($p['warranty_months'] ?? 12) . ' tháng';
    }
    $specifications_rows[] = [
        'key' => 'Thời gian bảo hành',
        'is_general' => true,
        'values' => $warranty_values
    ];

    // Thêm các thông số kỹ thuật chi tiết
    foreach ($all_spec_keys as $spec_key) {
        $values = [];
        foreach ($products as $p) {
            $val = $specs_by_product[$p['id']][$spec_key] ?? null;
            $values[(string)$p['id']] = $val !== null ? (string)$val : '—';
        }
        $specifications_rows[] = [
            'key' => $spec_key,
            'is_general' => false,
            'values' => $values
        ];
    }

    // 4. Trả về kết quả JSON chuẩn
    api_json_response(true, 'Lấy dữ liệu so sánh sản phẩm thành công!', [
        'products' => array_map(function($p) {
            return [
                'id' => (int)$p['id'],
                'name' => (string)$p['name'],
                'price' => (int)$p['price'],
                'old_price' => $p['old_price'] !== null ? (int)$p['old_price'] : null,
                'image' => (string)$p['image'],
                'category_id' => (int)$p['category_id']
            ];
        }, $products),
        'specifications' => $specifications_rows
    ]);

} catch (Throwable $e) {
    api_json_response(false, 'Đã xảy ra lỗi máy chủ khi lấy dữ liệu so sánh: ' . $e->getMessage(), [
        'products' => [],
        'specifications' => []
    ], 500);
}
