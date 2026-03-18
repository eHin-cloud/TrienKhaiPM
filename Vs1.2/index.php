<?php
session_start();
require_once 'database.php';

// Bao gồm Header chung
require_once 'header.php';

// Lấy tham số từ URL
$cat_id_filter = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$brand_id_filter = isset($_GET['brand_id']) ? (int)$_GET['brand_id'] : 0;
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// CẤU HÌNH PHÂN TRANG
$limit = 10; // Số sản phẩm trên 1 trang
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// ĐẾM TỔNG SỐ SẢN PHẨM ĐỂ TÍNH SỐ TRANG
$count_query = "SELECT COUNT(*) FROM products p WHERE 1=1";
$params = [];

if ($cat_id_filter > 0) { $count_query .= " AND p.category_id = ?"; $params[] = $cat_id_filter; }
if ($brand_id_filter > 0) { $count_query .= " AND p.brand_id = ?"; $params[] = $brand_id_filter; }
if ($search_keyword !== '') { $count_query .= " AND p.name LIKE ?"; $params[] = "%" . $search_keyword . "%"; }

$stmtCount = $db->prepare($count_query);
$stmtCount->execute($params);
$total_products = $stmtCount->fetchColumn();
$total_pages = ceil($total_products / $limit);

// XÂY DỰNG TRUY VẤN LẤY SẢN PHẨM
$query_products = "SELECT p.*, b.name as brand_name FROM products p LEFT JOIN brands b ON p.brand_id = b.id WHERE 1=1";
if ($cat_id_filter > 0) { $query_products .= " AND p.category_id = ?"; }
if ($brand_id_filter > 0) { $query_products .= " AND p.brand_id = ?"; }
if ($search_keyword !== '') { $query_products .= " AND p.name LIKE ?"; }

$query_products .= " ORDER BY p.id DESC LIMIT $limit OFFSET $offset";

$stmtProd = $db->prepare($query_products);
$stmtProd->execute($params);
$products = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

// Tạo tiêu đề dựa trên bộ lọc
$current_category_name = "Tất Cả Sản Phẩm Nổi Bật";
if ($search_keyword !== '') {
    $current_category_name = "Kết quả tìm kiếm cho: '" . htmlspecialchars($search_keyword) . "' ($total_products kết quả)";
} elseif ($cat_id_filter > 0) {
    foreach ($categories as $c) {
        if ($c['id'] == $cat_id_filter) { $current_category_name = "Danh mục: " . htmlspecialchars($c['name']); break; }
    }
} elseif ($brand_id_filter > 0) {
    $stmtB = $db->prepare("SELECT name FROM brands WHERE id = ?");
    $stmtB->execute([$brand_id_filter]);
    $b = $stmtB->fetch();
    if($b) $current_category_name = "Thương hiệu: " . htmlspecialchars($b['name']);
}

// Hàm build URL cho phân trang
function buildPageUrl($p) {
    $params = $_GET;
    $params['page'] = $p;
    return 'index.php?' . http_build_query($params);
}
?>

<style>
    .product-card { background: #fff; border: 1px solid #f1f2f6; border-radius: 8px; transition: all 0.2s ease; box-shadow: 0 1px 4px rgba(0,0,0,0.05); }
    .product-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); border-color: #0046ab; z-index: 10; }
    .installment-badge { background: #f1f2f6; color: #0046ab; font-weight: 600; font-size: 10px; padding: 2px 6px; border-radius: 4px; border: 1px solid #e0e0e0; }
    .promo-tag { background: #fff1f1; color: #d70018; border: 1px solid #ffd3d3; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 500; }
    .discount-label { background: #d70018; color: #fff; font-size: 11px; padding: 0 4px; border-radius: 3px; font-weight: 700; }
    .star-active { color: #f59e0b; }
</style>

<!-- BANNER (Chỉ hiện khi ở Trang chủ, không lọc, không phân trang) -->
<?php if ($cat_id_filter == 0 && $brand_id_filter == 0 && $search_keyword == '' && $page == 1): ?>
<section class="container mx-auto px-4 mt-4 md:mt-6">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        <div class="lg:col-span-3 relative rounded-xl overflow-hidden h-[180px] md:h-[300px] shadow-sm group">
            <img src="https://images.unsplash.com/photo-1584622650111-993a426fbf0a?auto=format&fit=crop&q=80&w=1200" class="w-full h-full object-cover group-hover:scale-105 transition duration-700">
            <div class="absolute inset-0 bg-gradient-to-r from-[#00388a]/90 to-transparent flex flex-col justify-center px-6 md:px-12 text-white">
                <span class="bg-danger text-white text-[10px] md:text-xs font-bold px-3 py-1 rounded w-fit mb-2 animate-pulse">SIÊU SALE</span>
                <h1 class="text-xl md:text-4xl font-extrabold mb-2 leading-tight">Mùa Hè Sôi Động <br><span class="text-secondary">Giảm Khủng 50%</span></h1>
                <p class="hidden md:block text-sm text-blue-100 max-w-sm mt-2">Mua sắm thông minh, giao hàng lắp đặt tận nhà 0Đ. Trợ lý AI hỗ trợ 24/7.</p>
            </div>
        </div>
        
        <div class="hidden lg:flex flex-col h-[300px]">
            <div class="flex-1 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6 border border-blue-200 flex flex-col justify-center items-center text-center shadow-sm">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 shadow-sm text-primary">
                    <i class="fa-solid fa-headset text-3xl"></i>
                </div>
                <h3 class="font-bold text-primary text-xl mb-2">Tư vấn chọn mua</h3>
                <p class="text-[13px] text-gray-600 mb-5 leading-relaxed">Trợ lý AI PRO giúp bạn chọn đúng sản phẩm phù hợp nhu cầu, nhanh chóng và chính xác.</p>
                <button onclick="toggleAIChat()" class="w-full bg-white text-primary text-sm font-bold py-2.5 px-4 rounded-lg border border-primary hover:bg-primary hover:text-white transition shadow-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Hỏi AI ngay
                </button>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- DANH SÁCH SẢN PHẨM -->
<section class="container mx-auto px-4 mt-8">
    <div class="flex justify-between items-center mb-4 border-b-2 border-primary pb-2">
        <h2 class="text-lg md:text-xl font-bold uppercase text-gray-800 flex items-center gap-2">
            <i class="fa-solid <?= ($cat_id_filter==0 && $brand_id_filter==0 && $search_keyword=='') ? 'fa-fire text-danger' : 'fa-list text-primary' ?>"></i> 
            <?= $current_category_name ?>
        </h2>
        <?php if ($cat_id_filter > 0 || $brand_id_filter > 0 || $search_keyword !== ''): ?>
            <a href="index.php" class="text-[13px] bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded text-gray-700 transition"><i class="fa-solid fa-xmark"></i> Bỏ lọc</a>
        <?php endif; ?>
    </div>

    <?php if (empty($products)): ?>
        <div class="text-center py-16 bg-white rounded-xl border border-gray-100 shadow-sm">
            <i class="fa-solid fa-box-open text-5xl text-gray-300 mb-4"></i>
            <p class="text-gray-500 font-medium">Không tìm thấy sản phẩm phù hợp với bộ lọc hiện tại.</p>
            <a href="index.php" class="inline-block mt-4 text-primary font-medium hover:underline">Quay lại trang chủ</a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-4">
            <?php foreach ($products as $p): 
                $discount = $p['old_price'] ? round((($p['old_price'] - $p['price']) / $p['old_price']) * 100) : 0;
                $tags = array_filter(explode(',', $p['tags']));
                
                $productJson = htmlspecialchars(json_encode([
                    'id' => $p['id'], 'name' => $p['name'], 'price' => $p['price'], 
                    'old_price' => $p['old_price'], 'discount' => $discount, 
                    'image' => $p['image'], 'brand_name' => $p['brand_name'] ?? 'Hãng khác'
                ]));
            ?>
            <div class="product-card p-3 flex flex-col relative cursor-pointer" onclick="viewProduct(<?= $productJson ?>)">
                <!-- Badges -->
                <div class="absolute top-3 left-3 flex flex-col gap-1 z-10 items-start">
                    <?php if (strpos($p['gift_text'], 'Trả góp') !== false || in_array('Trả góp 0%', $tags)): ?><span class="installment-badge">Trả góp 0%</span><?php endif; ?>
                    <?php foreach ($tags as $tag): $tag=trim($tag); if ($tag != 'Trả góp 0%' && $tag != ''): ?><span class="promo-tag"><?= htmlspecialchars($tag) ?></span><?php endif; endforeach; ?>
                </div>

                <div class="h-36 md:h-44 mb-3 overflow-hidden flex items-center justify-center p-2"><img src="<?= $p['image'] ?>" class="max-w-full max-h-full object-contain"></div>
                
                <div class="flex justify-between items-center mb-1 text-[11px] md:text-xs">
                    <span class="text-gray-500 font-medium uppercase"><?= htmlspecialchars($p['brand_name'] ?? 'Khác') ?></span>
                    <div class="flex items-center gap-1"><span class="font-bold text-gray-700"><?= number_format($p['rate_star'], 1) ?></span><i class="fa-solid fa-star text-[10px] star-active"></i></div>
                </div>

                <h3 class="font-semibold text-xs md:text-sm text-gray-800 line-clamp-2 mb-2 h-8 md:h-10 leading-snug"><?= htmlspecialchars($p['name']) ?></h3>
                
                <div class="mt-auto">
                    <div class="text-danger font-bold text-base md:text-lg"><?= number_format($p['price'], 0, ',', '.') ?>đ</div>
                    <?php if ($p['old_price'] && $discount > 0): ?>
                        <div class="flex items-center gap-2 mb-2"><div class="text-gray-400 text-[11px] line-through"><?= number_format($p['old_price'], 0, ',', '.') ?>đ</div><span class="discount-label">-<?= $discount ?>%</span></div>
                    <?php else: ?><div class="h-4 mb-2"></div><?php endif; ?>
                    
                    <?php if ($p['gift_text']): ?>
                        <div class="mt-1 text-[11px] bg-gray-50 text-gray-700 px-2 py-1.5 rounded border border-gray-200 flex items-start gap-1.5">
                            <i class="fa-solid fa-gift text-danger text-[10px] mt-0.5 shrink-0"></i> <span class="leading-tight line-clamp-2"><?= htmlspecialchars($p['gift_text']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- HIỂN THỊ PHÂN TRANG -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-center items-center mt-10 gap-2">
            <?php if ($page > 1): ?>
                <a href="<?= buildPageUrl($page - 1) ?>" class="px-3 py-1.5 bg-white border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700 text-sm shadow-sm transition"><i class="fa-solid fa-chevron-left"></i> Trước</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?= buildPageUrl($i) ?>" class="px-3.5 py-1.5 rounded-md border text-sm font-medium transition <?= $i === $page ? 'bg-primary text-white border-primary shadow-md' : 'bg-white border-gray-300 hover:bg-gray-50 text-gray-700 shadow-sm' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="<?= buildPageUrl($page + 1) ?>" class="px-3 py-1.5 bg-white border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700 text-sm shadow-sm transition">Tiếp <i class="fa-solid fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</section>

<?php require_once 'footer.php'; ?>