<?php
// Tính % giảm giá nếu có giá gốc
$discount = $p['old_price'] ? round(($p['old_price'] - $p['price']) / $p['old_price'] * 100) : 0;
// Tách tags thành mảng
$tags = array_filter(explode(',', $p['tags'] ?? ''));

// Chuẩn bị JSON data để truyền sang hàm viewProduct() của JS
// Hàm này lưu SP vào localStorage "đã xem" rồi redirect sang product_detail.php
$productJson = htmlspecialchars(json_encode([
    'id' => $p['id'],
    'name' => $p['name'],
    'price' => $p['price'],
    'old_price' => $p['old_price'],
    'discount' => $discount,
    'image' => $p['image'],
    'brand_name' => $p['brand_name'] ?? 'Hãng khác'
]));
?>
<!-- THẺ SẢN PHẨM (onclick -> viewProduct lưu lịch sử đã xem) -->
<div class="product-card p-3 flex flex-col relative cursor-pointer" onclick="viewProduct(<?= $productJson ?>)">
    <!-- Nhãn dán góc trên trái: Trả góp, Tags -->
    <div class="absolute top-3 left-3 flex flex-col gap-1 z-10 items-start">
        <?php if ((isset($p['gift_text']) && strpos($p['gift_text'], 'Trả góp') !== false) || in_array('Trả góp 0%', $tags)): ?>
            <span class="installment-badge"><?= __("installment_0") ?></span>
        <?php endif; ?>
        <?php foreach ($tags as $tag):
            $tag = trim($tag);
            if ($tag != 'Trả góp 0%' && $tag != ''): ?>
                <span class="promo-tag"><?= htmlspecialchars($tag) ?></span>
            <?php endif; 
        endforeach; ?>
    </div>

    <!-- Ảnh sản phẩm -->
    <div class="h-36 md:h-44 mb-3 overflow-hidden flex items-center justify-center p-2 relative group-img">
        <img src="<?= htmlspecialchars($p['image']) ?>" class="max-w-full max-h-full object-contain">
        
        <!-- Nút Xem nhanh (Chỉ hiện khi hover) -->
        <button type="button" 
                onclick="event.stopPropagation(); openQuickView(<?= $productJson ?>)"
                class="btn-quick-view" 
                title="<?= __("quick_view") ?>">
            <i class="fa-solid fa-eye"></i>
        </button>

        <!-- Nút So sánh -->
        <button type="button" 
                onclick="event.stopPropagation(); toggleCompare(<?= $p['id'] ?>, this)"
                class="btn-compare-card" 
                title="<?= __("add_to_compare") ?>">
            <i class="fa-solid fa-right-left"></i>
        </button>

        <!-- Nút Yêu thích -->
        <button type="button" 
                onclick="event.stopPropagation(); toggleWishlistAjax(<?= $p['id'] ?>, this)"
                class="btn-wishlist-card" 
                id="btn-wishlist-<?= $p['id'] ?>"
                title="<?= __("wishlist") ?>">
            <i class="fa-regular fa-heart"></i>
        </button>
    </div>

    <!-- Hãng + Đánh giá sao -->
    <div class="flex justify-between items-center mb-1 text-[11px] md:text-xs">
        <span class="text-gray-500 font-medium uppercase"><?= htmlspecialchars($p['brand_name'] ?? 'Khác') ?></span>
        <div class="flex items-center gap-1">
            <span class="font-bold text-gray-700"><?= number_format($p['rate_star'] ?? 5, 1) ?></span>
            <i class="fa-solid fa-star text-[10px] star-active"></i>
        </div>
    </div>

    <!-- Tên sản phẩm (tối đa 2 dòng) -->
    <h3 class="font-semibold text-xs md:text-sm text-gray-800 line-clamp-2 mb-2 h-8 md:h-10 leading-snug">
        <?= htmlspecialchars($p['name']) ?>
    </h3>

    <!-- Giá bán + Giá gốc + % giảm -->
    <div class="mt-auto">
        <div class="text-danger font-bold text-base md:text-lg"><?= number_format($p['price'], 0, ',', '.') ?>đ</div>
        <?php if (!empty($p['old_price']) && $discount > 0): ?>
            <div class="flex items-center gap-2 mb-2">
                <div class="text-gray-400 text-[11px] line-through">
                    <?= number_format($p['old_price'], 0, ',', '.') ?>đ
                </div>
                <span class="discount-label">-<?= $discount ?>%</span>
            </div>
        <?php else: ?>
            <div class="h-4 mb-2"></div>
        <?php endif; ?>

        <!-- Quà tặng kèm (nếu có) -->
        <?php if (!empty($p['gift_text'])): ?>
            <div class="mt-1 text-[11px] bg-gray-50 text-gray-700 px-2 py-1.5 rounded border border-gray-200 flex items-start gap-1.5">
                <i class="fa-solid fa-gift text-danger text-[10px] mt-0.5 shrink-0"></i> 
                <span class="leading-tight line-clamp-2"><?= htmlspecialchars($p['gift_text']) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- NÚT MUA NGAY + NÚT HỎI AI (AJAX - không reload trang) -->
    <div class="mt-3 grid grid-cols-5 gap-2 opacity-100 lg:opacity-0 group-hover:opacity-100 transition duration-300 relative z-20">
        <!-- Nút Mua ngay: event.stopPropagation() ngăn trigger onclick của card -->
        <button class="col-span-4 bg-primary text-white py-2 rounded text-[11px] md:text-sm font-bold hover:bg-blue-800 transition"
                onclick="event.stopPropagation(); addToCartAjax(<?= $p['id'] ?>);"><?= __("buy_now") ?></button>
        <!-- Nút Hỏi AI tư vấn -->
        <button onclick="event.stopPropagation(); askAIAboutProduct('<?= htmlspecialchars($p['name']) ?>')"
                class="col-span-1 border border-primary text-primary rounded flex items-center justify-center hover:bg-blue-50 transition"
                title="<?= __("ask_ai_consult") ?>">
            <i class="fa-solid fa-wand-magic-sparkles text-xs md:text-base"></i>
        </button>
    </div>
</div>
