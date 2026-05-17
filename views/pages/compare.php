<?php
/**
 * ============================================================
 * COMPARE.PHP - TRANG SO SÁNH SẢN PHẨM
 * ============================================================
 */

use App\Repository\ProductRepository;
use App\Service\ProductService;

$productRepo = new ProductRepository($db);
$productService = new ProductService($productRepo);

$compare_ids = $_SESSION['compare_list'] ?? [];
$products = [];

if (!empty($compare_ids)) {
    $products = $productService->getRecentlyViewedProducts($compare_ids);
}

require_once __DIR__ . '/../partials/header.php';
?>

<div class="container mx-auto px-4 py-8 min-h-screen">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
                <i class="fa-solid fa-right-left text-primary"></i>
                So sánh sản phẩm
            </h1>
            <p class="text-gray-500 mt-2">So sánh chi tiết cấu hình, tính năng và giá bán giữa các sản phẩm.</p>
        </div>
        <?php if (!empty($products)): ?>
            <div class="flex items-center gap-3">
                <button onclick="toggleDifferences()" id="btn-diff" class="text-sm font-bold text-primary hover:bg-blue-50 flex items-center gap-2 px-4 py-2 border border-primary rounded-lg transition shadow-sm">
                    <i class="fa-solid fa-code-compare"></i> So sánh khác biệt
                </button>
                <button onclick="clearCompare()" class="text-sm font-bold text-red-600 hover:text-red-700 flex items-center gap-2 px-4 py-2 bg-red-50 rounded-lg transition">
                    <i class="fa-solid fa-trash-can"></i> Xóa tất cả
                </button>
            </div>
        <?php endif; ?>

    </div>

    <?php if (empty($products)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
            <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-layer-group text-4xl text-gray-300"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Danh sách so sánh đang trống</h2>
            <p class="text-gray-500 mb-8">Hãy thêm ít nhất 2 sản phẩm để bắt đầu so sánh chi tiết.</p>
            <a href="index.php" class="inline-flex items-center gap-2 bg-primary text-white font-bold px-8 py-3 rounded-xl hover:bg-blue-800 transition shadow-lg shadow-blue-200">
                <i class="fa-solid fa-plus"></i> Chọn sản phẩm ngay
            </a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="p-6 bg-gray-50/50 w-64 text-left font-bold text-gray-400 text-xs uppercase tracking-widest">Tiêu chí</th>
                            <?php foreach ($products as $p): ?>
                                <th class="p-6 min-w-[300px] relative group border-l border-gray-100">
                                    <button onclick="removeFromCompare(<?= $p['id'] ?>)" class="absolute top-4 right-4 z-10 opacity-0 group-hover:opacity-100 transition-opacity w-8 h-8 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition shadow-lg">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                    <div class="flex flex-col items-center text-center">
                                        <div class="w-40 h-40 mb-4 hover:scale-105 transition-transform duration-500">
                                            <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-contain">
                                        </div>
                                        <h3 class="text-sm font-extrabold text-gray-800 line-clamp-2 h-10 mb-2"><?= htmlspecialchars($p['name']) ?></h3>
                                        <div class="flex flex-col items-center gap-1">
                                            <p class="text-xl font-black text-red-600"><?= number_format($p['price']) ?>đ</p>
                                            <?php if ($p['old_price']): ?>
                                                <p class="text-xs text-gray-400 line-through"><?= number_format($p['old_price']) ?>đ</p>
                                            <?php endif; ?>
                                        </div>
                                        <button onclick="addToCartAjax(<?= $p['id'] ?>)" class="mt-4 bg-primary text-white text-xs font-bold px-6 py-2.5 rounded-full hover:bg-blue-800 transition shadow-md">
                                            Thêm vào giỏ
                                        </button>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>

                        <!-- THÔNG TIN CHUNG -->
                        <tr class="bg-blue-50/50 section-header">
                            <td colspan="<?= count($products) + 1 ?>" class="px-6 py-3 text-[12px] font-bold text-blue-700 uppercase tracking-wider">Thông tin chung</td>
                        </tr>
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition compare-row">
                            <td class="p-4 pl-6 text-sm font-bold text-gray-700 bg-gray-50/50 w-64">Thương hiệu</td>
                            <?php foreach ($products as $p): ?>
                                <td class="p-4 text-sm text-gray-600 compare-value">
                                    <?= htmlspecialchars($p['brand_name']) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition compare-row bg-gray-50/30">
                            <td class="p-4 pl-6 text-sm font-bold text-gray-700 bg-gray-50/50 w-64">Đánh giá</td>
                            <?php foreach ($products as $p): ?>
                                <td class="p-4 text-sm text-gray-600 compare-value">
                                    <div class="flex items-center gap-1">
                                        <i class="fa-solid fa-star text-yellow-400 text-xs"></i>
                                        <span class="font-bold"><?= $p['rate_star'] ?></span>
                                        <span class="text-xs text-gray-400">(<?= $p['total_reviews'] ?> đánh giá)</span>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition compare-row">
                            <td class="p-4 pl-6 text-sm font-bold text-gray-700 bg-gray-50/50 w-64">Bảo hành</td>
                            <?php foreach ($products as $p): ?>
                                <td class="p-4 text-sm text-gray-600 compare-value">
                                    <?= $p['warranty_months'] ?? 12 ?> tháng
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- THÔNG SỐ KỸ THUẬT -->
                        <tr class="bg-blue-50/50 section-header">
                            <td colspan="<?= count($products) + 1 ?>" class="px-6 py-3 text-[12px] font-bold text-blue-700 uppercase tracking-wider">Thông số kỹ thuật</td>
                        </tr>
                        <?php
                        // Logic phân tích thông số kỹ thuật (đã giữ lại từ bước trước)
                        $specs_by_product = [];
                        $all_spec_keys = [];

                        foreach ($products as $p) {
                            $raw_specs = $p['specifications'];
                            $decoded = json_decode($raw_specs, true);
                            $items = [];

                            if ($decoded && is_array($decoded)) {
                                $items = $decoded;
                            } else {
                                // 1. Thử parse theo định dạng Bảng (Table - Thường dùng cho sản phẩm LG)
                                preg_match_all('/<tr>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<\/tr>/is', $raw_specs, $table_matches);
                                if (!empty($table_matches[1])) {
                                    foreach ($table_matches[1] as $idx => $label) {
                                        $k = trim(strip_tags($label));
                                        $v = trim(strip_tags($table_matches[2][$idx]));
                                        if ($k) $items[$k] = $v;
                                    }
                                } 
                                
                                // 2. Nếu không phải bảng, thử parse theo định dạng Danh sách (List - <li>)
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

                                // 3. Cuối cùng nếu vẫn trống thì dùng fallback
                                if (empty($items) && trim($raw_specs)) {
                                    $items['Chi tiết kỹ thuật'] = strip_tags($raw_specs);
                                }
                            }

                            $specs_by_product[$p['id']] = $items;
                            foreach ($items as $k => $v) {
                                if (!in_array($k, $all_spec_keys)) $all_spec_keys[] = $k;
                            }
                        }
                        
                        $rowIndex = 0;
                        foreach ($all_spec_keys as $spec_key): 
                            $rowIndex++;
                            $rowBg = ($rowIndex % 2 == 0) ? '' : 'bg-gray-50/30';
                        ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition compare-row <?= $rowBg ?>">
                                <td class="p-4 pl-6 text-sm font-bold text-gray-700 bg-gray-50/50 w-64 align-top"><?= htmlspecialchars($spec_key) ?></td>
                                <?php foreach ($products as $p): ?>
                                    <td class="p-4 text-sm text-gray-600 leading-relaxed align-top compare-value">
                                        <?= isset($specs_by_product[$p['id']][$spec_key]) ? nl2br(htmlspecialchars($specs_by_product[$p['id']][$spec_key])) : '<span class="text-gray-300">—</span>' ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function removeFromCompare(id) {
        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('product_id', id);
        formData.append('csrf_token', '<?= get_csrf_token() ?>');

        fetch(getApiUrl('ajax_compare.php'), {
            method: 'POST',
            body: formData
        }).then(res => res.json()).then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    function clearCompare() {
        const formData = new FormData();
        formData.append('action', 'clear');
        formData.append('csrf_token', '<?= get_csrf_token() ?>');

        fetch(getApiUrl('ajax_compare.php'), {
            method: 'POST',
            body: formData
        }).then(res => res.json()).then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    /**
     * SO SÁNH KHÁC BIỆT
     * Lọc và ẩn đi những hàng mà giá trị của tất cả sản phẩm giống hệt nhau.
     */
    let showOnlyDiff = false;
    function toggleDifferences() {
        showOnlyDiff = !showOnlyDiff;
        const btn = document.getElementById('btn-diff');
        const rows = document.querySelectorAll('tbody tr:not(.section-header)');
        
        if (showOnlyDiff) {
            btn.classList.add('bg-primary', 'text-white');
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Hiển thị tất cả';
            
            rows.forEach(row => {
                if (row.classList.contains('is-same')) {
                    row.style.display = 'none';
                }
            });
        } else {
            btn.classList.remove('bg-primary', 'text-white');
            btn.innerHTML = '<i class="fa-solid fa-code-compare"></i> So sánh khác biệt';
            
            rows.forEach(row => {
                row.style.display = '';
            });
        }
    }

    /**
     * TỰ ĐỘNG NHẬN DIỆN KHÁC BIỆT
     * Quét tất cả các hàng và đánh dấu những hàng có sự khác biệt.
     */
    function checkDifferences() {
        const rows = document.querySelectorAll('tbody tr:not(.section-header)');
        rows.forEach(row => {
            const values = Array.from(row.querySelectorAll('.compare-value, td:not(:first-child)'))
                               .map(td => td.innerText.trim().toLowerCase());
            
            if (values.length > 1) {
                const isSame = values.every(v => v === values[0]);
                if (!isSame) {
                    row.classList.add('row-diff-highlight');
                    row.classList.remove('is-same');
                } else {
                    row.classList.add('is-same');
                    row.classList.remove('row-diff-highlight');
                }
            }
        });
    }

    // Chạy kiểm tra ngay khi trang tải xong
    document.addEventListener('DOMContentLoaded', checkDifferences);
</script>



<style>
    /* CSS Tùy chỉnh cho bảng so sánh giống hình mẫu */
    table {
        border-spacing: 0;
        border-collapse: separate;
    }
    table thead th {
        position: sticky;
        top: 0;
        z-index: 20;
        background: white;
    }
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    /* Đảm bảo cột tiêu chí không bị ẩn khi cuộn ngang */
    td:first-child {
        position: sticky;
        left: 0;
        z-index: 10;
        border-right: 1px solid #f3f4f6;
    }
    /* Highlight cho dòng có sự khác biệt */
    .row-diff-highlight {
        background-color: #f0f7ff !important; /* blue-50 cực nhẹ */
    }
    .row-diff-highlight td:first-child {
        background-color: #e0efff !important; /* blue-100 */
        color: #1e40af !important; /* blue-800 */
        border-right: 2px solid #3b82f6;
    }
    .compare-row.row-diff-highlight:hover {
        background-color: #e0efff !important;
    }

</style>



<?php require_once __DIR__ . '/../partials/footer.php'; ?>
