<?php
// Tính % giảm giá nếu có giá gốc
$discount = $p['old_price'] ? round(($p['old_price'] - $p['price']) / $p['old_price'] * 100) : 0;
// Tách tags thành mảng
$tags = array_filter(explode(',', $p['tags'] ?? ''));

// Thống kê số lượng đã bán từ chi tiết đơn hàng
$soldCount = 0;
if (isset($db)) {
    $stmtSold = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM order_details WHERE product_id = ?");
    $stmtSold->execute([$p['id']]);
    $soldCount = (int)$stmtSold->fetchColumn();
}
$soldStr = getCurrentLang() === 'en' ? ($soldCount . ' sold') : ('Đã bán ' . $soldCount);

// Hàm định dạng chữ viết hoa đẹp cho tag
$formatTag = function($tag) {
    $tag = trim($tag);
    $tag = str_replace('-', ' ', $tag);
    return mb_convert_case($tag, MB_CASE_TITLE, "UTF-8");
};

// Phân loại các tags thành Promo Badges (nổi bật) và Metadata Tags
$promo_badges = [];
$meta_tags = [];
$promo_keywords = ['trả góp 0%', 'hot', 'new', 'mới', 'bán chạy', 'sale', 'giảm sốc', '0%'];

foreach ($tags as $t) {
    $t_clean = trim($t);
    if ($t_clean === '') continue;
    if (in_array(mb_strtolower($t_clean, 'UTF-8'), $promo_keywords) || mb_strpos(mb_strtolower($t_clean, 'UTF-8'), 'trả góp') !== false) {
        $promo_badges[] = $t_clean;
    } else {
        $meta_tags[] = $t_clean;
    }
}

// Chuẩn bị JSON data để truyền sang hàm viewProduct() của JS
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
    <!-- Style bổ sung cho nhãn dán cao cấp và các nút nổi bật -->
    <style>
        .promo-tag-premium {
            background: linear-gradient(135deg, #fff1f1 0%, #ffe4e4 100%);
            color: #ef4444;
            border: 1px solid #fecaca;
            font-size: 9px;
            padding: 2px 7px;
            border-radius: 5px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            box-shadow: 0 2px 5px rgba(239, 68, 68, 0.06);
            display: inline-flex;
            align-items: center;
        }
        .installment-badge-premium {
            background: linear-gradient(135deg, #f0f7ff 0%, #e0efff 100%);
            color: #3b82f6;
            border: 1px solid #bfdbfe;
            font-size: 9px;
            padding: 2px 7px;
            border-radius: 5px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            box-shadow: 0 2px 5px rgba(59, 130, 246, 0.06);
            display: inline-flex;
            align-items: center;
        }
        .product-meta-pill {
            background-color: #faf5ff;
            color: #8b5cf6;
            border: 1px solid #f3e8ff;
            padding: 1.5px 7px;
            border-radius: 9999px;
            font-size: 9.5px;
            font-weight: 600;
            letter-spacing: 0.2px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
        }
        .product-card:hover .product-meta-pill {
            background-color: #f3e8ff;
            color: #7c3aed;
            border-color: #e9d5ff;
        }

        /* Vị trí cột 3 nút nổi góc trên phải ảnh */
        .btn-quick-view-card {
            position: absolute;
            top: 8px;
            right: 8px;
            transform: scale(0.8);
            background: rgba(255, 255, 255, 0.95);
            color: #0046ab;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 30;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(0, 70, 171, 0.1);
        }

        .btn-compare-card-new {
            position: absolute;
            top: 52px;
            right: 8px;
            transform: scale(0.8);
            background: rgba(255, 255, 255, 0.95);
            color: #475569;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 30;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .btn-wishlist-card-new {
            position: absolute;
            top: 96px;
            right: 8px;
            transform: scale(0.8);
            background: rgba(255, 255, 255, 0.95);
            color: #f43f5e;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 30;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .product-card:hover .btn-quick-view-card,
        .product-card:hover .btn-compare-card-new,
        .product-card:hover .btn-wishlist-card-new {
            opacity: 1;
            transform: scale(1);
        }

        .btn-quick-view-card:hover {
            background: #0046ab;
            color: white;
            transform: scale(1.1) !important;
        }

        .btn-compare-card-new:hover {
            background: #0046ab;
            color: white;
            transform: scale(1.1) !important;
        }

        .btn-wishlist-card-new:hover, 
        .btn-wishlist-card-new.active {
            background: #f43f5e;
            color: white;
            transform: scale(1.1) !important;
        }
    </style>

    <!-- Nhãn dán góc trên trái: Chỉ hiện các Promo Badges thực sự nổi bật -->
    <div class="absolute top-3 left-3 flex flex-col gap-1.5 z-10 items-start">
        <?php if ((isset($p['gift_text']) && strpos($p['gift_text'], 'Trả góp') !== false) || in_array('Trả góp 0%', $tags) || in_array('Trạng thái trả góp 0%', $promo_badges)): ?>
            <span class="installment-badge-premium"><i class="fa-solid fa-credit-card mr-1 text-[9px]"></i><?= __("installment_0") ?></span>
        <?php endif; ?>
        <?php foreach ($promo_badges as $badge):
            if (mb_strtolower($badge, 'UTF-8') != 'trả góp 0%'): ?>
                <span class="promo-tag-premium"><i class="fa-solid fa-fire-flame-simple mr-1 text-[9px] text-red-500 animate-pulse"></i><?= htmlspecialchars($formatTag($badge)) ?></span>
            <?php endif; 
        endforeach; ?>
    </div>

    <!-- Ảnh sản phẩm -->
    <div class="h-36 md:h-44 mb-3 overflow-hidden flex items-center justify-center p-2 relative group-img">
        <img src="<?= asset($p['image']) ?>" class="max-w-full max-h-full object-contain">
        
        <!-- Nút Xem nhanh (Chỉ hiện khi hover) -->
        <button type="button" 
                onclick="event.stopPropagation(); openQuickView(<?= $productJson ?>)"
                class="btn-quick-view-card" 
                title="<?= __("quick_view") ?>">
            <i class="fa-solid fa-eye"></i>
        </button>

        <!-- Nút So sánh -->
        <button type="button" 
                onclick="event.stopPropagation(); toggleCompare(<?= $p['id'] ?>, this)"
                class="btn-compare-card-new" 
                title="<?= __("add_to_compare") ?>">
            <i class="fa-solid fa-right-left"></i>
        </button>

        <!-- Nút Yêu thích -->
        <button type="button" 
                onclick="event.stopPropagation(); toggleWishlistAjax(<?= $p['id'] ?>, this)"
                class="btn-wishlist-card-new" 
                id="btn-wishlist-<?= $p['id'] ?>"
                title="<?= __("wishlist") ?>">
            <i class="fa-regular fa-heart"></i>
        </button>
    </div>

    <!-- Hãng + Đánh giá sao + Số lượng đã bán -->
    <div class="flex justify-between items-center mb-1 text-[11px] md:text-xs">
        <span class="text-gray-500 font-medium uppercase"><?= htmlspecialchars($p['brand_name'] ?? 'Khác') ?></span>
        <div class="flex items-center gap-1.5">
            <div class="flex items-center gap-0.5">
                <span class="font-bold text-gray-700"><?= number_format($p['rate_star'] ?? 5, 1) ?></span>
                <i class="fa-solid fa-star text-[10px] text-yellow-400"></i>
            </div>
            <span class="text-gray-300">|</span>
            <span class="text-gray-500 font-medium"><?= htmlspecialchars($soldStr) ?></span>
        </div>
    </div>

    <!-- Tên sản phẩm (tối đa 2 dòng) -->
    <h3 class="font-semibold text-xs md:text-sm text-gray-800 line-clamp-2 mb-1.5 h-8 md:h-10 leading-snug">
        <?= htmlspecialchars(getCurrentLang() === 'en' ? translate_text($p['name'], 'prod_name_' . $p['id']) : $p['name']) ?>
    </h3>

    <!-- Metadata Tags Row (Dành cho thương hiệu phụ, phân loại, ngành hàng...) -->
    <?php if (!empty($meta_tags)): ?>
        <div class="flex flex-wrap gap-1 mt-1 mb-2">
            <?php 
            $count = 0;
            foreach ($meta_tags as $mt): 
                if ($count >= 3) break; // Chỉ hiển thị tối đa 3 tag
                $count++;
            ?>
                <span class="product-meta-pill">
                    <?= htmlspecialchars($formatTag($mt)) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="h-[21px] mt-1 mb-2"></div> <!-- Bù chiều cao để các card thẳng hàng -->
    <?php endif; ?>

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
                <span class="leading-tight line-clamp-2"><?= htmlspecialchars(getCurrentLang() === 'en' ? translate_text($p['gift_text'], 'prod_gift_card_' . $p['id']) : $p['gift_text']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Tồn kho -->
        <div class="mt-1.5 text-[11px] text-gray-500 flex items-center gap-1 font-medium">
            <?php if (isset($p['stock']) && $p['stock'] > 0): ?>
                <i class="fa-solid fa-box text-green-500"></i> <?= __("products_available", "Còn") ?> <?= $p['stock'] ?> <?= __("products_available_suffix", "sản phẩm") ?>
            <?php else: ?>
                <i class="fa-solid fa-triangle-exclamation text-red-500"></i> <span class="text-red-500 font-bold"><?= __("out_of_stock", "Hết hàng") ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- NÚT MUA NGAY + NÚT HỎI AI (AJAX - không reload trang) -->
    <div class="mt-3 grid grid-cols-5 gap-2 relative z-20">
        <!-- Nút Mua ngay: event.stopPropagation() ngăn trigger onclick của card -->
        <button class="col-span-4 <?= (isset($p['stock']) && $p['stock'] > 0) ? 'bg-primary hover:bg-blue-800' : 'bg-gray-400 cursor-not-allowed' ?> text-white py-2 rounded text-[11px] md:text-sm font-bold transition"
                onclick="event.stopPropagation(); <?= (isset($p['stock']) && $p['stock'] > 0) ? "buyNowAjax({$p['id']})" : "Swal.fire({ icon: 'warning', title: 'Thông báo', text: 'Sản phẩm này tạm thời hết hàng!' })" ?>;"><?= __("buy_now") ?></button>
        <!-- Nút Hỏi AI tư vấn -->
        <button onclick="event.stopPropagation(); askAIAboutProduct('<?= htmlspecialchars(getCurrentLang() === 'en' ? translate_text($p['name'], 'prod_name_' . $p['id']) : $p['name']) ?>')"
                class="col-span-1 border border-primary text-primary rounded flex items-center justify-center hover:bg-blue-50 transition"
                title="<?= __("ask_ai_consult") ?>">
            <i class="fa-solid fa-wand-magic-sparkles text-xs md:text-base"></i>
        </button>
    </div>
</div>
