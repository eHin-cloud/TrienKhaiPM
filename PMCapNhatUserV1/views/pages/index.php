<?php
/**
 * ============================================================
 * INDEX.PHP - TRANG CHỦ / DANH SÁCH SẢN PHẨM
 * ============================================================
 * 
 * Trang chủ hiển thị danh sách sản phẩm với các chức năng:
 * 
 * 1. HIỂN THỊ SẢN PHẨM:
 *    - Tất cả sản phẩm (mặc định)
 *    - Lọc theo danh mục (?cat_id=X)
 *    - Lọc theo thương hiệu (?brand_id=X)
 *    - Tìm kiếm theo từ khóa (?search=keyword)
 * 
 * 2. PHÂN TRANG:
 *    - Mỗi trang hiển thị 10 sản phẩm ($limit = 10)
 *    - Nút "Trước" / "Tiếp" và số trang
 * 
 * 3. BANNER QUẢNG CÁO:
 *    - Chỉ hiện ở trang chủ (không lọc, trang 1)
 *    - Banner sale + nút "Hỏi AI ngay"
 * 
 * 4. THẺ SẢN PHẨM:
 *    - Ảnh, tên, giá, giá gốc, % giảm, hãng, đánh giá sao
 *    - Nhãn dán (tags): Trả góp 0%, Mới 2024...
 *    - Nút "Mua ngay" (AJAX) + Nút "Hỏi AI tư vấn"
 * 
 * @requires database.php - Kết nối DB, hàm getAllCategories()
 * @requires header.php   - Giao diện header + navigation
 * @requires footer.php   - Giao diện footer + AI Chat + sản phẩm đã xem
 */

// session_start() removed by Router
//// test_db_functions.php removed // Chỉ để test các hàm DB, không cần thiết cho index.php thực tế
// database.php is auto-loaded by Router

// ==========================================
// XỬ LÝ AJAX TRƯỚC KHI INCLUDE HEADER
// Khi ajax=1, chỉ trả HTML nội dung grid SP (không có header/footer)
// ==========================================
$is_ajax = isset($_GET['ajax']);

// Include giao diện Header (bao gồm nav menu, đăng nhập/đăng ký)
if (!$is_ajax) {
    require_once __DIR__ . '/../partials/header.php';
}

use App\Repository\ProductRepository;
use App\Service\ProductService;
use App\Repository\BrandRepository;

// ==========================================
// LẤY THAM SỐ KHỞI TẠO TỪ URL & SERVICE
// ==========================================
$cat_id_filter = isset($_GET['cat_id']) ? (int) $_GET['cat_id'] : 0;
$brand_id_filter = isset($_GET['brand_id']) ? (int) $_GET['brand_id'] : 0;
$min_price_filter = isset($_GET['min_price']) ? (int) $_GET['min_price'] : 0;
$max_price_filter = isset($_GET['max_price']) ? (int) $_GET['max_price'] : 0;
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

$limit = 12;
$page = isset($_GET['page']) && (int) $_GET['page'] > 0 ? (int) $_GET['page'] : 1;

$productRepo = new ProductRepository($db);
$productService = new ProductService($productRepo);

// Gọi khối Service tính toán Pagination & Lấy sản phẩm
$result = $productService->getPaginatedHomeProducts($cat_id_filter, $brand_id_filter, $search_keyword, $min_price_filter, $max_price_filter, $page, $limit);

$products = $result['products'];
$total_products = $result['total_products'];
$total_pages = $result['total_pages'];

// ==========================================
// TẠO TIÊU ĐỀ ĐỘNG DỰA TRÊN BỘ LỌC
// ==========================================
$current_category_name = "Tất Cả Sản Phẩm Nổi Bật";
if ($search_keyword !== '') {
    $current_category_name = "Kết quả tìm kiếm cho: '" . htmlspecialchars($search_keyword) . "' ($total_products kết quả)";
} elseif ($cat_id_filter > 0) {
    // Khi AJAX, $categories có thể chưa được load (header.php bị skip) - load thủ công
    if (!isset($categories)) {
        $categories = getAllCategories($db);
    }
    foreach ($categories as $c) {
        if ($c['id'] == $cat_id_filter) {
            $current_category_name = "Danh mục: " . htmlspecialchars($c['name']);
            break;
        }
    }
} elseif ($brand_id_filter > 0) {
    // Lấy tên hãng qua BrandRepository
    $brandRepo = new BrandRepository($db);
    $b = $brandRepo->findById($brand_id_filter);
    if ($b) {
        $current_category_name = "Thương hiệu: " . htmlspecialchars($b['name']);
    }
}

/**
 * Hàm tạo URL phân trang, giữ nguyên các tham số bộ lọc hiện tại
 * @param int $p - Số trang cần tạo URL
 * @return string - URL hoàn chỉnh với tham số page
 */
function buildPageUrl($p)
{
    $params = $_GET;         // Giữ nguyên tất cả tham số GET hiện tại
    $params['page'] = $p;    // Thay đổi tham số page
    return 'index.php?' . http_build_query($params);
}

// (Biến $is_ajax đã được khai báo ở trên, trước khi include header)
// Nếu là AJAX request: set header và bắt đầu output buffer - sẽ exit sớm
if ($is_ajax) {
    header('Content-Type: text/html; charset=utf-8');
}
?>

<?php if (!$is_ajax): ?>
<script>
    function setQuickPrice(min, max) {
        document.querySelector('input[name="min_price"]').value = min;
        document.querySelector('input[name="max_price"]').value = max;
    }
</script>
<style>
    /* Thẻ sản phẩm - hover nổi lên với shadow */
    .product-card {
        background: #fff;
        border: 1px solid #f1f2f6;
        border-radius: 8px;
        transition: all 0.2s ease;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    }

    .product-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.1);
        border-color: #0046ab;
        z-index: 10;
    }

    /* Badge trả góp */
    .installment-badge {
        background: #f1f2f6;
        color: #0046ab;
        font-weight: 600;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #e0e0e0;
    }

    /* Tag khuyến mãi */
    .promo-tag {
        background: #fff1f1;
        color: #d70018;
        border: 1px solid #ffd3d3;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 500;
    }

    /* Label % giảm giá */
    .discount-label {
        background: #d70018;
        color: #fff;
        font-size: 11px;
        padding: 0 4px;
        border-radius: 3px;
        font-weight: 700;
    }

    /* Sao đánh giá màu vàng */
    .star-active {
        color: #f59e0b;
    }

    /* Animation cho bộ lọc */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-filter {
        animation: fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* Bộ lọc Ngang (Top Bar) Premium */
    .filter-section-new {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.6);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 
            0 10px 25px -5px rgba(0, 0, 0, 0.04),
            0 8px 10px -6px rgba(0, 0, 0, 0.02);
        position: relative;
        overflow: hidden;
    }
    
    .filter-section-new::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #0046ab, #00d2ff);
        opacity: 0.8;
    }
    
    .filter-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 0 22px;
        height: 42px; /* Cố định chiều cao để không bị lệch */
        border-radius: 21px;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid #f1f5f9;
        color: #64748b;
        background: #f8fafc;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        white-space: nowrap;
        cursor: pointer;
        user-select: none;
    }

    .filter-chip:hover {
        color: #0046ab;
        background: #ffffff;
        transform: translateY(-2px);
        box-shadow: 0 0 0 1.5px #0046ab, 0 8px 16px rgba(0, 70, 171, 0.1);
    }

    .filter-chip.active {
        background: linear-gradient(135deg, #0046ab 0%, #0061ff 100%);
        color: white;
        border: none;
        box-shadow: 0 8px 20px rgba(0, 70, 171, 0.25);
        padding: 0 23px; /* Bù cho 1px border bị mất để giữ chiều rộng */
    }

    .filter-chip i {
        font-size: 14px; /* Tăng nhẹ kích thước icon */
        color: inherit;
        display: flex;
        align-items: center;
    }

    /* Expandable Content */
    .expandable-content {
        max-height: 0;
        opacity: 0;
        visibility: hidden;
        overflow: hidden;
        transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease, visibility 0.3s;
    }

    .expandable-content.is-expanded {
        max-height: 1000px;
        opacity: 1;
        visibility: visible;
        padding-top: 1.5rem;
        border-top: 1px solid #f1f5f9;
        margin-top: 1.25rem;
    }

    .toggle-advanced-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 700;
        color: #0046ab;
        background: #f0f7ff;
        padding: 6px 14px;
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.2s;
        border: 1.5px solid transparent;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .toggle-advanced-btn:hover {
        background: #e0efff;
        border-color: rgba(0, 70, 171, 0.2);
    }

    .toggle-advanced-btn i {
        transition: transform 0.3s ease;
    }

    /* Ẩn scrollbar nhưng vẫn kéo được */
    .scroll-x-hidden {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .scroll-x-hidden::-webkit-scrollbar {
        display: none;
    }

    .price-input-compact {
        background-color: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 500;
        width: 130px;
        outline: none;
        transition: all 0.2s;
    }

    .price-input-compact:focus {
        border-color: #0046ab;
        background-color: white;
        box-shadow: 0 0 0 4px rgba(0, 70, 171, 0.08);
    }

    .quick-price-btn {
        text-[11px] px-3 py-1.5 bg-slate-50 border border-slate-100 hover:border-blue-200 hover:text-blue-600 rounded-lg transition-all font-medium text-slate-500;
    }

    /* Skeleton Loading Style */
    .skeleton-box {
        background: #f1f5f9;
        background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite linear;
        border-radius: 8px;
    }

    @keyframes shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Quick View Modal Style */
    #quick-view-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1000;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        align-items: center;
        justify-content: center;
        padding: 1rem;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    #quick-view-modal.active {
        display: flex;
        opacity: 1;
    }

    .modal-content {
        background: white;
        width: 100%;
        max-width: 850px;
        border-radius: 20px;
        overflow: hidden;
        position: relative;
        transform: translateY(20px);
        transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    #quick-view-modal.active .modal-content {
        transform: translateY(0);
    }

    /* Nút Xem nhanh ở góc phải thẻ sản phẩm */
    .btn-quick-view {
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

    .product-card:hover .btn-quick-view {
        opacity: 1;
        transform: scale(1);
    }

    .btn-quick-view:hover {
        background: #0046ab;
        color: white;
        transform: scale(1.1) !important;
    }

    /* Style Phân Trang Premium */
    .pagination-link {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-weight: 700;
        font-size: 14px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid #e2e8f0;
        background: white;
        color: #475569;
    }

    .pagination-link:hover {
        border-color: #0046ab;
        color: #0046ab;
        background: #f0f7ff;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 70, 171, 0.1);
    }

    .pagination-link.active {
        background: #0046ab;
        color: white;
        border-color: #0046ab;
        box-shadow: 0 8px 20px rgba(0, 70, 171, 0.25);
    }

    .pagination-nav-btn {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: white;
        border: 1px solid #e2e8f0;
        color: #475569;
        transition: all 0.2s;
    }

    .pagination-nav-btn:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #0046ab;
    }
</style>

<!-- ==========================================
      SECTION: BANNER QUẢNG CÁO (Chỉ hiện ở trang chủ, trang 1)
      ========================================== -->
<?php if ($cat_id_filter == 0 && $brand_id_filter == 0 && $search_keyword == '' && $page == 1):
    $site_settings = getSiteSettings($db);
    // Lấy 4 sản phẩm sale (Cập nhật 4 sản phẩm mới mỗi ngày dựa vào ngày hiện tại)
    $stmt_banner = $db->query("SELECT * FROM products WHERE old_price > price ORDER BY RAND(CURDATE() + 0) LIMIT 4");
    $banner_products = $stmt_banner->fetchAll(PDO::FETCH_ASSOC);
?>
    <style>
        /* Carousel Banner Styles */
        .banner-carousel { position: relative; overflow: hidden; border-radius: 0.75rem; }
        .carousel-inner { display: flex; transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1); height: 100%; }
        .carousel-item { min-width: 100%; position: relative; }
        .carousel-indicators { position: absolute; bottom: 1rem; left: 50%; transform: translateX(-50%); display: flex; gap: 0.5rem; z-index: 20; }
        .carousel-indicator { width: 0.5rem; height: 0.5rem; border-radius: 50%; background-color: rgba(255, 255, 255, 0.4); cursor: pointer; transition: all 0.3s ease; }
        .carousel-indicator.active { background-color: white; width: 1.5rem; border-radius: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .carousel-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255, 255, 255, 0.15); color: white; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; backdrop-filter: blur(4px); cursor: pointer; z-index: 20; transition: all 0.3s; opacity: 0; border: 1px solid rgba(255, 255, 255, 0.3); }
        .banner-carousel:hover .carousel-btn { opacity: 1; }
        .carousel-btn:hover { background: rgba(255, 255, 255, 0.3); transform: translateY(-50%) scale(1.1); }
        .carousel-btn.prev { left: 1rem; }
        .carousel-btn.next { right: 1rem; }
        @media (max-width: 768px) { .carousel-btn { opacity: 1; width: 30px; height: 30px; font-size: 12px; } .carousel-btn.prev { left: 0.5rem; } .carousel-btn.next { right: 0.5rem; } }
    </style>

    <section class="container mx-auto px-4 mt-4 md:mt-6">
        <div class="grid grid-cols-1 gap-4">
            <?php if (count($banner_products) > 0): ?>
                <!-- Carousel Banner Động (Bao gồm banner gốc ở Slide 1) -->
                <div class="banner-carousel h-[180px] md:h-[300px] shadow-sm group" id="mainBannerCarousel">
                    <div class="carousel-inner" id="carouselInner">
                        
                        <!-- SLIDE 1: BANNER GỐC (Static) -->
                        <div class="carousel-item relative cursor-pointer group/item">
                            <img src="<?= htmlspecialchars($site_settings['banner_image'] ?? 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?auto=format&fit=crop&q=80&w=1200') ?>"
                                class="absolute inset-0 w-full h-full object-cover group-hover/item:scale-105 transition duration-700">
                            <div class="absolute inset-0 bg-gradient-to-r from-[#00388a]/90 to-transparent flex flex-col justify-center px-6 md:px-12 text-white">
                                <span class="bg-danger text-white text-[10px] md:text-xs font-bold px-3 py-1 rounded w-fit mb-2 animate-pulse"><?= htmlspecialchars($site_settings['banner_badge'] ?? 'SIÊU SALE') ?></span>
                                <h1 class="text-xl md:text-4xl font-extrabold mb-2 leading-tight"><?= htmlspecialchars($site_settings['banner_title1'] ?? 'Tháng 4 Sôi Động') ?> <br><span class="text-secondary"><?= htmlspecialchars($site_settings['banner_title2'] ?? 'Giảm Khủng 50%') ?></span></h1>
                                <p class="hidden md:block text-sm text-blue-100 max-w-sm mt-2"><?= htmlspecialchars($site_settings['banner_subtitle'] ?? 'Mua sắm thông minh, giao hàng lắp đặt tận nhà 0Đ. Trợ lý AI hỗ trợ 24/7.') ?></p>
                            </div>
                        </div>

                        <!-- CÁC SLIDE TIẾP THEO: SẢN PHẨM SALE -->
                        <?php foreach ($banner_products as $index => $bp): 
                            $discount = round(($bp['old_price'] - $bp['price']) / $bp['old_price'] * 100);
                        ?>
                            <div class="carousel-item relative cursor-pointer group/item" onclick="window.location.href='product_detail.php?id=<?= $bp['id'] ?>'">
                                <!-- Nền Banner: Ảnh sản phẩm làm nền mờ -->
                                <img src="<?= $bp['image'] ?>" class="absolute inset-0 w-full h-full object-cover blur-md opacity-40 scale-110">
                                <div class="absolute inset-0 bg-gradient-to-r from-[#00388a]/95 via-[#0046ab]/80 to-transparent"></div>
                                
                                <div class="absolute inset-0 flex items-center px-8 md:px-16">
                                    <div class="w-2/3 md:w-1/2 text-white z-10">
                                        <span class="bg-danger text-white text-[10px] md:text-xs font-bold px-3 py-1 rounded w-fit mb-2 md:mb-3 inline-block animate-pulse">SẢN PHẨM CỦA NGÀY -<?= $discount ?>%</span>
                                        <h1 class="text-lg md:text-3xl lg:text-4xl font-extrabold mb-1 md:mb-3 leading-tight line-clamp-2 md:line-clamp-3 drop-shadow-md"><?= htmlspecialchars($bp['name']) ?></h1>
                                        <div class="flex items-baseline gap-2 md:gap-3 mt-1 md:mt-3">
                                            <span class="text-secondary text-xl md:text-4xl font-black drop-shadow-md"><?= number_format($bp['price'], 0, ',', '.') ?>đ</span>
                                            <span class="text-blue-200 line-through text-xs md:text-lg"><?= number_format($bp['old_price'], 0, ',', '.') ?>đ</span>
                                        </div>
                                        <button class="mt-3 md:mt-6 bg-white text-primary px-4 md:px-6 py-1.5 md:py-2.5 rounded-full font-bold text-xs md:text-sm hover:bg-secondary hover:text-white transition shadow-lg inline-flex items-center gap-2">
                                            Mua ngay <i class="fa-solid fa-arrow-right"></i>
                                        </button>
                                    </div>
                                    <div class="w-1/3 md:w-1/2 flex justify-center items-center z-10 h-full py-2 md:py-6">
                                        <img src="<?= $bp['image'] ?>" class="max-h-full object-contain drop-shadow-2xl group-hover/item:scale-110 transition duration-500 ease-out">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Nút điều hướng -->
                    <button class="carousel-btn prev" onclick="moveCarousel(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="carousel-btn next" onclick="moveCarousel(1)"><i class="fa-solid fa-chevron-right"></i></button>
                    
                    <!-- Dấu chấm chỉ báo -->
                    <div class="carousel-indicators" id="carouselIndicators">
                        <div class="carousel-indicator active" onclick="goToSlide(0)"></div>
                        <?php foreach ($banner_products as $index => $bp): ?>
                            <div class="carousel-indicator" onclick="goToSlide(<?= $index + 1 ?>)"></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <script>
                    const inner = document.getElementById('carouselInner');
                    const indicators = document.querySelectorAll('.carousel-indicator');
                    const items = document.querySelectorAll('.carousel-item');
                    const totalSlides = items.length;
                    
                    // Kỹ thuật Infinite Carousel mượt mà: Clone phần tử đầu và cuối
                    const firstClone = items[0].cloneNode(true);
                    const lastClone = items[totalSlides - 1].cloneNode(true);
                    inner.appendChild(firstClone);
                    inner.insertBefore(lastClone, items[0]);
                    
                    let currentSlide = 1; // Slide thực bắt đầu ở index 1 do có clone
                    let isTransitioning = false;
                    let slideInterval;

                    // Khởi tạo vị trí ban đầu
                    inner.style.transform = `translateX(-100%)`;

                    function updateIndicators() {
                        let activeIndex = currentSlide - 1;
                        if (activeIndex === totalSlides) activeIndex = 0; // Đang ở clone đầu (chuẩn bị nhảy về 0)
                        if (activeIndex === -1) activeIndex = totalSlides - 1; // Đang ở clone cuối
                        
                        indicators.forEach((ind, index) => {
                            ind.classList.toggle('active', index === activeIndex);
                        });
                    }

                    function updateSlide(useTransition = true) {
                        inner.style.transition = useTransition ? 'transform 0.7s cubic-bezier(0.4, 0, 0.2, 1)' : 'none';
                        inner.style.transform = `translateX(-${currentSlide * 100}%)`;
                        updateIndicators();
                    }

                    inner.addEventListener('transitionend', () => {
                        isTransitioning = false;
                        // Nếu đi quá sang phải (vào clone của slide đầu tiên) -> giật ngược lại slide đầu tiên thật (không có animation)
                        if (currentSlide === totalSlides + 1) {
                            currentSlide = 1;
                            updateSlide(false);
                        }
                        // Nếu đi quá sang trái (vào clone của slide cuối cùng) -> giật ngược lại slide cuối cùng thật
                        if (currentSlide === 0) {
                            currentSlide = totalSlides;
                            updateSlide(false);
                        }
                    });

                    function moveCarousel(direction) {
                        if (isTransitioning) return;
                        isTransitioning = true;
                        currentSlide += direction;
                        updateSlide(true);
                        resetInterval();
                    }

                    function goToSlide(index) {
                        if (isTransitioning) return;
                        currentSlide = index + 1; // +1 do mảng HTML thực tế bị đẩy lên bởi clone
                        updateSlide(true);
                        resetInterval();
                    }

                    function startInterval() {
                        slideInterval = setInterval(() => { moveCarousel(1); }, 4000); // 4 giây đổi 1 lần
                    }

                    function resetInterval() {
                        clearInterval(slideInterval);
                        startInterval();
                    }

                    if (totalSlides > 1) {
                        startInterval();
                        const bannerElement = document.getElementById('mainBannerCarousel');
                        if (bannerElement) {
                            bannerElement.addEventListener('mouseenter', () => clearInterval(slideInterval));
                            bannerElement.addEventListener('mouseleave', startInterval);
                        }
                    }
                </script>
            <?php else: ?>
                <!-- Banner tĩnh nếu không có sản phẩm sale -->
                <div class="relative rounded-xl overflow-hidden h-[180px] md:h-[300px] shadow-sm group">
                    <img src="<?= htmlspecialchars($site_settings['banner_image'] ?? 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?auto=format&fit=crop&q=80&w=1200') ?>"
                        class="w-full h-full object-cover group-hover:scale-105 transition duration-700">
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-[#00388a]/90 to-transparent flex flex-col justify-center px-6 md:px-12 text-white">
                        <span
                            class="bg-danger text-white text-[10px] md:text-xs font-bold px-3 py-1 rounded w-fit mb-2 animate-pulse"><?= htmlspecialchars($site_settings['banner_badge'] ?? 'SIÊU SALE') ?></span>
                        <h1 class="text-xl md:text-4xl font-extrabold mb-2 leading-tight"><?= htmlspecialchars($site_settings['banner_title1'] ?? 'Tháng 4 Sôi Động') ?> <br><span
                                class="text-secondary"><?= htmlspecialchars($site_settings['banner_title2'] ?? 'Giảm Khủng 50%') ?></span></h1>
                        <p class="hidden md:block text-sm text-blue-100 max-w-sm mt-2"><?= htmlspecialchars($site_settings['banner_subtitle'] ?? 'Mua sắm thông minh, giao hàng lắp đặt tận nhà 0Đ. Trợ lý AI hỗ trợ 24/7.') ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<!-- ==========================================
     SECTION: THANH BỘ LỌC NGANG (Top Bar Premium)
     ========================================== -->
<section class="container mx-auto px-4 mt-8 animate-filter">
    <div class="filter-section-new">
        <div class="space-y-6">
            <!-- 1. Bộ lọc Danh mục -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-[11px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-layer-group text-primary"></i> DANH MỤC
                    </h3>

                    <!-- Nút Toggle Phóng to/Thu nhỏ -->
                    <button type="button" id="btn-toggle-advanced" class="toggle-advanced-btn">
                        <span id="toggle-text"><?= ($brand_id_filter > 0 || $min_price_filter > 0 || $max_price_filter > 0) ? 'Thu nhỏ' : 'Xem thêm bộ lọc' ?></span>
                        <i class="fa-solid fa-chevron-down text-[10px] <?= ($brand_id_filter > 0 || $min_price_filter > 0 || $max_price_filter > 0) ? 'rotate-180' : '' ?>"></i>
                    </button>
                </div>
                <div class="flex overflow-x-auto gap-3 py-2 pb-3 scroll-x-hidden -mx-1 px-1">
                    <a href="index.php?brand_id=<?= $brand_id_filter ?>&min_price=<?= $min_price_filter ?>&max_price=<?= $max_price_filter ?>&search=<?= urlencode($search_keyword) ?>" 
                       class="filter-chip <?= $cat_id_filter == 0 ? 'active' : '' ?>">
                        Tất cả
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="index.php?cat_id=<?= $cat['id'] ?>&brand_id=<?= $brand_id_filter ?>&min_price=<?= $min_price_filter ?>&max_price=<?= $max_price_filter ?>&search=<?= urlencode($search_keyword) ?>" 
                           class="filter-chip <?= $cat_id_filter == $cat['id'] ? 'active' : '' ?>">
                            <i class="fa-solid <?= $cat['icon'] ?>"></i> <?= htmlspecialchars($cat['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Vùng nội dung nâng cao (Thương hiệu & Giá) -->
            <div id="advanced-filter-content" class="expandable-content <?= ($brand_id_filter > 0 || $min_price_filter > 0 || $max_price_filter > 0) ? 'is-expanded' : '' ?>">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Thương hiệu -->
                <div class="lg:col-span-2 space-y-3">
                    <h3 class="text-[11px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-award text-secondary"></i> Thương hiệu
                    </h3>
                    <div class="flex flex-wrap gap-2 py-1">
                        <a href="index.php?cat_id=<?= $cat_id_filter ?>&min_price=<?= $min_price_filter ?>&max_price=<?= $max_price_filter ?>&search=<?= urlencode($search_keyword) ?>" 
                           class="filter-chip <?= $brand_id_filter == 0 ? 'active' : '' ?>">
                            Tất cả hãng
                        </a>
                        <?php 
                        $brandRepo = new BrandRepository($db);
                        $all_brands_list = $brandRepo->findAll();
                        foreach ($all_brands_list as $brand): ?>
                            <a href="index.php?cat_id=<?= $cat_id_filter ?>&brand_id=<?= $brand['id'] ?>&min_price=<?= $min_price_filter ?>&max_price=<?= $max_price_filter ?>&search=<?= urlencode($search_keyword) ?>" 
                               class="filter-chip <?= $brand_id_filter == $brand['id'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($brand['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Lọc Giá -->
                <div class="space-y-3">
                    <h3 class="text-[11px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-tag text-danger"></i> Khoảng giá (VNĐ)
                    </h3>
                    <form action="index.php" method="GET" class="flex flex-col gap-3">
                        <input type="hidden" name="cat_id" value="<?= $cat_id_filter ?>">
                        <input type="hidden" name="brand_id" value="<?= $brand_id_filter ?>">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search_keyword) ?>">
                        
                        <div class="flex items-center gap-2">
                            <input type="number" name="min_price" value="<?= $min_price_filter ?>" placeholder="Từ" class="price-input-compact flex-1">
                            <span class="text-gray-400">-</span>
                            <input type="number" name="max_price" value="<?= $max_price_filter ?>" placeholder="Đến" class="price-input-compact flex-1">
                            <button type="submit" class="bg-primary text-white p-2 rounded-lg hover:bg-blue-800 transition shadow-sm h-[34px] w-[34px] flex items-center justify-center">
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </button>
                        </div>

                        <!-- Mốc giá nhanh -->
                        <div class="flex flex-wrap gap-2 mt-1">
                            <button type="button" onclick="setQuickPriceToForm(0, 1000000)" class="text-[10px] px-3 py-1.5 bg-slate-50 border border-slate-100 hover:border-blue-200 hover:text-blue-600 rounded-lg transition-all font-medium text-slate-500">Dưới 1tr</button>
                            <button type="button" onclick="setQuickPriceToForm(1000000, 10000000)" class="text-[10px] px-3 py-1.5 bg-slate-50 border border-slate-100 hover:border-blue-200 hover:text-blue-600 rounded-lg transition-all font-medium text-slate-500">1tr - 10tr</button>
                            <button type="button" onclick="setQuickPriceToForm(10000000, 50000000)" class="text-[10px] px-3 py-1.5 bg-slate-50 border border-slate-100 hover:border-blue-200 hover:text-blue-600 rounded-lg transition-all font-medium text-slate-500">Trên 10tr</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnToggle = document.getElementById('btn-toggle-advanced');
        const advancedContent = document.getElementById('advanced-filter-content');
        const toggleText = document.getElementById('toggle-text');
        const toggleIcon = btnToggle ? btnToggle.querySelector('i') : null;

        if (btnToggle && advancedContent) {
            btnToggle.addEventListener('click', function() {
                const isExpanded = advancedContent.classList.contains('is-expanded');
                
                if (isExpanded) {
                    advancedContent.classList.remove('is-expanded');
                    toggleText.innerText = 'Xem thêm bộ lọc';
                    toggleIcon.classList.remove('rotate-180');
                    // Cuộn nhẹ lên trên nếu đang ở dưới
                    window.scrollTo({ top: advancedContent.offsetTop - 100, behavior: 'smooth' });
                } else {
                    advancedContent.classList.add('is-expanded');
                    toggleText.innerText = 'Thu nhỏ';
                    toggleIcon.classList.add('rotate-180');
                }
            });
        }
    });

    function setQuickPriceToForm(min, max) {
        const minInput = document.querySelector('input[name="min_price"]');
        const maxInput = document.querySelector('input[name="max_price"]');
        if(minInput && maxInput) {
            minInput.value = min;
            maxInput.value = max;
            // Submit form
            minInput.closest('form').submit();
        }
    }
</script>
<?php endif; /* Kết thúc if (!$is_ajax) cho phần banner & filter */ ?>

<!-- ==========================================
     SECTION: DANH SÁCH SẢN PHẨM (Full-Width)
     ========================================== -->
<?php if (!$is_ajax): ?>
<section id="ajax-product-section" class="container mx-auto px-4 mt-8">
    <div id="ajax-inner-content" class="w-full">
<?php endif; ?>

<?php if ($is_ajax): ob_start(); endif; ?>
        <!-- Tiêu đề section + Nút bỏ lọc -->
        <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-3">
            <div class="flex flex-col gap-1">
                <h2 class="text-lg md:text-xl font-extrabold uppercase text-gray-800 flex items-center gap-2">
                    <i class="fa-solid <?= ($cat_id_filter == 0 && $brand_id_filter == 0 && $search_keyword == '') ? 'fa-fire text-danger' : 'fa-list text-primary' ?>"></i>
                    <?= $current_category_name ?>
                </h2>
                <?php if ($cat_id_filter > 0 || $brand_id_filter > 0 || $search_keyword !== ''): ?>
                    <p class="text-xs text-gray-500 font-medium">Tìm thấy <span class="text-primary font-bold"><?= $total_products ?></span> sản phẩm phù hợp</p>
                <?php endif; ?>
            </div>
            
            <?php if ($cat_id_filter > 0 || $brand_id_filter > 0 || $search_keyword !== '' || $min_price_filter > 0 || $max_price_filter > 0): ?>
                <a href="index.php" class="text-xs bg-red-50 text-danger font-bold hover:bg-red-100 px-4 py-2 rounded-full transition flex items-center gap-1.5 border border-red-100">
                    <i class="fa-solid fa-rotate-left"></i> Xóa tất cả bộ lọc
                </a>
            <?php endif; ?>
        </div>

            <?php if (empty($products)): ?>
                <div class="text-center py-16 bg-white rounded-xl border border-gray-100 shadow-sm">
                    <i class="fa-solid fa-box-open text-5xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 font-medium">Không tìm thấy sản phẩm phù hợp với bộ lọc hiện tại.</p>
                    <a href="index.php" class="inline-block mt-4 text-primary font-medium hover:underline">Quay lại trang chủ</a>
                </div>
            <?php else: ?>
                <!-- 1. Skeleton Loading Grid (Ẩn đi sau khi load) -->
                <div id="product-skeleton" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4 mb-10">
                    <?php for($i=0; $i<8; $i++): ?>
                        <div class="bg-white p-3 rounded-xl border border-gray-100 shadow-sm space-y-4">
                            <div class="skeleton-box h-40 w-full"></div>
                            <div class="skeleton-box h-4 w-3/4"></div>
                            <div class="skeleton-box h-4 w-1/2"></div>
                            <div class="skeleton-box h-10 w-full"></div>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- 2. Danh sách sản phẩm thật (Ẩn lúc đầu) -->
                <div id="product-grid-main" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4 opacity-0 transition-opacity duration-500">
                    <?php foreach ($products as $p): 
                        // ... (logic dữ liệu SP)
                        // Tính % giảm giá nếu có giá gốc
                        $discount = $p['old_price'] ? round(($p['old_price'] - $p['price']) / $p['old_price'] * 100) : 0;
                        // Tách tags thành mảng
                        $tags = array_filter(explode(',', $p['tags']));

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
                                <?php if ((isset($p['gift_text']) && strpos($p['gift_text'], 'Trả góp') !== false) || in_array('Trả góp 0%', $tags)): ?><span
                                    class="installment-badge">Trả góp 0%</span><?php endif; ?>
                                <?php foreach ($tags as $tag):
                                    $tag = trim($tag);
                                    if ($tag != 'Trả góp 0%' && $tag != ''): ?><span
                                        class="promo-tag"><?= htmlspecialchars($tag) ?></span><?php endif; endforeach; ?>
                            </div>

                            <!-- Ảnh sản phẩm -->
                            <div class="h-36 md:h-44 mb-3 overflow-hidden flex items-center justify-center p-2 relative group-img">
                                <img src="<?= $p['image'] ?>" class="max-w-full max-h-full object-contain">
                                
                                <!-- Nút Xem nhanh (Chỉ hiện khi hover) -->
                                <button type="button" 
                                        onclick="event.stopPropagation(); openQuickView(<?= $productJson ?>)"
                                        class="btn-quick-view" 
                                        title="Xem nhanh">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>

                            <!-- Hãng + Đánh giá sao -->
                            <div class="flex justify-between items-center mb-1 text-[11px] md:text-xs">
                                <span
                                    class="text-gray-500 font-medium uppercase"><?= htmlspecialchars($p['brand_name'] ?? 'Khác') ?></span>
                                <div class="flex items-center gap-1"><span
                                    class="font-bold text-gray-700"><?= number_format($p['rate_star'], 1) ?></span><i
                                    class="fa-solid fa-star text-[10px] star-active"></i></div>
                            </div>

                            <!-- Tên sản phẩm (tối đa 2 dòng) -->
                            <h3 class="font-semibold text-xs md:text-sm text-gray-800 line-clamp-2 mb-2 h-8 md:h-10 leading-snug">
                                <?= htmlspecialchars($p['name']) ?></h3>

                            <!-- Giá bán + Giá gốc + % giảm -->
                            <div class="mt-auto">
                                <div class="text-danger font-bold text-base md:text-lg"><?= number_format($p['price'], 0, ',', '.') ?>đ
                                </div>
                                <?php if ($p['old_price'] && $discount > 0): ?>
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="text-gray-400 text-[11px] line-through">
                                            <?= number_format($p['old_price'], 0, ',', '.') ?>đ</div><span
                                            class="discount-label">-<?= $discount ?>%</span>
                                    </div>
                                <?php else: ?>
                                    <div class="h-4 mb-2"></div><?php endif; ?>

                                <!-- Quà tặng kèm (nếu có) -->
                                <?php if ($p['gift_text']): ?>
                                    <div
                                        class="mt-1 text-[11px] bg-gray-50 text-gray-700 px-2 py-1.5 rounded border border-gray-200 flex items-start gap-1.5">
                                        <i class="fa-solid fa-gift text-danger text-[10px] mt-0.5 shrink-0"></i> <span
                                            class="leading-tight line-clamp-2"><?= htmlspecialchars($p['gift_text']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- NÚT MUA NGAY + NÚT HỎI AI (AJAX - không reload trang) -->
                            <div
                                class="mt-3 grid grid-cols-5 gap-2 opacity-100 lg:opacity-0 group-hover:opacity-100 transition duration-300 relative z-20">
                                <!-- Nút Mua ngay: event.stopPropagation() ngăn trigger onclick của card -->
                                <button
                                    class="col-span-4 bg-primary text-white py-2 rounded text-[11px] md:text-sm font-bold hover:bg-blue-800 transition"
                                    onclick="event.stopPropagation(); addToCartAjax(<?= $p['id'] ?>);">Mua ngay</button>
                                <!-- Nút Hỏi AI tư vấn -->
                                <button onclick="event.stopPropagation(); askAIAboutProduct('<?= htmlspecialchars($p['name']) ?>')"
                                    class="col-span-1 border border-primary text-primary rounded flex items-center justify-center hover:bg-blue-50 transition"
                                    title="Hỏi AI tư vấn">
                                    <i class="fa-solid fa-wand-magic-sparkles text-xs md:text-base"></i>
                                </button>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- ==========================================
                     PHÂN TRANG (Luôn hiển thị để người dùng thấy cấu trúc)
                     ========================================== -->
                <?php if ($total_products > 0): ?>
                    <div class="flex justify-center items-center mt-12 gap-3 pb-8">
                        <!-- Nút "Trước" -->
                        <?php if ($page > 1): ?>
                            <a href="<?= buildPageUrl($page - 1) ?>" class="pagination-nav-btn">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-nav-btn opacity-30 cursor-not-allowed">
                                <i class="fa-solid fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>

                        <!-- Số trang -->
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="<?= buildPageUrl($i) ?>"
                                class="pagination-link <?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Nút "Tiếp" -->
                        <?php if ($page < $total_pages): ?>
                            <a href="<?= buildPageUrl($page + 1) ?>" class="pagination-nav-btn">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-nav-btn opacity-30 cursor-not-allowed">
                                <i class="fa-solid fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php
                // Nếu là AJAX, trả về chỉ nội dung bên trong div#ajax-inner-content
                if ($is_ajax) {
                    $content = ob_get_clean();
                    echo $content;
                    exit;
                }
                ?>

            <?php endif; ?>
        </div>
<?php if (!$is_ajax): ?>
    </div>
</section>
<?php endif; ?>

<style>
    .suggested-section {
        background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
        border: 1px solid #e2e8f0;
    }
    .suggested-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .suggested-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 20px -5px rgba(0, 70, 171, 0.15);
    }
</style>

<!-- ==========================================
      SECTION: GỢI Ý SẢN PHẨM (Suggested Products)
      ========================================== -->
<?php
    $suggested_limit = 4;
    $suggested_products = [];
    $has_more_suggested = false;
    $total_suggested_count = 0;
    
    if ($cat_id_filter > 0) {
        $stmt_count = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt_count->execute([$cat_id_filter]);
        $total_suggested_count = (int)$stmt_count->fetchColumn();
        $has_more_suggested = $total_suggested_count > $suggested_limit;

        $stmt_sug = $db->prepare("SELECT p.*, b.name as brand_name FROM products p LEFT JOIN brands b ON p.brand_id = b.id WHERE p.category_id = ? ORDER BY RAND() LIMIT ?");
        $stmt_sug->execute([$cat_id_filter, $suggested_limit]);
        $suggested_products = $stmt_sug->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt_count = $db->prepare("SELECT COUNT(*) FROM products");
        $stmt_count->execute();
        $total_suggested_count = (int)$stmt_count->fetchColumn();
        $has_more_suggested = $total_suggested_count > $suggested_limit;

        $stmt_sug = $db->prepare("SELECT p.*, b.name as brand_name FROM products p LEFT JOIN brands b ON p.brand_id = b.id ORDER BY RAND() LIMIT ?");
        $stmt_sug->execute([$suggested_limit]);
        $suggested_products = $stmt_sug->fetchAll(PDO::FETCH_ASSOC);
    }
?>

<?php if (!empty($suggested_products)): ?>
<section class="container mx-auto px-4 mt-12 mb-8">
    <div class="suggested-section rounded-2xl p-4 md:p-6 shadow-sm">
        <div class="flex items-center justify-start mb-4">
            <h3 class="text-sm md:text-base font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-wand-magic-sparkles text-primary"></i>
                <?= $cat_id_filter > 0 ? 'Sản phẩm tương tự bạn có thể thích' : 'Gợi ý dành cho bạn' ?>
            </h3>
        </div>
        
        <div id="suggested-grid" class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($suggested_products as $sp):
                $sp_discount = $sp['old_price'] ? round(($sp['old_price'] - $sp['price']) / $sp['old_price'] * 100) : 0;
                $spJson = htmlspecialchars(json_encode([
                    'id' => $sp['id'],
                    'name' => $sp['name'],
                    'price' => $sp['price'],
                    'old_price' => $sp['old_price'],
                    'discount' => $sp_discount,
                    'image' => $sp['image'],
                    'brand_name' => $sp['brand_name'] ?? 'Hãng khác'
                ]));
            ?>
                <div class="suggested-card bg-white p-3 rounded-xl border border-gray-100 shadow-sm cursor-pointer relative group" onclick="viewProduct(<?= $spJson ?>)">
                    <div class="h-24 md:h-32 mb-2 overflow-hidden flex items-center justify-center relative">
                        <img src="<?= $sp['image'] ?>" class="max-w-full max-h-full object-contain group-hover:scale-110 transition duration-300">
                    </div>
                    <h4 class="text-xs md:text-sm font-semibold text-gray-800 line-clamp-1 mb-1"><?= htmlspecialchars($sp['name']) ?></h4>
                    <div class="flex items-center justify-between">
                        <span class="text-danger font-bold text-sm"><?= number_format($sp['price'], 0, ',', '.') ?>đ</span>
                        <?php if ($sp_discount > 0): ?>
                            <span class="bg-danger text-white text-[9px] px-1 rounded font-bold">-<?= $sp_discount ?>%</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($has_more_suggested): ?>
            <div class="mt-6 text-center">
                <button id="btn-load-more-suggested"
                        data-cat-id="<?= $cat_id_filter ?>"
                        data-offset="<?= $suggested_limit ?>"
                        data-total="<?= $total_suggested_count ?>"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-blue-200 text-primary rounded-full text-xs font-bold hover:bg-blue-50 hover:border-primary transition-all shadow-sm uppercase tracking-wider">
                    <span id="load-more-text">Xem thêm sản phẩm</span>
                    <i id="load-more-icon" class="fa-solid fa-chevron-down text-[10px]"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

<!-- ==========================================
     MODAL XEM NHANH (QUICK VIEW)
     ========================================== -->
<div id="quick-view-modal" onclick="closeQuickView(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <button onclick="closeQuickView()" class="absolute top-4 right-4 w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 transition-all z-50">
            <i class="fa-solid fa-xmark text-xl text-gray-500"></i>
        </button>
        
        <div id="modal-body" class="p-6 md:p-8">
            <!-- Dữ liệu đổ từ JS -->
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Logic Load More Suggested Products (AJAX - không reload trang)
        const btnLoadMore = document.getElementById('btn-load-more-suggested');
        if (btnLoadMore) {
            btnLoadMore.addEventListener('click', async function() {
                const catId = this.dataset.catId;
                let offset = parseInt(this.dataset.offset);
                const total = parseInt(this.dataset.total);
                const textSpan = document.getElementById('load-more-text');
                const icon = document.getElementById('load-more-icon');
                const grid = document.getElementById('suggested-grid');
                const loadPerClick = 4;

                btnLoadMore.disabled = true;
                textSpan.innerText = 'Đang tải...';
                icon.className = 'fa-solid fa-circle-notch fa-spin text-[10px]';

                // Sử dụng đường dẫn tương đối để Router xử lý (vì file này đã được khai báo trong public/index.php)
                const apiUrl = `get_more_suggested.php?cat_id=${catId}&offset=${offset}&limit=${loadPerClick}`;

                try {
                    const response = await fetch(apiUrl);
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    const data = await response.json();

                    if (data.success && data.products && data.products.length > 0) {
                        data.products.forEach(p => {
                            const discount = p.old_price && p.old_price > 0
                                ? Math.round((p.old_price - p.price) / p.old_price * 100)
                                : 0;
                            const pData = {
                                id: p.id,
                                name: p.name,
                                price: p.price,
                                old_price: p.old_price,
                                discount: discount,
                                image: p.image,
                                brand_name: p.brand_name || 'Hãng khác'
                            };

                            const card = document.createElement('div');
                            card.className = 'suggested-card bg-white p-3 rounded-xl border border-gray-100 shadow-sm cursor-pointer relative group';
                            card.onclick = () => viewProduct(pData);
                            card.style.animation = 'fadeInUp 0.4s ease forwards';
                            card.innerHTML = `
                                <div class="h-24 md:h-32 mb-2 overflow-hidden flex items-center justify-center relative">
                                    <img src="${p.image}" class="max-w-full max-h-full object-contain group-hover:scale-110 transition duration-300" loading="lazy">
                                </div>
                                <h4 class="text-xs md:text-sm font-semibold text-gray-800 line-clamp-1 mb-1">${p.name}</h4>
                                <div class="flex items-center justify-between">
                                    <span class="text-danger font-bold text-sm">${new Intl.NumberFormat('vi-VN').format(p.price)}đ</span>
                                    ${discount > 0 ? `<span class="bg-danger text-white text-[9px] px-1 rounded font-bold">-${discount}%</span>` : ''}
                                </div>
                            `;
                            grid.appendChild(card);
                        });

                        // Chỉ cho phép xem thêm 1 lần, load xong là ẩn luôn nút
                        btnLoadMore.parentElement.style.display = 'none';
                    } else {
                        // Không còn sản phẩm nào, ẩn nút
                        btnLoadMore.parentElement.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Lỗi tải thêm gợi ý sản phẩm:', error);
                    textSpan.innerText = 'Thử lại';
                } finally {
                    if (btnLoadMore.parentElement.style.display !== 'none') {
                        btnLoadMore.disabled = false;
                        textSpan.innerText = 'Xem thêm sản phẩm';
                        icon.className = 'fa-solid fa-chevron-down text-[10px]';
                    }
                }
            });
        }

        // Logic Skeleton Loading ban đầu
        window.addEventListener('load', function() {
            setTimeout(() => {
                const skeleton = document.getElementById('product-skeleton');
                const grid = document.getElementById('product-grid-main');
                if (skeleton) {
                    skeleton.style.display = 'none';
                }
                if (grid) {
                    grid.classList.replace('opacity-0', 'opacity-100');
                }
            }, 600);
        });

        // Khởi tạo Phân trang AJAX
        initAjaxPagination();
    });

    // Logic Phân trang AJAX - delegate trên document, chỉ gắn 1 lần
    let _paginationListenerActive = false;
    function initAjaxPagination() {
        if (_paginationListenerActive) return;
        _paginationListenerActive = true;

        document.addEventListener('click', function(e) {
            const link = e.target.closest('#ajax-inner-content .pagination-link, #ajax-inner-content .pagination-nav-btn');
            if (link && link.tagName === 'A' && link.getAttribute('href')) {
                e.preventDefault();
                const url = link.getAttribute('href');
                loadPage(url);
            }
        });
    }

    async function loadPage(url) {
        const productSection = document.getElementById('ajax-product-section');
        if (!productSection) return;

        // 1. Cuộn mượt lên đầu danh sách sản phẩm
        const offset = productSection.offsetTop - 100;
        window.scrollTo({ top: offset, behavior: 'smooth' });
        
        // 2. Hiển thị trạng thái Loading (Skeleton)
        const mainGrid = document.getElementById('product-grid-main');
        if (mainGrid) mainGrid.classList.add('opacity-0');
        
        const skeleton = document.getElementById('product-skeleton');
        if (skeleton) {
            skeleton.style.display = 'grid';
        }

        // 3. Gọi AJAX lấy nội dung mới
        const ajaxUrl = url.includes('?') ? `${url}&ajax=1` : `${url}?ajax=1`;
        
        try {
            const response = await fetch(ajaxUrl);
            const html = await response.text();
            
            // Đợi một chút để hiệu ứng cuộn và skeleton trông mượt mà hơn
            setTimeout(() => {
                // Chỉ cập nhật nội dung bên trong #ajax-inner-content (tránh lặp filter/CSS)
                const innerContent = document.getElementById('ajax-inner-content');
                if (innerContent) {
                    innerContent.innerHTML = html;
                }
                
                // Cập nhật URL trên trình duyệt (để share link, nhấn Back vẫn OK)
                history.pushState(null, '', url);
                
                // UI: Ẩn skeleton và hiện lưới sản phẩm mới
                const newSkeleton = document.getElementById('product-skeleton');
                const newGrid = document.getElementById('product-grid-main');
                if (newSkeleton) newSkeleton.style.display = 'none';
                if (newGrid) {
                    setTimeout(() => newGrid.classList.replace('opacity-0', 'opacity-100'), 50);
                }

                // Tái khởi tạo event listener phân trang (vì DOM đã thay đổi)
                initAjaxPagination();
            }, 500);
        } catch (error) {
            console.error('Lỗi khi tải trang AJAX:', error);
            window.location.href = url; // Fallback về load trang truyền thống
        }
    }

    // Logic Quick View
    function openQuickView(p) {
        const modal = document.getElementById('quick-view-modal');
        const body = document.getElementById('modal-body');
        
        body.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="flex items-center justify-center p-4 bg-gray-50 rounded-2xl">
                    <img src="${p.image}" class="max-w-full max-h-[350px] object-contain drop-shadow-xl">
                </div>
                <div class="flex flex-col">
                    <span class="text-xs font-bold text-primary uppercase tracking-widest mb-2">${p.brand_name}</span>
                    <h2 class="text-xl md:text-2xl font-extrabold text-gray-800 mb-4">${p.name}</h2>
                    
                    <div class="flex items-baseline gap-3 mb-6">
                        <span class="text-2xl md:text-3xl font-black text-danger">${new Intl.NumberFormat('vi-VN').format(p.price)}đ</span>
                        ${p.old_price && p.old_price > 0 ? `<span class="text-gray-400 line-through text-sm">${new Intl.NumberFormat('vi-VN').format(p.old_price)}đ</span>` : ''}
                    </div>

                    <div class="space-y-4 mb-8">
                        <div class="flex items-center gap-3 text-sm text-gray-600">
                            <i class="fa-solid fa-check-circle text-green-500"></i>
                            <span>Bảo hành chính hãng 12 tháng</span>
                        </div>
                        <div class="flex items-center gap-3 text-sm text-gray-600">
                            <i class="fa-solid fa-truck-fast text-blue-500"></i>
                            <span>Miễn phí giao hàng & lắp đặt tận chỗ</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-auto">
                        <button onclick="addToCartAjax(${p.id}); closeQuickView()" class="bg-primary text-white font-bold py-3.5 rounded-xl hover:bg-blue-800 transition shadow-lg shadow-blue-100 uppercase text-xs">
                            Cho vào giỏ
                        </button>
                        <a href="product_detail.php?id=${p.id}" class="bg-white text-primary border border-primary font-bold py-3.5 rounded-xl hover:bg-blue-50 transition text-center uppercase text-xs">
                            Xem chi tiết
                        </a>
                    </div>
                </div>
            </div>
        `;
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeQuickView(e) {
        const modal = document.getElementById('quick-view-modal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
</script>
