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
                <?= __("compare_title") ?>
            </h1>
            <p class="text-gray-500 mt-2"><?= __("compare_desc") ?></p>
        </div>
        <?php if (!empty($products)): ?>
            <button onclick="clearCompare()" class="text-sm font-bold text-red-600 hover:text-red-700 flex items-center gap-2 px-4 py-2 bg-red-50 rounded-lg transition">
                <i class="fa-solid fa-trash-can"></i> <?= __("clear_all") ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($products)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
            <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-layer-group text-4xl text-gray-300"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-800 mb-2"><?= __("compare_empty") ?></h2>
            <p class="text-gray-500 mb-8"><?= __("compare_empty_desc") ?></p>
            <a href="index.php" class="inline-flex items-center gap-2 bg-primary text-white font-bold px-8 py-3 rounded-xl hover:bg-blue-800 transition shadow-lg shadow-blue-200">
                <i class="fa-solid fa-plus"></i> <?= __("choose_product_now") ?>
            </a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="p-6 bg-gray-50/50 w-64 text-left font-bold text-gray-400 text-xs uppercase tracking-widest"><?= __("criteria") ?></th>
                            <?php foreach ($products as $p): ?>
                                <th class="p-6 min-w-[300px] relative group border-l border-gray-100">
                                    <button onclick="removeFromCompare(<?= $p['id'] ?>)" class="absolute top-4 right-4 z-10 opacity-0 group-hover:opacity-100 transition-opacity w-8 h-8 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition shadow-lg">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                    <div class="flex flex-col items-center text-center">
                                        <div class="w-40 h-40 mb-4 hover:scale-105 transition-transform duration-500">
                                            <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars(getCurrentLang() === 'en' ? translate_text($p['name'], 'prod_name_' . $p['id']) : $p['name']) ?>" class="w-full h-full object-contain">
                                        </div>
                                        <h3 class="text-sm font-extrabold text-gray-800 line-clamp-2 h-10 mb-2"><?= htmlspecialchars(getCurrentLang() === 'en' ? translate_text($p['name'], 'prod_name_' . $p['id']) : $p['name']) ?></h3>
                                        <div class="flex flex-col items-center gap-1">
                                            <p class="text-xl font-black text-red-600"><?= number_format($p['price']) ?>đ</p>
                                            <?php if ($p['old_price']): ?>
                                                <p class="text-xs text-gray-400 line-through"><?= number_format($p['old_price']) ?>đ</p>
                                            <?php endif; ?>
                                        </div>
                                        <button onclick="addToCartAjax(<?= $p['id'] ?>)" class="mt-4 bg-primary text-white text-xs font-bold px-6 py-2.5 rounded-full hover:bg-blue-800 transition shadow-md">
                                            <?= __("add_to_cart") ?>
                                        </button>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- THÔNG TIN CHUNG -->
                        <tr class="bg-gray-50/30">
                            <td colspan="<?= count($products) + 1 ?>" class="px-6 py-3 text-[11px] font-black text-gray-400 uppercase tracking-widest"><?= __("general_info") ?></td>
                        </tr>
                        <tr class="border-b border-gray-50 hover:bg-blue-50/30 transition">
                            <td class="p-6 text-sm font-bold text-gray-600"><?= __("brand_prefix") ?></td>
                            <?php foreach ($products as $p): ?>
                                <td class="p-6 text-center border-l border-gray-50">
                                    <span class="px-3 py-1 bg-gray-100 rounded text-xs font-bold text-gray-700 uppercase"><?= htmlspecialchars($p['brand_name']) ?></span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="border-b border-gray-50 hover:bg-blue-50/30 transition">
                            <td class="p-6 text-sm font-bold text-gray-600"><?= __("rating") ?></td>
                            <?php foreach ($products as $p): ?>
                                <td class="p-6 text-center border-l border-gray-50">
                                    <div class="flex items-center justify-center gap-1">
                                        <i class="fa-solid fa-star text-yellow-400 text-sm"></i>
                                        <span class="font-bold text-gray-800"><?= $p['rate_star'] ?></span>
                                        <span class="text-xs text-gray-400">(<?= $p['total_reviews'] ?>)</span>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="border-b border-gray-50 hover:bg-blue-50/30 transition">
                            <td class="p-6 text-sm font-bold text-gray-600"><?= __("warranty") ?></td>
                            <?php foreach ($products as $p): ?>
                                <td class="p-6 text-center border-l border-gray-50">
                                    <span class="text-sm text-gray-700 font-medium"><?= ($p['warranty_months'] ?? 12) . ' ' . __("months_suffix") ?></span>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- THÔNG SỐ KỸ THUẬT -->
                        <tr class="bg-gray-50/30">
                            <td colspan="<?= count($products) + 1 ?>" class="px-6 py-3 text-[11px] font-black text-gray-400 uppercase tracking-widest"><?= __("detailed_config") ?></td>
                        </tr>
                        <?php
                        // Phân tích specifications để lấy các tiêu chí
                        $all_specs = [];
                        foreach ($products as $p) {
                            $specs = json_decode($p['specifications'], true);
                            if ($specs && is_array($specs)) {
                                foreach ($specs as $key => $val) {
                                    $all_specs[$key] = true;
                                }
                            } else {
                                // Fallback nếu specs là text (html)
                                $all_specs[__("technical_details")] = true;
                            }
                        }
                        
                        foreach (array_keys($all_specs) as $spec_key): ?>
                            <tr class="border-b border-gray-50 hover:bg-blue-50/30 transition">
                                <td class="p-6 text-sm font-bold text-gray-600"><?= htmlspecialchars(getCurrentLang() === 'en' ? translate_html_content($spec_key, 'spec_key_' . md5($spec_key)) : $spec_key) ?></td>
                                <?php foreach ($products as $p): ?>
                                    <td class="p-6 text-center border-l border-gray-50 text-sm text-gray-700 leading-relaxed">
                                        <?php
                                        $specs = json_decode($p['specifications'], true);
                                        if ($specs && isset($specs[$spec_key])) {
                                            $val = $specs[$spec_key];
                                            echo htmlspecialchars(getCurrentLang() === 'en' ? translate_html_content($val, 'spec_val_' . md5($val)) : $val);
                                        } elseif ($spec_key === __("technical_details")) {
                                            echo '<div class="text-xs max-h-32 overflow-y-auto text-left">' . translate_html_content($p['specifications'], 'prod_specs_' . $p['id']) . '</div>';
                                        } else {
                                            echo '<span class="text-gray-300">—</span>';
                                        }
                                        ?>
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
</script>

<style>
    /* CSS Tùy chỉnh cho bảng so sánh */
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
</style>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
