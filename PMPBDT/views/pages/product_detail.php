<?php
/**
 * ============================================================
 * PRODUCT_DETAIL.PHP - TRANG CHI TIẾT SẢN PHẨM
 * ============================================================
 * 
 * CHỨC NĂNG:
 * 1. Hiện thị thông tin chi tiết của sản phẩm theo parameter ID.
 * 2. Hiển thị form đánh giá sản phẩm có tính năng đính kèm Media.
 * 3. Cho phép người dùng đăng và xóa đánh giá cá nhân.
 * 4. Gọi Ajax hành động Mua hàng và Đăng ký trả góp.
 * 5. Gợi ý các sản phẩm cùng cấu hình (liên quan/cùng hãng).
 * 
 * @requires database.php, header.php, footer.php
 */

use App\Repository\ProductRepository;
use App\Service\ProductService;

// session_start() removed by Router
// database.php is auto-loaded by Router
/**
 * Bắt buộc ID sản phẩm tồn tại trên URL và là số hợp lệ.
 */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<h2 class='text-center mt-10 font-bold text-red-500'>" . __("invalid_product") . "</h2>");
}
$id = (int) $_GET['id'];

/**
 * ========================================================
 * 1. XỬ LÝ POST: GỬI ĐÁNH GIÁ & NHẬN XÉT SẢN PHẨM
 * ========================================================
 * Nhận request khi user submit form đánh giá.
 * - Yêu cầu user phải đăng nhập.
 * - Hỗ trợ upload tối đa 5 file ảnh/video (<= 20MB).
 * - Tính toán và cập nhật lại điểm đánh giá trung bình.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (isset($_SESSION['user_id'])) {
        // Lấy thông tin văn bản & thông số đánh giá từ submit request
        $rating = (int) $_POST['rating'];
        $comment = trim($_POST['comment']);
        $user_id = $_SESSION['user_id'];

        // --- XỬ LÝ UPLOAD MULTI-FILE MEDIA ---
        // Biến $media_paths sẽ lưu mảng đường dẫn file hợp lệ (sau khi upload pass)
        $media_paths = [];
        if (isset($_FILES['review_media'])) {
            $upload_dir = 'uploads/reviews/';
            // Tự khởi tạo folder thư mục trên máy chủ nếu chưa từng tồn tại
            if (!file_exists($upload_dir))
                mkdir($upload_dir, 0777, true);

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm', 'video/quicktime'];
            $max_files = 5; // Cấu hình giới hạn số luợng file cho form
            $file_count = count($_FILES['review_media']['name']);

            // Lặp vòng để lần lượt handle theo mảng từng file
            for ($f = 0; $f < min($file_count, $max_files); $f++) {
                if ($_FILES['review_media']['error'][$f] === UPLOAD_ERR_OK) {
                    $mime = $_FILES['review_media']['type'][$f];
                    // Điều kiện: file phải đúng chuẩn Mime cho phép và không dung lượng nặng hơn 20MB
                    if (in_array($mime, $allowed_types) && $_FILES['review_media']['size'][$f] <= 20 * 1024 * 1024) {
                        $ext = pathinfo($_FILES['review_media']['name'][$f], PATHINFO_EXTENSION);
                        $new_name = 'rev_' . time() . '_' . $f . '.' . $ext; // Sinh tự động Random name
                        $target = $upload_dir . $new_name;

                        if (move_uploaded_file($_FILES['review_media']['tmp_name'][$f], $target)) {
                            $media_paths[] = $target; // Thêm path thành công vào mảng DB
                        }
                    }
                }
            }
        }
        // Chuyển dữ liệu mảng path array() về kiểu chuỗi văn bản JSON format để tương thích table
        $media_json = !empty($media_paths) ? json_encode($media_paths) : null;

        // --- CHÈN DATABASE & UPDATE TỔNG ---
        $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        // Xác thực logic: Giá trị đánh giá phải nằm trong ngưỡng [1,5] sao (nếu không phải reply)
        if (!empty($comment)) {
            $stmtRev = $db->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, media, parent_id) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmtRev->execute([$id, $user_id, $rating, $comment, $media_json, $parent_id])) {

                // Chỉ tính lại trung bình nếu là review gốc (không phải reply)
                if (!$parent_id) {
                    // Thực thi truy xuất DB để tính lại trung bình cộng tổng số thực tế
                    $avgStmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE product_id = ? AND parent_id IS NULL");
                    $avgStmt->execute([$id]);
                    $avgData = $avgStmt->fetch(PDO::FETCH_ASSOC);

                    // Cập nhật giá trị đã tính toán trở lại bảng cha `products` để không phải truy vấn lúc show list home
                    $db->prepare("UPDATE products SET rate_star = ?, total_reviews = ? WHERE id = ?")->execute([round($avgData['avg_rating'], 1), $avgData['total'], $id]);
                }

                // Load lại component review sau khi xong
                if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Cảm ơn quý khách đã comment!']);
                    exit;
                }
                $_SESSION['review_success_msg'] = "Cảm ơn quý khách đã comment!";
                header("Location: product_detail.php?id=$id#reviews");
                exit;
            }
        }
    }
}

// Chế độ chỉ lấy danh sách đánh giá (Dùng cho AJAX cập nhật UI không reload)
if (isset($_GET['only_reviews']) && $_GET['only_reviews'] == 1) {
    $reviews = getProductReviews($db, $id);
    if (empty($reviews)) {
        echo '<p class="text-center text-gray-500 italic py-4">' . __("no_reviews") . '</p>';
    } else {
        $totalReviews = count($reviews);
        foreach ($reviews as $index => $rev) {
            echo '<div class="review-item ' . ($index >= 2 ? 'hidden' : '') . '" data-index="' . $index . '">';
            echo renderReviewItem($rev, $id);
            echo '</div>';
        }
        if ($totalReviews > 2) {
            echo '<div class="text-center mt-6" id="load-more-reviews-container">';
            echo '<button onclick="loadMoreReviews()" class="px-8 py-2.5 border-2 border-primary text-primary rounded-full font-bold text-sm hover:bg-primary hover:text-white transition shadow-sm">';
            echo 'Xem thêm ' . ($totalReviews - 2) . ' đánh giá khác';
            echo '</button></div>';
        }
    }
    exit;
}

/**
 * ========================================================
 * 2. XỬ LÝ POST: XÓA ĐÁNH GIÁ SẢN PHẨM
 * ========================================================
 * - Xác thực: Chỉ cho phép xóa khi tài khoản này là người đăng (hoặc Admin).
 * - Xóa dữ liệu ổ cứng (các file media đã upload).
 * - Xóa dòng dữ liệu trong database và cập nhật lại điểm đánh giá trung bình.
 */
// Xử lý XÓA đánh giá của chính mình
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    if (isset($_SESSION['user_id'])) {
        $review_id = (int) $_POST['review_id'];
        $user_id = $_SESSION['user_id'];

        // Điều kiện khắt khe: chỉ cho phép lấy nếu review đó thuộc chủ nhân thật sự (hoặc do quyền nhân vật trên session đang là 'admin')
        $checkOwner = $db->prepare("SELECT * FROM reviews WHERE id = ? AND (user_id = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))");
        $checkOwner->execute([$review_id, $user_id, $user_id]);
        $reviewToDelete = $checkOwner->fetch(PDO::FETCH_ASSOC);

        if ($reviewToDelete) {
            // --- BƯỚC 1: XÓA CỨNG DATA TRÊN THƯ MỤC ROOT Ổ CỨNG ---
            // Tránh gây phình tài nguyên máy chủ bằng thao tác xóa chuỗi filepath được map vào bảng
            if (!empty($reviewToDelete['media'])) {
                $media_arr = json_decode($reviewToDelete['media'], true);
                if (is_array($media_arr)) {
                    foreach ($media_arr as $filepath) {
                        // Xác nhận file thực sự còn tồn tại để diệt bỏ thông qua hàm PHP unlink
                        if (file_exists($filepath)) {
                            unlink($filepath);
                        }
                    }
                }
            }
            // --- BƯỚC 2: XÓA LỊCH SỬ DÒNG SQL MAP VỚI SẢN PHẨM ---
            $db->prepare("DELETE FROM reviews WHERE id = ?")->execute([$review_id]);

            // --- BƯỚC 3: CẬP NHẬT LẠI GIÁ TRỊ LỆCH (NẾU REVIEW ĐÓ ĐANG TỒN TẠI TÍNH TOÁN SAI) ---
            $avgStmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE product_id = ?");
            $avgStmt->execute([$id]);
            $avgData = $avgStmt->fetch(PDO::FETCH_ASSOC);

            $newRate = round($avgData['avg_rating'] ?? 0, 1);
            $newTotal = $avgData['total'] ?? 0;
            $db->prepare("UPDATE products SET rate_star = ?, total_reviews = ? WHERE id = ?")->execute([$newRate, $newTotal, $id]);

            // Return callback path with fragment jump
            header("Location: product_detail.php?id=$id#reviews");
            exit;
        }
    }
}

/**
 * ========================================================
 * 3. FETCH DATA TỪ DATABASE
 * ========================================================
 */
// Khởi tạo ProductRepository/ProductService cho các luồng gợi ý
$productRepo = new ProductRepository($db);
$productService = new ProductService($productRepo);

// Lưu lại lịch sử xem sản phẩm nếu khách hàng đã đăng nhập
if (isset($_SESSION['user_id'])) {
    $productService->trackProductView($_SESSION['user_id'], $id);
}

// Truy xuất chi tiết sản phẩm theo ID
$product = $productService->getProductDetails($id);

if (!$product) {
    die("<h2 class='text-center mt-10 font-bold text-red-500'>" . __("not_found_product") . "</h2>");
}

// Truy xuất danh sách sản phẩm tương tự và sản phẩm mua kèm
$related = $productService->getAlternativeProducts($id, 6, 0.15);
$cross_sell_products = $productService->getCrossSellProducts($id, 6);

// Truy xuất danh sách đánh giá & thống kê số điểm phân phối sao
$reviews = getProductReviews($db, $id);
$reviewStats = getReviewStats($reviews);

/**
 * Hàm đệ quy hiển thị đánh giá và phản hồi
 */
function renderReviewItem($rev, $productId, $level = 0) {
    global $id; // ID sản phẩm từ trang cha
    $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    $is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $rev['user_id'];
    
    ob_start(); ?>
    <div class="<?= $level === 0 ? 'border-b border-gray-100 py-6 last:border-0' : 'mt-4 border-t border-gray-50 pt-4 pb-2' ?>">
        <!-- Header -->
        <div class="flex items-center justify-between mb-2">
            <div class="font-bold <?= $level === 0 ? 'text-[14px]' : 'text-[13px]' ?> flex items-center gap-2">
                <span class="<?= $level === 0 ? 'w-8 h-8 text-xs' : 'w-6 h-6 text-[10px]' ?> bg-primary/10 text-primary rounded-full flex items-center justify-center font-bold">
                    <?= mb_substr($rev['fullname'], 0, 1) ?>
                </span>
                <span class="text-gray-800"><?= htmlspecialchars($rev['fullname']) ?></span>
                <span class="text-[11px] text-gray-400 font-normal ml-1">• <?= date('H:i d/m/Y', strtotime($rev['created_at'])) ?></span>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($is_owner || $is_admin): ?>
                    <button onclick="confirmDeleteReview(<?= $rev['id'] ?>)" class="text-gray-300 hover:text-red-500 transition text-[12px]">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                    <form id="delete-review-form-<?= $rev['id'] ?>" method="POST" action="product_detail.php?id=<?= $id ?>" class="hidden">
                        <?= csrf_input_field() ?>
                        <input type="hidden" name="delete_review" value="1">
                        <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rating (Chỉ hiện cho review gốc) -->
        <?php if ($level === 0): ?>
            <div class="flex items-center gap-2 mb-2 pl-10">
                <div class="flex text-yellow-400 text-[12px] gap-0.5">
                    <?php for ($i = 1; $i <= 5; $i++) echo "<i class='fa-solid fa-star " . ($i <= $rev['rating'] ? '' : 'text-gray-200') . "'></i>"; ?>
                </div>
                <span class="text-xs font-medium text-gray-500">
                    <?php
                    $labels = [1 => __("very_bad"), 2 => __("bad"), 3 => __("normal"), 4 => __("good"), 5 => __("excellent")];
                    echo $labels[$rev['rating']] ?? '';
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Nội dung -->
        <p class="text-[14px] <?= $level === 0 ? 'pl-10' : 'pl-8' ?> text-gray-700 leading-relaxed">
            <?= nl2br(htmlspecialchars($rev['comment'])) ?>
        </p>

        <!-- Media (Chỉ hiện cho review gốc nếu có) -->
        <?php if ($level === 0): 
            $media = isset($rev['media']) ? json_decode($rev['media'], true) : [];
            if (!empty($media)): ?>
                <div class="flex flex-wrap gap-2 mt-3 pl-10">
                    <?php foreach ($media as $file): 
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        $is_video = in_array($ext, ['mp4', 'webm', 'mov']); ?>
                        <div class="relative w-20 h-20 rounded-lg overflow-hidden border border-gray-200 cursor-pointer group" onclick="openMediaViewer('<?= $file ?>', <?= $is_video ? 'true' : 'false' ?>)">
                            <?php if ($is_video): ?>
                                <video src="<?= $file ?>" class="w-full h-full object-cover"></video>
                                <div class="absolute inset-0 bg-black/30 flex items-center justify-center group-hover:bg-black/50 transition">
                                    <i class="fa-solid fa-play text-white text-lg"></i>
                                </div>
                            <?php else: ?>
                                <img src="<?= $file ?>" class="w-full h-full object-cover group-hover:scale-105 transition">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; 
        endif; ?>

        <!-- Nút Phản hồi & Form -->
        <div class="<?= $level === 0 ? 'pl-10' : 'pl-8' ?> mt-3">
            <button onclick="toggleReplyForm(<?= $rev['id'] ?>, '<?= addslashes($rev['fullname']) ?>')" class="text-primary text-[13px] font-bold hover:underline flex items-center gap-1 opacity-80 hover:opacity-100 transition">
                <i class="fa-solid fa-reply"></i> <?= __("reply") ?>
            </button>

            <div id="reply-form-<?= $rev['id'] ?>" class="hidden bg-gray-50 p-4 rounded-xl border border-gray-200 mt-3 max-w-xl shadow-sm">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="POST" action="product_detail.php?id=<?= $id ?>#reviews">
                        <?= csrf_input_field() ?>
                        <input type="hidden" name="parent_id" value="<?= $rev['id'] ?>">
                        <input type="hidden" name="rating" value="5">
                        <textarea name="comment" required rows="<?= $level === 0 ? '4' : '3' ?>" 
                            class="w-full p-3 text-[14px] border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:outline-none mb-2 resize-none shadow-inner"
                            placeholder="<?= __("reply_placeholder") ?>"></textarea>
                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="toggleReplyForm(<?= $rev['id'] ?>)" class="px-3 py-1.5 text-gray-500 text-xs font-medium hover:bg-gray-200 rounded-md transition"><?= __("cancel") ?></button>
                            <button type="submit" name="submit_review" class="bg-primary text-white px-5 py-1.5 rounded-md text-xs font-bold hover:bg-blue-800 transition shadow-sm">
                                <?= __("send") ?>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center py-2">
                        <p class="text-xs text-gray-500 mb-2"><?= __("login_to_review") ?></p>
                        <button onclick="document.getElementById('loginModal').classList.remove('hidden')" class="text-primary font-bold text-xs hover:underline"><?= __("login_now") ?></button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Các phản hồi con (Chỉ hiện ở cấp gốc) -->
            <?php if ($level === 0 && !empty($rev['replies'])): ?>
                <?php 
                $replyCount = count($rev['replies']);
                if ($replyCount >= 2): 
                ?>
                    <div class="mt-3">
                        <button onclick="toggleReplies(<?= $rev['id'] ?>)" id="btn-show-replies-<?= $rev['id'] ?>" 
                            class="text-[12px] text-primary font-bold hover:underline flex items-center gap-1.5 bg-blue-50 px-3 py-1 rounded-full transition">
                            <i class="fa-solid fa-caret-down"></i> <?= sprintf(__("view_more_replies"), $replyCount) ?>
                        </button>
                        <div id="replies-container-<?= $rev['id'] ?>" class="hidden">
                            <?php foreach ($rev['replies'] as $reply) echo renderReviewItem($reply, $productId, 1); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mt-2">
                        <?php foreach ($rev['replies'] as $reply) echo renderReviewItem($reply, $productId, 1); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php return ob_get_clean();
}

// Thiết lập Meta SEO cho trang chi tiết sản phẩm
$meta_title = (getCurrentLang() === 'en' ? translate_text($product['name'], 'prod_name_' . $product['id']) : $product['name']) . " - Điện Máy PRO";
$meta_desc = mb_substr(strip_tags(getCurrentLang() === 'en' ? translate_html_content($product['description'], 'prod_desc_' . $product['id']) : $product['description']), 0, 160) . "...";
$meta_image = $product['image'];

require_once __DIR__ . '/../partials/header.php';
?>

<div class="container mx-auto px-4 py-4 md:py-6">
    <!-- =========================================================
         BREADCRUMB & TIÊU ĐỀ SẢN PHẨM
         ========================================================= -->
    <!-- Breadcrumb -->
    <div class="text-[13px] text-primary mb-4 flex items-center gap-2 overflow-x-auto whitespace-nowrap hide-scrollbar">
        <a href="index.php" class="hover:underline font-medium"><?= __("home") ?></a>
        <i class="fa-solid fa-angle-right text-[10px] text-gray-400"></i>
        <a href="index.php?cat_id=<?= $product['category_id'] ?>"
            class="hover:underline font-medium"><?= htmlspecialchars(__cat($product['category_name'])) ?></a>
        <i class="fa-solid fa-angle-right text-[10px] text-gray-400"></i>
        <a href="index.php?brand_id=<?= $product['brand_id'] ?>"
            class="hover:underline font-medium"><?= htmlspecialchars($product['brand_name']) ?></a>
    </div>

    <!-- Tiêu đề Sản Phẩm -->
    <div class="mb-4 pb-4 border-b border-gray-200">
        <h1 class="text-[22px] md:text-2xl font-bold text-gray-800 leading-snug mb-2">
            <?= htmlspecialchars(getCurrentLang() === 'en' ? translate_text($product['name'], 'prod_name_' . $product['id']) : $product['name']) ?></h1>
        <div class="flex flex-wrap items-center gap-4 text-[13px]">
            <div class="flex items-center gap-1 text-yellow-400">
                <span class="font-bold text-gray-700"><?= $product['rate_star'] ?></span>
                <?php for ($i = 1; $i <= 5; $i++): ?><i
                        class="fa-solid fa-star <?= $i <= $product['rate_star'] ? '' : 'text-gray-200' ?>"></i><?php endfor; ?>
                <a href="#reviews" class="text-primary hover:underline ml-1">(<?= $product['total_reviews'] ?> <?= __("reviews_count") ?>)</a>
            </div>
        </div>
    </div>

    <!-- =========================================================
         NỘI DUNG CHÍNH (ẢNH SẢN PHẨM & CÁC CHỨC NĂNG MUA HÀNG)
         ========================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- CỘT TRÁI -->
        <div class="lg:col-span-7 flex flex-col gap-4">
            <div
                class="border border-gray-200 rounded-lg p-4 flex items-center justify-center h-[350px] md:h-[450px] bg-white relative">
                <img src="<?= $product['image'] ?>" class="max-w-full max-h-full object-contain">
                <?php if ($product['old_price']):
                    $disc = round((($product['old_price'] - $product['price']) / $product['old_price']) * 100); ?>
                    <div
                        class="absolute top-4 left-4 bg-gradient-to-r from-red-500 to-red-600 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md">
                        -<?= $disc ?>%</div>
                <?php endif; ?>
            </div>

            <div
                class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[13px] text-gray-700 bg-white p-4 border border-gray-200 rounded-lg">
                <div class="flex items-start gap-2"><i class="fa-solid fa-rotate text-primary mt-1 text-base w-5"></i>
                    <p><?= __("return_policy_hint") ?></p>
                </div>
                <div class="flex items-start gap-2"><i
                        class="fa-solid fa-shield-halved text-primary mt-1 text-base w-5"></i>
                    <p><?= __("warranty_hint") ?></p>
                </div>
            </div>
        </div>

        <!-- CỘT PHẢI -->
        <div class="lg:col-span-5 flex flex-col gap-4">

            <!-- Box Giá -->
            <div class="bg-gray-50 rounded-lg p-5 border border-gray-200 shadow-sm relative overflow-hidden">
                <p class="text-gray-500 text-sm font-medium mb-1 uppercase tracking-wider"><?= __("online_price") ?></p>
                <div class="flex items-end gap-3 mb-2">
                    <span class="text-4xl font-extrabold text-danger"><?= number_format($product['price']) ?>đ</span>
                </div>
                <?php if ($product['old_price']): ?>
                    <div class="flex items-center gap-2">
                        <span
                            class="text-sm md:text-base text-gray-500 line-through"><?= number_format($product['old_price']) ?>đ</span>
                        <span
                            class="text-[11px] bg-red-100 text-red-600 font-bold px-2 py-0.5 rounded border border-red-200"><?= __("discount_prefix") ?>
                            <?= $disc ?? 0 ?>%</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Box Khuyến Mãi -->
            <?php if ($product['gift_text']):
                $gifts = array_filter(explode(';', $product['gift_text']));
                ?>
                <div class="border border-red-200 rounded-lg overflow-hidden shadow-sm">
                    <div
                        class="bg-red-50 px-4 py-3 border-b border-red-200 text-danger font-bold text-[15px] flex items-center gap-2">
                        <i class="fa-solid fa-gift text-lg"></i> <?= __("promo_value_hint") ?> 500.000đ
                    </div>
                    <div class="p-4 text-[13.5px] text-gray-700 bg-white flex flex-col gap-3">
                        <?php foreach ($gifts as $index => $gift): ?>
                            <div class="flex items-start gap-2.5">
                                <div
                                    class="w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center shrink-0 font-bold text-[10px]">
                                    <?= $index + 1 ?></div>
                                <span class="leading-tight"><?= htmlspecialchars(getCurrentLang() === 'en' ? translate_text(trim($gift), 'prod_gift_' . $product['id'] . '_' . $index) : trim($gift)) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- CÁC NÚT MUA HÀNG TÍCH HỢP AJAX -->
            <div class="flex flex-col gap-3 mt-2">
                <div class="flex gap-3 h-[60px]">
                    <button type="button" onclick="addToCartAjax(<?= $id ?>)"
                        class="flex-1 bg-white border border-[#2e7dd6] text-[#2e7dd6] rounded-lg hover:bg-blue-50 transition shadow-sm flex flex-col items-center justify-center text-center">
                        <i class="fa-solid fa-cart-plus text-lg mb-0.5"></i>
                        <span class="text-[14px] font-medium leading-tight"><?= __("add_to_cart") ?></span>
                    </button>
                    <button type="button" onclick="buyNowAjax(<?= $id ?>)"
                        class="flex-1 bg-[#ff7a00] text-white rounded-lg hover:bg-[#e66e00] transition shadow-sm flex flex-col items-center justify-center text-center">
                        <span class="font-medium text-[16px] leading-tight"><?= __("buy_now") ?></span>
                    </button>
                </div>
                <div class="flex gap-2">
                    <button type="button"
                        onclick="openInstallmentModal()"
                        class="flex-1 bg-[#2e7dd6] text-white rounded-lg py-2.5 hover:bg-[#2368b8] transition shadow-sm flex flex-col items-center justify-center">
                        <span class="font-medium text-[15px] mb-0.5"><?= __("buy_installment") ?> <i
                                class="fa-solid fa-angle-right text-[12px]"></i></span>
                        <span class="text-[12px] font-normal opacity-90"><?= __("callback_hint") ?></span>
                    </button>
                    <button type="button" onclick="toggleCompare(<?= $id ?>, this)"
                        class="w-[60px] bg-white border border-gray-200 text-gray-600 rounded-lg hover:border-primary hover:text-primary transition shadow-sm flex items-center justify-center text-lg"
                        title="<?= __("add_to_compare") ?>">
                        <i class="fa-solid fa-right-left"></i>
                    </button>
                    <button type="button" onclick="toggleWishlistAjax(<?= $id ?>, this)"
                        id="btn-wishlist-<?= $id ?>"
                        class="w-[60px] bg-white border border-gray-200 text-pink-500 rounded-lg hover:bg-pink-50 transition shadow-sm flex items-center justify-center text-lg"
                        title="<?= __("wishlist") ?>">
                        <i class="fa-regular fa-heart"></i>
                    </button>
                </div>
            </div>

            <div class="text-center text-[13px] text-gray-600 mt-2">
                <?= __("call_to_buy") ?> <a href="tel:18001061" class="text-primary font-bold hover:underline">1800.1061</a> (7:30 -
                22:00)
            </div>
        </div>
    </div>

    <!-- =========================================================
         MÔ TẢ & THÔNG SỐ KỸ THUẬT SẢN PHẨM
         ========================================================= -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mt-8">
        <div class="lg:col-span-7 bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-8">
            <h2 class="text-[18px] font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200"><?= __("highlights") ?></h2>
            <div id="desc-container" class="relative overflow-hidden" style="max-height: 350px;">
                <div class="prose prose-sm max-w-none text-gray-700 leading-relaxed text-[15px] text-justify">
                    <?= translate_html_content($product['description'] ? $product['description'] : '<p>Chưa có thông tin mô tả chi tiết cho sản phẩm này.</p>', 'prod_desc_' . $product['id']) ?>
                    <img src="<?= $product['image'] ?>"
                         class="w-full max-w-[500px] mx-auto my-6 rounded-lg border border-gray-100"
                         alt="<?= $product['name'] ?>">
                </div>
                <div id="desc-gradient"
                     class="absolute bottom-0 left-0 w-full h-24 bg-gradient-to-t from-white to-transparent"></div>
            </div>
            <button id="btn-read-more" onclick="toggleDescription()"
                    class="mt-4 w-full text-primary border border-primary hover:bg-blue-50 py-2 rounded-lg text-[14px] font-medium transition"><?= __("read_more") ?></button>
        </div>

        <div class="lg:col-span-5">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-6 sticky top-20">
                <h2 class="text-[18px] font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200"><?= __("specifications") ?>
                </h2>
                <div class="text-[14px] text-gray-700 specs-table overflow-hidden" style="max-height: 300px;">
                    <?= translate_html_content($product['specifications'] ? $product['specifications'] : '<p>' . __("no_specifications") . '</p>', 'prod_specs_' . $product['id']) ?>
                </div>
                <style>
                    .specs-table ul {
                        padding: 0;
                        margin: 0;
                        list-style: none;
                        display: flex;
                        flex-direction: column;
                    }

                    .specs-table li {
                        padding: 12px 15px;
                        display: flex;
                        gap: 10px;
                        border-bottom: 1px solid #f1f2f6;
                    }

                    .specs-table li:nth-child(odd) {
                        background-color: #f9fafb;
                    }
                </style>
                <button onclick="document.getElementById('specsModal').classList.remove('hidden')"
                    class="mt-4 w-full text-primary border border-primary hover:bg-blue-50 py-2 rounded-lg text-[14px] font-medium transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-list"></i> <?= __("view_detailed_specs") ?>
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================
         ĐÁNH GIÁ VÀ NHẬN XÉT SẢN PHẨM 
         ========================================================= -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-8 mt-8" id="reviews">
        <h2 class="text-[18px] font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200"><?= __("reviews_comments") ?>
            <?= htmlspecialchars($product['name']) ?></h2>

        <!-- PHẦN TỔNG HỢP ĐÁNH GIÁ -->
        <div class="flex flex-col md:flex-row gap-6 items-center border-b border-gray-100 pb-6 mb-6">
            <!-- Điểm trung bình -->
            <div class="flex flex-col items-center md:w-1/4 shrink-0">
                <div class="text-5xl font-extrabold text-primary mb-1"><?= number_format($reviewStats['avg'], 1) ?>
                </div>
                <div class="flex text-lg mb-1 gap-0.5">
                    <?php
                    $avgStar = $reviewStats['avg'];
                    for ($i = 1; $i <= 5; $i++):
                        if ($i <= floor($avgStar)): ?>
                            <i class="fa-solid fa-star text-yellow-400"></i>
                        <?php elseif ($i - $avgStar < 1 && $i - $avgStar > 0): ?>
                            <i class="fa-solid fa-star-half-stroke text-yellow-400"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-star text-gray-200"></i>
                        <?php endif; endfor; ?>
                </div>
                <div class="text-sm text-gray-500 font-medium"><?= $reviewStats['total'] ?> <?= __("reviews_count") ?></div>
            </div>

            <!-- Biểu đồ phân phối sao -->
            <div class="flex-1 w-full max-w-sm">
                <?php for ($s = 5; $s >= 1; $s--):
                    $count = $reviewStats['dist'][$s];
                    $pct = $reviewStats['total'] > 0 ? round(($count / $reviewStats['total']) * 100) : 0;
                    ?>
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="text-xs font-bold text-gray-600 w-8 text-right"><?= $s ?> <i
                                class="fa-solid fa-star text-yellow-400 text-[10px]"></i></span>
                        <div class="flex-1 h-2.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 <?= $s >= 4 ? 'bg-green-400' : ($s === 3 ? 'bg-yellow-400' : 'bg-orange-400') ?>"
                                style="width: <?= $pct ?>%"></div>
                        </div>
                        <span class="text-[11px] text-gray-400 w-12"><?= $count ?> <span
                                class="hidden sm:inline"><?= __("results") ?></span></span>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Nút gửi đánh giá -->
            <div class="flex flex-col items-center justify-center shrink-0">
                <p class="text-sm text-gray-600 mb-3 text-center"><?= __("invite_review") ?>?</p>
                <button onclick="toggleReviewForm()"
                    class="bg-primary text-white py-2.5 px-8 rounded-lg text-sm font-bold shadow-md hover:bg-blue-800 transition flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square"></i> <?= __("send_review") ?>
                </button>
            </div>
        </div>

        <!-- FORM ĐÁNH GIÁ -->
        <div id="review-form-container"
            class="hidden mb-8 max-w-2xl mx-auto bg-gray-50 p-5 rounded-xl border border-gray-200 shadow-sm">
            <?php if (isset($_SESSION['user_id'])): ?>
                <form id="main-review-form" method="POST" action="product_detail.php?id=<?= $id ?>" enctype="multipart/form-data">
                    <?= csrf_input_field() ?>
                    <h4 class="font-bold mb-4 text-center text-gray-800"><?= __("invite_review") ?></h4>

                    <!-- Chọn sao -->
                    <div class="flex justify-center gap-2 mb-1 text-3xl text-gray-300">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fa-solid fa-star cursor-pointer star-select transition hover:scale-125"
                                id="star_<?= $i ?>" onclick="setRating(<?= $i ?>)"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-center text-sm text-gray-500 mb-4" id="rating-text"><?= __("excellent") ?></p>

                    <input type="hidden" name="rating" id="input_rating" value="5">
                    <textarea name="comment" required rows="5"
                        class="w-full p-4 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:outline-none mb-4 text-sm resize-none shadow-sm"
                        placeholder="<?= __("review_placeholder") ?>"></textarea>

                    <!-- Upload ảnh/video -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><i class="fa-solid fa-camera mr-1"></i>
                            <?= __("media_upload_label") ?></label>
                        <div id="media-drop-zone"
                            class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-primary hover:bg-blue-50/30 transition relative">
                            <input type="file" name="review_media[]" id="review-media-input" multiple
                                accept="image/*,video/mp4,video/webm,video/quicktime"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                onchange="previewMedia(this)">
                            <div id="media-placeholder">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-300 mb-2"></i>
                                <p class="text-sm text-gray-500"><?= __("drag_drop_media") ?></p>
                                <p class="text-xs text-gray-400 mt-1"><?= __("media_support_hint") ?></p>
                            </div>
                        </div>
                        <div id="media-preview" class="flex flex-wrap gap-2 mt-3"></div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="toggleReviewForm()"
                            class="px-4 py-2 text-gray-500 hover:bg-gray-200 rounded-lg text-sm font-medium transition"><?= __("cancel") ?></button>
                        <button type="submit" name="submit_review"
                            class="bg-primary text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-blue-800 transition shadow-md flex items-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i> <?= __("send_review") ?>
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fa-solid fa-circle-exclamation text-yellow-500 text-3xl mb-2"></i>
                    <p class="text-gray-700 mb-3"><?= __("login_to_review") ?></p>
                    <button onclick="document.getElementById('loginModal').classList.remove('hidden')"
                        class="bg-primary text-white py-2 px-6 rounded-lg text-sm font-bold shadow hover:bg-blue-800 transition"><?= __("login_now") ?></button>
                </div>
            <?php endif; ?>
        </div>

        <!-- DANH SÁCH ĐÁNH GIÁ -->
        <div id="reviews-list-container">
            <?php if (empty($reviews)): ?>
                <p class="text-center text-gray-500 italic py-4"><?= __("no_reviews") ?></p>
            <?php else:
                $totalReviews = count($reviews);
                foreach ($reviews as $index => $rev): ?>
                    <div class="review-item <?= $index >= 2 ? 'hidden' : '' ?>" data-index="<?= $index ?>">
                        <?= renderReviewItem($rev, $id) ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($totalReviews > 2): ?>
                    <div class="text-center mt-6" id="load-more-reviews-container">
                        <button onclick="loadMoreReviews()" class="px-8 py-2.5 border-2 border-primary text-primary rounded-full font-bold text-sm hover:bg-primary hover:text-white transition shadow-sm">
                            <?= sprintf(__("view_more_reviews"), $totalReviews - 2) ?>
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- =========================================================
         MODAL LIGHTBOX XEM ẢNH/VIDEO MEDIA
         ========================================================= -->
    <div id="mediaViewerModal"
        class="hidden fixed inset-0 bg-black/80 z-[200] flex items-center justify-center backdrop-blur-sm p-4"
        onclick="closeMediaViewer(event)">
        <button onclick="document.getElementById('mediaViewerModal').classList.add('hidden')"
            class="absolute top-4 right-4 text-white/80 hover:text-white text-3xl z-10 w-10 h-10 flex items-center justify-center"><i
                class="fa-solid fa-xmark"></i></button>
        <div id="mediaViewerContent" class="max-w-4xl max-h-[85vh] flex items-center justify-center"></div>
    </div>

    <!-- =========================================================
         SẢN PHẨM TƯƠNG TỰ (Alternative)
         ========================================================= -->
    <?php if (!empty($related)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-8 mt-8">
            <h2 class="text-lg font-bold text-gray-800 mb-4 uppercase border-b border-gray-100 pb-2"><?= __("similar_products") ?></h2>
            <div class="flex overflow-x-auto gap-3 pb-4 hide-scrollbar">
                <?php foreach ($related as $r):
                    $r_disc = $r['old_price'] ? round((($r['old_price'] - $r['price']) / $r['old_price']) * 100) : 0;
                    ?>
                    <a href="product_detail.php?id=<?= $r['id'] ?>"
                        class="min-w-[160px] md:min-w-[200px] w-[160px] md:w-[200px] flex-shrink-0 border border-gray-100 hover:border-primary p-3 rounded-lg group transition block shadow-sm hover:shadow-md">
                        <div class="h-32 flex items-center justify-center mb-3">
                            <img src="<?= $r['image'] ?>"
                                class="max-w-full max-h-full object-contain group-hover:scale-105 transition">
                        </div>
                        <h4
                            class="text-[13px] text-gray-800 line-clamp-2 h-10 leading-snug mb-1 group-hover:text-primary font-medium">
                            <?= htmlspecialchars($r['name']) ?></h4>
                        <div class="text-danger font-bold text-[15px]"><?= number_format($r['price']) ?>đ</div>
                        <?php if ($r_disc > 0): ?>
                            <div class="flex items-center gap-1 mt-1">
                                <span class="text-gray-400 text-[11px] line-through"><?= number_format($r['old_price']) ?>đ</span>
                                <span class="text-[10px] bg-red-100 text-red-600 px-1 rounded font-bold">-<?= $r_disc ?>%</span>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- =========================================================
         PHỤ KIỆN GỢI Ý (Cross-sell)
         ========================================================= -->
    <?php if (!empty($cross_sell_products)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-8 mt-8">
            <h2 class="text-lg font-bold text-gray-800 mb-4 uppercase border-b border-gray-100 pb-2"><?= __("cross_sell_title") ?></h2>
            <div class="flex overflow-x-auto gap-3 pb-4 hide-scrollbar">
                <?php foreach ($cross_sell_products as $r):
                    $r_disc = $r['old_price'] ? round((($r['old_price'] - $r['price']) / $r['old_price']) * 100) : 0;
                    $crossLabel = '';
                    if (!empty($r['discount_percent'])) {
                        $crossLabel = __("discount_prefix") . ' ' . round($r['discount_percent']) . '%';
                    } elseif (!empty($r['discount_amount'])) {
                        $crossLabel = __("discount_prefix") . ' ' . number_format($r['discount_amount']) . 'đ';
                    }
                    ?>
                    <a href="product_detail.php?id=<?= $r['id'] ?>"
                        class="min-w-[160px] md:min-w-[200px] w-[160px] md:w-[200px] flex-shrink-0 border border-gray-100 hover:border-primary p-3 rounded-lg group transition block shadow-sm hover:shadow-md">
                        <div class="relative h-32 flex items-center justify-center mb-3">
                            <?php if ($crossLabel): ?>
                                <span class="absolute top-2 left-2 bg-yellow-100 text-yellow-900 text-[10px] px-2 py-1 rounded-full font-semibold z-10"><?= htmlspecialchars($crossLabel) ?></span>
                            <?php endif; ?>
                            <img src="<?= $r['image'] ?>"
                                class="max-w-full max-h-full object-contain group-hover:scale-105 transition">
                        </div>
                        <h4
                            class="text-[13px] text-gray-800 line-clamp-2 h-10 leading-snug mb-1 group-hover:text-primary font-medium">
                            <?= htmlspecialchars($r['name']) ?></h4>
                        <div class="text-danger font-bold text-[15px]"><?= number_format($r['price']) ?>đ</div>
                        <?php if ($r_disc > 0): ?>
                            <div class="flex items-center gap-1 mt-1">
                                <span class="text-gray-400 text-[11px] line-through"><?= number_format($r['old_price']) ?>đ</span>
                                <span class="text-[10px] bg-red-100 text-red-600 px-1 rounded font-bold">-<?= $r_disc ?>%</span>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- =========================================================
     MODALS HTML LAYER
     ========================================================= -->

<!-- MODAL CẤU HÌNH CHI TIẾT -->
<div id="specsModal"
    class="hidden fixed inset-0 bg-black/60 z-[100] flex items-center justify-center backdrop-blur-sm px-4">
    <div class="bg-white rounded-xl w-full max-w-[600px] max-h-[85vh] flex flex-col relative shadow-2xl">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50 rounded-t-xl">
            <h3 class="font-bold text-lg text-gray-800"><?= __("view_detailed_specs") ?></h3>
            <button onclick="document.getElementById('specsModal').classList.add('hidden')"
                class="text-gray-400 hover:text-red-500 transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-red-50"><i
                    class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div class="p-6 overflow-y-auto specs-table">
            <h4 class="font-bold text-primary mb-3"><?= htmlspecialchars($product['name']) ?></h4>
            <?= translate_html_content($product['specifications'] ? $product['specifications'] : '<p>' . __("no_specifications") . '</p>', 'prod_specs_' . $product['id']) ?>
        </div>
    </div>
</div>

<!-- MODAL TRẢ GÓP -->
<div id="installmentModal" class="hidden fixed inset-0 bg-black/60 z-[100] flex items-center justify-center backdrop-blur-sm px-4 py-8 overflow-y-auto">
    <div class="bg-white rounded-2xl w-full max-w-[750px] flex flex-col relative shadow-2xl border border-gray-100 max-h-[90vh] overflow-hidden transition-all duration-300 transform scale-95 opacity-0" id="installmentModalContainer">
        
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 shrink-0">
            <h3 class="font-extrabold text-xl text-gray-900 flex items-center gap-2">
                <i class="fa-solid fa-credit-card text-primary text-lg"></i>
                <?= __("installment_info") ?>
            </h3>
            <button onclick="closeInstallmentModal()" class="text-gray-400 hover:text-red-500 transition w-9 h-9 flex items-center justify-center rounded-full hover:bg-red-50">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <!-- Scrollable Body -->
        <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
            
            <!-- Product Brief -->
            <div class="flex items-center gap-4 bg-gray-50 p-4 rounded-xl mb-6 border border-gray-100">
                <div class="w-16 h-16 shrink-0 bg-white rounded-lg p-1 border border-gray-100 flex items-center justify-center">
                    <img src="<?= htmlspecialchars($product['image']) ?>" class="w-full h-full object-contain" alt="<?= htmlspecialchars($product['name']) ?>">
                </div>
                <div>
                    <h4 class="font-bold text-gray-800 text-sm md:text-base line-clamp-1"><?= htmlspecialchars($product['name']) ?></h4>
                    <p class="text-lg font-black text-red-600 mt-0.5" id="installmentProductPrice" data-price="<?= $product['price'] ?>">
                        <?= number_format($product['price']) ?>đ
                    </p>
                </div>
            </div>

            <!-- Tabs at top -->
            <div class="grid grid-cols-3 gap-2 p-1 bg-gray-100 rounded-xl mb-6 shrink-0">
                <!-- Tab 1: Finance Company -->
                <button onclick="switchInstallmentTab(1)" id="inst-tab-1" class="flex flex-col items-center justify-center py-2.5 px-2 rounded-lg text-center transition-all bg-white text-primary shadow-sm font-bold border border-transparent">
                    <span class="text-xs md:text-sm font-black"><?= __("pay_finance_company") ?></span>
                    <span class="text-[10px] text-gray-400 font-medium"><?= __("pay_finance_company_desc") ?></span>
                </button>
                <!-- Tab 2: Credit Card -->
                <button onclick="switchInstallmentTab(2)" id="inst-tab-2" class="flex flex-col items-center justify-center py-2.5 px-2 rounded-lg text-center transition-all text-gray-500 hover:text-gray-800 font-bold border border-transparent">
                    <span class="text-xs md:text-sm font-black"><?= __("pay_credit_card") ?></span>
                    <span class="text-[10px] text-gray-400 font-medium"><?= __("pay_credit_card_desc") ?></span>
                </button>
                <!-- Tab 3: Buy Now Pay Later -->
                <button onclick="switchInstallmentTab(3)" id="inst-tab-3" class="flex flex-col items-center justify-center py-2.5 px-2 rounded-lg text-center transition-all text-gray-500 hover:text-gray-800 font-bold border border-transparent">
                    <span class="text-xs md:text-sm font-black"><?= __("buy_now_pay_later") ?></span>
                    <span class="text-[10px] text-gray-400 font-medium"><?= __("buy_now_pay_later_desc") ?></span>
                </button>
            </div>

            <!-- TAB 1: Finance Company Content -->
            <div id="inst-content-1" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Choose Finance Company -->
                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2"><?= __("select_finance_company") ?></label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" onclick="selectFinanceCompany('Shinhan Finance')" id="comp-Shinhan" class="border-2 border-primary bg-blue-50/30 rounded-xl p-3 flex items-center justify-center font-bold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none h-12">
                                Shinhan Finance
                            </button>
                            <button type="button" onclick="selectFinanceCompany('Home Credit')" id="comp-Home" class="border-2 border-gray-100 rounded-xl p-3 flex items-center justify-center font-bold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none h-12">
                                Home Credit
                            </button>
                            <button type="button" onclick="selectFinanceCompany('HD Saison')" id="comp-HD" class="border-2 border-gray-100 rounded-xl p-3 flex items-center justify-center font-bold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none h-12">
                                HD Saison
                            </button>
                            <button type="button" onclick="selectFinanceCompany('Mirae Asset')" id="comp-Mirae" class="border-2 border-gray-100 rounded-xl p-3 flex items-center justify-center font-bold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none h-12">
                                Mirae Asset
                            </button>
                        </div>
                    </div>
                    
                    <!-- Choose Prepayment Percentage -->
                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2"><?= __("select_prepayment_percent") ?? 'Chọn mức trả trước (%)' ?></label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" onclick="selectPrepayPercent(10)" id="prepay-10" class="border-2 border-gray-100 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none">
                                10%
                            </button>
                            <button type="button" onclick="selectPrepayPercent(20)" id="prepay-20" class="border-2 border-gray-100 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none">
                                20%
                            </button>
                            <button type="button" onclick="selectPrepayPercent(30)" id="prepay-30" class="border-2 border-primary bg-blue-50/30 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none">
                                30%
                            </button>
                            <button type="button" onclick="selectPrepayPercent(40)" id="prepay-40" class="border-2 border-gray-100 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none">
                                40%
                            </button>
                            <button type="button" onclick="selectPrepayPercent(50)" id="prepay-50" class="border-2 border-gray-100 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none">
                                50%
                            </button>
                        </div>
                    </div>
                    
                    <!-- Choose Term -->
                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2"><?= __("select_installment_term") ?></label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" onclick="selectFinanceTerm(3)" id="term-3" class="border-2 border-primary bg-blue-50/30 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none">
                                3 <?= __("months_suffix") ?>
                            </button>
                            <button type="button" onclick="selectFinanceTerm(4)" id="term-4" class="border-2 border-gray-100 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none">
                                4 <?= __("months_suffix") ?>
                            </button>
                            <button type="button" onclick="selectFinanceTerm(6)" id="term-6" class="border-2 border-gray-100 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none">
                                6 <?= __("months_suffix") ?>
                            </button>
                            <button type="button" onclick="selectFinanceTerm(9)" id="term-9" class="border-2 border-gray-100 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none">
                                9 <?= __("months_suffix") ?>
                            </button>
                            <button type="button" onclick="selectFinanceTerm(12)" id="term-12" class="border-2 border-gray-100 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none">
                                12 <?= __("months_suffix") ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Finance Calculations Table -->
                <div class="border border-gray-100 rounded-2xl overflow-hidden bg-gray-50/30">
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all">
                        <span class="text-sm text-gray-500 font-medium"><?= __("company") ?></span>
                        <span class="text-sm font-bold text-gray-800 text-right" id="calc-company">Shinhan Finance</span>
                    </div>
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all">
                        <span class="text-sm text-gray-500 font-medium"><?= __("installment_price") ?></span>
                        <span class="text-sm font-bold text-gray-800 text-right" id="calc-price"><?= number_format($product['price']) ?>đ</span>
                    </div>
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all">
                        <span class="text-sm text-gray-500 font-medium"><?= __("prepayment") ?> (<span id="calc-prepay-percent">30</span>%)</span>
                        <span class="text-sm font-bold text-gray-800 text-right" id="calc-prepay">0đ</span>
                    </div>
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all">
                        <span class="text-sm text-gray-500 font-medium"><?= __("interest_rate") ?></span>
                        <span class="text-sm font-bold text-green-600 text-right" id="calc-interest-rate">0%</span>
                    </div>
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all">
                        <span class="text-sm text-gray-500 font-medium"><?= __("required_papers") ?></span>
                        <span class="text-xs font-bold text-gray-600 text-right"><?= __("required_papers_val") ?></span>
                    </div>
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all">
                        <span class="text-sm text-gray-500 font-medium"><?= __("monthly_installment_principal") ?? 'Tiền trả góp hàng tháng (Gốc)' ?></span>
                        <span class="text-sm font-bold text-red-600 text-right" id="calc-monthly">0đ</span>
                    </div>
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all">
                        <span class="text-sm text-gray-500 font-medium"><?= __("principal_interest") ?></span>
                        <span class="text-sm font-bold text-gray-800 text-right" id="calc-total-monthly">0đ</span>
                    </div>
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all">
                        <span class="text-sm text-gray-500 font-medium"><?= __("insurance_fee") ?></span>
                        <span class="text-sm font-bold text-gray-800 text-right">0đ</span>
                    </div>
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all bg-gray-50">
                        <span class="text-sm text-gray-600 font-black"><?= __("total_payment") ?></span>
                        <span class="text-sm font-black text-gray-800 text-right" id="calc-total">0đ</span>
                    </div>
                    <div class="grid grid-cols-2 p-3 hover:bg-white transition-all bg-red-50/20">
                        <span class="text-sm text-gray-600 font-black"><?= __("difference") ?></span>
                        <span class="text-sm font-black text-red-600 text-right" id="calc-diff">0đ</span>
                    </div>
                </div>
                <p class="text-center text-[10px] text-gray-400 mt-1 italic"><?= __("installment_disclaimer") ?></p>
            </div>

            <!-- TAB 2: Credit Card Content -->
            <div id="inst-content-2" class="hidden space-y-6">
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2"><?= __("select_installment_method") ?></label>
                    <div class="border-2 border-primary bg-blue-50/30 rounded-xl p-3 flex items-center justify-between font-bold text-sm text-gray-800 select-none">
                        <span><?= __("pay_via_onepay") ?></span>
                        <div class="flex gap-1 shrink-0">
                            <span class="bg-gray-100 text-gray-500 rounded px-1 text-[10px]">Visa</span>
                            <span class="bg-gray-100 text-gray-500 rounded px-1 text-[10px]">Master</span>
                            <span class="bg-gray-100 text-gray-500 rounded px-1 text-[10px]">JCB</span>
                        </div>
                    </div>
                </div>

                <!-- 1. Select bank -->
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2"><?= __("select_bank") ?></label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <button type="button" onclick="selectCreditBank('Vietcombank')" id="bank-Vietcombank" class="border-2 border-primary bg-blue-50/30 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 transition-all select-none">
                            Vietcombank
                        </button>
                        <button type="button" onclick="selectCreditBank('Techcombank')" id="bank-Techcombank" class="border-2 border-gray-100 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none">
                            Techcombank
                        </button>
                        <button type="button" onclick="selectCreditBank('Sacombank')" id="bank-Sacombank" class="border-2 border-gray-100 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none">
                            Sacombank
                        </button>
                        <button type="button" onclick="selectCreditBank('ACB')" id="bank-ACB" class="border-2 border-gray-100 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none">
                            ACB
                        </button>
                        <button type="button" onclick="selectCreditBank('MBBank')" id="bank-MBBank" class="border-2 border-gray-100 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none">
                            MBBank
                        </button>
                        <button type="button" onclick="selectCreditBank('VPBank')" id="bank-VPBank" class="border-2 border-gray-100 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none">
                            VPBank
                        </button>
                        <button type="button" onclick="selectCreditBank('VIB')" id="bank-VIB" class="border-2 border-gray-100 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none">
                            VIB
                        </button>
                        <button type="button" onclick="selectCreditBank('BIDV')" id="bank-BIDV" class="border-2 border-gray-100 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none">
                            BIDV
                        </button>
                        <button type="button" onclick="selectCreditBank('VietinBank')" id="bank-VietinBank" class="border-2 border-gray-100 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none">
                            VietinBank
                        </button>
                        <button type="button" onclick="selectCreditBank('TPBank')" id="bank-TPBank" class="border-2 border-gray-100 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none">
                            TPBank
                        </button>
                        <button type="button" onclick="selectCreditBank('HSBC')" id="bank-HSBC" class="border-2 border-gray-100 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none">
                            HSBC
                        </button>
                        <button type="button" onclick="selectCreditBank('Shinhan Bank')" id="bank-ShinhanBank" class="border-2 border-gray-100 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none">
                            Shinhan Bank
                        </button>
                    </div>
                </div>

                <!-- 2. Select card type -->
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2"><?= __("select_card_type") ?></label>
                    <div class="grid grid-cols-3 gap-2">
                        <button onclick="selectCreditCard('Visa')" id="card-Visa" class="border-2 border-primary bg-blue-50/30 rounded-xl py-2 flex items-center justify-center font-bold text-xs text-gray-700 transition-all select-none">
                            Visa
                        </button>
                        <button onclick="selectCreditCard('MasterCard')" id="card-MasterCard" class="border-2 border-gray-100 rounded-xl py-2 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none">
                            MasterCard
                        </button>
                        <button onclick="selectCreditCard('JCB')" id="card-JCB" class="border-2 border-gray-100 rounded-xl py-2 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none">
                            JCB
                        </button>
                    </div>
                </div>

                <!-- 3. Select Term and Rate -->
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2"><?= __("select_term_rate") ?></label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <button type="button" onclick="selectCreditTerm(3)" id="cterm-3" class="border-2 border-primary bg-blue-50/30 rounded-xl p-3 flex flex-col items-center justify-center transition-all select-none">
                            <span class="text-sm font-extrabold text-gray-800">3 <?= __("months_suffix") ?></span>
                            <span class="text-[10px] text-green-600 font-bold">0% Lãi suất</span>
                        </button>
                        <button type="button" onclick="selectCreditTerm(6)" id="cterm-6" class="border-2 border-gray-100 rounded-xl p-3 flex flex-col items-center justify-center transition-all select-none">
                            <span class="text-sm font-extrabold text-gray-855">6 <?= __("months_suffix") ?></span>
                            <span class="text-[10px] text-red-600 font-bold">0.58% / tháng</span>
                        </button>
                        <button type="button" onclick="selectCreditTerm(9)" id="cterm-9" class="border-2 border-gray-100 rounded-xl p-3 flex flex-col items-center justify-center transition-all select-none">
                            <span class="text-sm font-extrabold text-gray-855">9 <?= __("months_suffix") ?></span>
                            <span class="text-[10px] text-red-600 font-bold">0.5% / tháng</span>
                        </button>
                        <button type="button" onclick="selectCreditTerm(12)" id="cterm-12" class="border-2 border-gray-100 rounded-xl p-3 flex flex-col items-center justify-center transition-all select-none">
                            <span class="text-sm font-extrabold text-gray-855">12 <?= __("months_suffix") ?></span>
                            <span class="text-[10px] text-red-600 font-bold">0.46% / tháng</span>
                        </button>
                    </div>
                </div>

                <!-- Credit Card Calculations Table -->
                <div class="border border-gray-100 rounded-2xl overflow-hidden bg-gray-50/30">
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all">
                        <span class="text-sm text-gray-500 font-medium"><?= __("bank") ?></span>
                        <span class="text-sm font-bold text-gray-800 text-right" id="cc-calc-bank">Vietcombank</span>
                    </div>
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all">
                        <span class="text-sm text-gray-500 font-medium"><?= __("card_type") ?></span>
                        <span class="text-sm font-bold text-gray-800 text-right" id="cc-calc-card">Visa</span>
                    </div>
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all">
                        <span class="text-sm text-gray-500 font-medium"><?= __("installment_price") ?></span>
                        <span class="text-sm font-bold text-gray-800 text-right"><?= number_format($product['price']) ?>đ</span>
                    </div>
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all">
                        <span class="text-sm text-gray-500 font-medium"><?= __("flat_interest_rate") ?></span>
                        <span class="text-sm font-extrabold text-red-600 text-right" id="cc-calc-rate">0.5% / tháng</span>
                    </div>
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all">
                        <span class="text-sm text-gray-500 font-medium"><?= __("monthly_payment_goclai") ?></span>
                        <span class="text-sm font-bold text-red-600 text-right" id="cc-calc-monthly">0đ</span>
                    </div>
                    <div class="grid grid-cols-2 border-b border-gray-100 p-3 hover:bg-white transition-all bg-gray-50">
                        <span class="text-sm text-gray-600 font-black"><?= __("total_payment") ?></span>
                        <span class="text-sm font-black text-gray-800 text-right" id="cc-calc-total">0đ</span>
                    </div>
                    <div class="grid grid-cols-2 p-3 hover:bg-white transition-all bg-red-50/20">
                        <span class="text-sm text-gray-600 font-black"><?= __("difference") ?></span>
                        <span class="text-sm font-black text-red-600 text-right" id="cc-calc-diff">0đ</span>
                    </div>
                </div>
                <p class="text-center text-[10px] text-gray-400 mt-1 italic"><?= __("installment_disclaimer") ?></p>
            </div>

            <!-- TAB 3: Buy Now Pay Later Content -->
            <div id="inst-content-3" class="hidden space-y-6">
                <!-- Select BNPL Provider -->
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2"><?= __("select_bnpl_provider") ?></label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <button type="button" onclick="selectBnplProvider('Home PayLater')" id="bnpl-HomePayLater" class="border-2 border-primary bg-blue-50/30 rounded-xl p-3 flex flex-col items-center justify-center font-bold text-sm text-gray-800 transition-all select-none">
                            <span class="text-sm font-extrabold text-gray-800">Home PayLater</span>
                            <span class="text-[10px] text-red-500 font-black uppercase mt-1">🔥 <?= __("hot_tag") ?></span>
                        </button>
                        <button type="button" onclick="selectBnplProvider('Fundiin')" id="bnpl-Fundiin" class="border-2 border-gray-100 rounded-xl p-3 flex flex-col items-center justify-center font-bold text-sm text-gray-800 hover:bg-gray-50 transition-all select-none">
                            <span class="text-sm font-extrabold text-gray-800">Fundiin</span>
                            <span class="text-[10px] text-blue-500 font-bold uppercase mt-1">⚡️ <?= __("popular_tag") ?></span>
                        </button>
                        <button type="button" onclick="selectBnplProvider('Kredivo')" id="bnpl-Kredivo" class="border-2 border-gray-100 rounded-xl p-3 flex flex-col items-center justify-center font-bold text-sm text-gray-800 hover:bg-gray-50 transition-all select-none">
                            <span class="text-sm font-extrabold text-gray-800">Kredivo</span>
                            <span class="text-[10px] text-gray-400 font-medium uppercase mt-1">💳 <?= __("global_tag") ?></span>
                        </button>
                    </div>
                </div>

                <!-- BNPL Description Box -->
                <div class="bg-gray-50 border border-gray-100 rounded-2xl p-5">
                    <h5 class="font-extrabold text-gray-800 text-base mb-2" id="bnpl-title">Home PayLater</h5>
                    <p class="text-sm text-gray-600 leading-relaxed" id="bnpl-desc">Home PayLater là dịch vụ mua trước trả sau cực HOT của Home Credit. Hạn mức lên đến 25 triệu, không cần chứng minh thu nhập, lãi suất 0% cho kỳ hạn ngắn, xét duyệt siêu tốc chỉ trong 60 giây.</p>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="mt-8 border-t border-gray-100 pt-6 space-y-4">
                <h5 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-1"><?= __("contact_info") ?></h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1"><?= __("fullname") ?> *</label>
                        <input type="text" id="inst-fullname" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-primary text-sm font-medium" value="<?= isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : '' ?>" placeholder="<?= __("fullname") ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1"><?= __("phone") ?> *</label>
                        <input type="tel" id="inst-phone" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-primary text-sm font-medium" placeholder="VD: 0987654321">
                    </div>
                </div>
            </div>

            <!-- Old to New Trade-In Switch (Common under all tabs) -->
            <div class="mt-4 space-y-2">
                <label class="flex items-center gap-3 cursor-pointer select-none group">
                    <input type="checkbox" id="inst-trade-in" class="w-5 h-5 accent-primary rounded cursor-pointer transition">
                    <span class="text-sm font-bold text-gray-700 group-hover:text-gray-900 transition-all">
                        <?= __("trade_in_prompt") ?>
                    </span>
                </label>
            </div>

        </div>

        <!-- Footer Actions -->
        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between gap-4 bg-gray-50/50 shrink-0">
            <button onclick="closeInstallmentModal()" class="px-6 py-3 border border-gray-200 text-gray-600 rounded-xl font-bold text-sm hover:bg-gray-100 transition shadow-sm bg-white min-w-[120px]">
                <?= __("close") ?>
            </button>
            <button onclick="submitNewInstallment()" class="flex-1 px-8 py-3 bg-red-600 text-white rounded-xl font-black text-sm hover:bg-red-700 transition shadow-lg shadow-red-200 text-center uppercase tracking-wider">
                <?= __("confirm_installment") ?>
            </button>
        </div>

    </div>
</div>

<script>
    let currentTab = 1;
    let selectedCompany = 'Shinhan Finance';
    let selectedTerm = 3;
    let selectedPrepayPercent = 30;
    let selectedBank = 'Vietcombank';
    let selectedCreditCard = 'Visa';
    let selectedCreditTerm = 3;
    let selectedBnpl = 'Home PayLater';

    const interestRatesMap = {
        'Shinhan Finance': { 3: 0, 4: 1.29, 6: 1.49, 9: 1.69, 12: 1.89 },
        'Home Credit': { 3: 0, 4: 1.39, 6: 1.59, 9: 1.79, 12: 1.99 },
        'HD Saison': { 3: 0, 4: 1.49, 6: 1.69, 9: 1.89, 12: 2.09 },
        'Mirae Asset': { 3: 0, 4: 1.59, 6: 1.79, 9: 1.99, 12: 2.19 }
    };

    const bnplData = {
        'Home PayLater': {
            title: 'Home PayLater',
            desc: `<?= __("home_paylater_desc") ?>`
        },
        'Fundiin': {
            title: 'Fundiin',
            desc: `<?= __("fundiin_desc") ?>`
        },
        'Kredivo': {
            title: 'Kredivo',
            desc: `<?= __("kredivo_desc") ?>`
        }
    };

    function openInstallmentModal() {
        const modal = document.getElementById('installmentModal');
        const container = document.getElementById('installmentModalContainer');
        modal.classList.remove('hidden');
        setTimeout(() => {
            container.classList.remove('scale-95', 'opacity-0');
            container.classList.add('scale-100', 'opacity-100');
        }, 10);
        
        selectedCompany = 'Shinhan Finance';
        selectedTerm = 3;
        selectedPrepayPercent = 30;
        selectedBnpl = 'Home PayLater';
        selectedBank = 'Vietcombank';
        selectedCreditCard = 'Visa';
        selectedCreditTerm = 3;
        
        selectFinanceCompany('Shinhan Finance');
        selectPrepayPercent(30);
        selectFinanceTerm(3);
        selectCreditBank('Vietcombank');
        selectCreditCard('Visa');
        selectCreditTerm(3);
        selectBnplProvider('Home PayLater');
        recalcInstallment();
        
        modal.onclick = function(e) {
            if (e.target === modal) {
                closeInstallmentModal();
            }
        };
    }

    function closeInstallmentModal() {
        const modal = document.getElementById('installmentModal');
        const container = document.getElementById('installmentModalContainer');
        container.classList.remove('scale-100', 'opacity-100');
        container.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    function switchInstallmentTab(tabNum) {
        currentTab = tabNum;
        for (let i = 1; i <= 3; i++) {
            const tabEl = document.getElementById('inst-tab-' + i);
            const contentEl = document.getElementById('inst-content-' + i);
            if (i === tabNum) {
                tabEl.className = "flex flex-col items-center justify-center py-2.5 px-2 rounded-lg text-center transition-all bg-white text-primary shadow-sm font-bold border border-transparent";
                contentEl.classList.remove('hidden');
            } else {
                tabEl.className = "flex flex-col items-center justify-center py-2.5 px-2 rounded-lg text-center transition-all text-gray-500 hover:text-gray-800 font-bold border border-transparent";
                contentEl.classList.add('hidden');
            }
        }
        recalcInstallment();
    }

    function selectFinanceCompany(compName) {
        selectedCompany = compName;
        const companies = ['Shinhan Finance', 'Home Credit', 'HD Saison', 'Mirae Asset'];
        const compMap = {
            'Shinhan Finance': 'Shinhan',
            'Home Credit': 'Home',
            'HD Saison': 'HD',
            'Mirae Asset': 'Mirae'
        };
        companies.forEach(c => {
            const btn = document.getElementById('comp-' + compMap[c]);
            if (btn) {
                if (c === compName) {
                    btn.className = "border-2 border-primary bg-blue-50/30 rounded-xl p-3 flex items-center justify-center font-bold text-sm text-gray-700 transition-all select-none h-12";
                } else {
                    btn.className = "border-2 border-gray-100 rounded-xl p-3 flex items-center justify-center font-bold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none h-12";
                }
            }
        });
        recalcInstallment();
    }

    function selectPrepayPercent(percent) {
        selectedPrepayPercent = percent;
        const percents = [10, 20, 30, 40, 50];
        percents.forEach(p => {
            const btn = document.getElementById('prepay-' + p);
            if (btn) {
                if (p === percent) {
                    btn.className = "border-2 border-primary bg-blue-50/30 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 transition-all select-none";
                } else {
                    btn.className = "border-2 border-gray-100 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none";
                }
            }
        });
        recalcInstallment();
    }

    function selectFinanceTerm(months) {
        selectedTerm = months;
        const terms = [3, 4, 6, 9, 12];
        terms.forEach(t => {
            const btn = document.getElementById('term-' + t);
            if (btn) {
                if (t === months) {
                    btn.className = "border-2 border-primary bg-blue-50/30 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 transition-all select-none";
                } else {
                    btn.className = "border-2 border-gray-100 rounded-xl py-2 flex items-center justify-center font-extrabold text-sm text-gray-700 hover:bg-gray-50 transition-all select-none";
                }
            }
        });
        recalcInstallment();
    }

    function selectCreditBank(bankName) {
        selectedBank = bankName;
        const banks = ['Vietcombank', 'Techcombank', 'Sacombank', 'ACB', 'MBBank', 'VPBank', 'VIB', 'BIDV', 'VietinBank', 'TPBank', 'HSBC', 'ShinhanBank'];
        banks.forEach(b => {
            const btn = document.getElementById('bank-' + b);
            if (btn) {
                const isSelected = (bankName === b || (bankName === 'Shinhan Bank' && b === 'ShinhanBank'));
                if (isSelected) {
                    btn.className = "border-2 border-primary bg-blue-50/30 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 transition-all select-none";
                } else {
                    btn.className = "border-2 border-gray-100 rounded-xl p-2.5 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none";
                }
            }
        });
        recalcInstallment();
    }

    function selectCreditCard(cardName) {
        selectedCreditCard = cardName;
        const cards = ['Visa', 'MasterCard', 'JCB'];
        cards.forEach(c => {
            const btn = document.getElementById('card-' + c);
            if (btn) {
                if (c === cardName) {
                    btn.className = "border-2 border-primary bg-blue-50/30 rounded-xl py-2 flex items-center justify-center font-bold text-xs text-gray-700 transition-all select-none";
                } else {
                    btn.className = "border-2 border-gray-100 rounded-xl py-2 flex items-center justify-center font-bold text-xs text-gray-700 hover:bg-gray-50 transition-all select-none";
                }
            }
        });
        recalcInstallment();
    }

    function selectCreditTerm(months) {
        selectedCreditTerm = months;
        const terms = [3, 6, 9, 12];
        terms.forEach(t => {
            const btn = document.getElementById('cterm-' + t);
            if (btn) {
                if (t === months) {
                    btn.className = "border-2 border-primary bg-blue-50/30 rounded-xl p-3 flex flex-col items-center justify-center transition-all select-none";
                } else {
                    btn.className = "border-2 border-gray-100 rounded-xl p-3 flex flex-col items-center justify-center transition-all select-none";
                }
            }
        });
        recalcInstallment();
    }

    function selectBnplProvider(providerName) {
        selectedBnpl = providerName;
        const providers = ['Home PayLater', 'Fundiin', 'Kredivo'];
        providers.forEach(p => {
            const idSuffix = p.replace(/\s+/g, '');
            const btn = document.getElementById('bnpl-' + idSuffix);
            if (btn) {
                if (p === providerName) {
                    btn.className = "border-2 border-primary bg-blue-50/30 rounded-xl p-3 flex flex-col items-center justify-center font-bold text-sm text-gray-800 transition-all select-none";
                } else {
                    btn.className = "border-2 border-gray-100 rounded-xl p-3 flex flex-col items-center justify-center font-bold text-sm text-gray-800 hover:bg-gray-50 transition-all select-none";
                }
            }
        });
        
        const data = bnplData[providerName];
        if (data) {
            document.getElementById('bnpl-title').innerText = data.title;
            document.getElementById('bnpl-desc').innerText = data.desc;
        }
    }

    function recalcInstallment() {
        const price = parseFloat(document.getElementById('installmentProductPrice').getAttribute('data-price'));
        
        // 1. Finance Company calculations (Tab 1)
        document.getElementById('calc-company').innerText = selectedCompany;
        document.getElementById('calc-prepay-percent').innerText = selectedPrepayPercent;
        
        const prepayAmount = Math.round(price * (selectedPrepayPercent / 100));
        document.getElementById('calc-prepay').innerText = prepayAmount.toLocaleString('vi-VN') + 'đ';
        
        const remainingPrincipal = price - prepayAmount;
        const rates = interestRatesMap[selectedCompany] || { 3: 0, 4: 0, 6: 0, 9: 0, 12: 0 };
        const monthlyInterestRate = rates[selectedTerm] || 0;
        document.getElementById('calc-interest-rate').innerText = monthlyInterestRate + '%<?= __("per_month_suffix") ?>';
        
        const monthlyPrincipal = remainingPrincipal / selectedTerm;
        const monthlyInterest = remainingPrincipal * (monthlyInterestRate / 100);
        const monthlyAmt = Math.round(monthlyPrincipal + monthlyInterest);
        
        document.getElementById('calc-monthly').innerText = Math.round(monthlyPrincipal).toLocaleString('vi-VN') + 'đ';
        document.getElementById('calc-total-monthly').innerText = monthlyAmt.toLocaleString('vi-VN') + 'đ';
        
        const totalToPay = prepayAmount + (monthlyAmt * selectedTerm);
        document.getElementById('calc-total').innerText = totalToPay.toLocaleString('vi-VN') + 'đ';
        
        const difference = totalToPay - price;
        document.getElementById('calc-diff').innerText = difference.toLocaleString('vi-VN') + 'đ';

        // 2. Credit Card calculations (Tab 2)
        const ccBankEl = document.getElementById('cc-calc-bank');
        if (ccBankEl) {
            ccBankEl.innerText = selectedBank;
            document.getElementById('cc-calc-card').innerText = selectedCreditCard;
            
            // Map flat monthly rates directly
            const ccMonthlyRatesMap = { 3: 0, 6: 0.58, 9: 0.5, 12: 0.46 };
            const ccFlatRate = ccMonthlyRatesMap[selectedCreditTerm] || 0;
            document.getElementById('cc-calc-rate').innerText = ccFlatRate + '%<?= __("per_month_suffix") ?>';
            
            const ccMonthlyAmt = Math.round((price / selectedCreditTerm) + (price * (ccFlatRate / 100)));
            document.getElementById('cc-calc-monthly').innerText = ccMonthlyAmt.toLocaleString('vi-VN') + 'đ';
            
            const ccTotalToPay = ccMonthlyAmt * selectedCreditTerm;
            document.getElementById('cc-calc-total').innerText = ccTotalToPay.toLocaleString('vi-VN') + 'đ';
            
            const ccDifference = ccTotalToPay - price;
            document.getElementById('cc-calc-diff').innerText = ccDifference.toLocaleString('vi-VN') + 'đ';
        }
    }

    function submitNewInstallment() {
        const fullname = document.getElementById('inst-fullname').value.trim();
        const phone = document.getElementById('inst-phone').value.trim();

        if (!fullname) {
            Swal.fire('<?php echo __('warning'); ?>', '<?php echo __('please_enter_fullname') ?? 'Vui lòng nhập họ tên' ?>', 'warning');
            return;
        }
        if (!phone || !/^[0-9]{10}$/.test(phone)) {
            Swal.fire('<?php echo __('warning'); ?>', '<?php echo __('please_enter_valid_phone') ?? 'Vui lòng nhập số điện thoại 10 số' ?>', 'warning');
            return;
        }

        const price = parseFloat(document.getElementById('installmentProductPrice').getAttribute('data-price'));
        const tradeInCheckbox = document.getElementById('inst-trade-in');
        const isTradeIn = tradeInCheckbox.checked ? 1 : 0;
        const tradeInText = isTradeIn ? ' (Thu cũ lên đời)' : '';

        let termText = '';
        let payment_method = '';
        let partner_name = '';
        let card_type = '';
        let prepayment_percent = 0;
        let prepayment_amount = 0;
        let term_months = 3;
        let monthly_payment = 0;
        let total_payment = 0;
        let difference_amount = 0;
        let interest_rate = 0;

        if (currentTab === 1) {
            payment_method = 'finance';
            partner_name = selectedCompany;
            prepayment_percent = selectedPrepayPercent;
            prepayment_amount = Math.round(price * (prepayment_percent / 100));
            term_months = selectedTerm;
            interest_rate = (interestRatesMap[selectedCompany] || {})[selectedTerm] || 0;
            
            const remainingPrincipal = price - prepayment_amount;
            const monthlyPrincipal = remainingPrincipal / term_months;
            const monthlyInterest = remainingPrincipal * (interest_rate / 100);
            monthly_payment = Math.round(monthlyPrincipal + monthlyInterest);
            total_payment = prepayment_amount + (monthly_payment * term_months);
            difference_amount = total_payment - price;
            
            termText = `Công ty tài chính: ${selectedCompany}, Trả trước: ${selectedPrepayPercent}%, Kỳ hạn: ${selectedTerm} tháng${tradeInText}`;
        } else if (currentTab === 2) {
            payment_method = 'credit_card';
            partner_name = selectedBank;
            card_type = selectedCreditCard;
            prepayment_percent = 0;
            prepayment_amount = 0;
            term_months = selectedCreditTerm;
            
            const ccMonthlyRatesMap = { 3: 0, 6: 0.58, 9: 0.5, 12: 0.46 };
            interest_rate = ccMonthlyRatesMap[selectedCreditTerm] || 0;
            monthly_payment = Math.round((price / term_months) + (price * (interest_rate / 100)));
            total_payment = monthly_payment * term_months;
            difference_amount = total_payment - price;
            
            termText = `Thẻ tín dụng: ${selectedBank} - ${selectedCreditCard}, Kỳ hạn: ${selectedCreditTerm} tháng (Lãi suất phẳng: ${interest_rate}%/tháng)${tradeInText}`;
        } else {
            payment_method = 'bnpl';
            partner_name = selectedBnpl;
            prepayment_percent = 0;
            prepayment_amount = 0;
            term_months = 3;
            interest_rate = 0;
            monthly_payment = Math.round(price / 3);
            total_payment = price;
            difference_amount = 0;
            
            termText = `Mua trước trả sau: ${selectedBnpl}${tradeInText}`;
        }

        const formData = new FormData();
        formData.append('product_id', <?= $id ?>);
        formData.append('term', termText);
        formData.append('fullname', fullname);
        formData.append('phone', phone);
        formData.append('payment_method', payment_method);
        formData.append('partner_name', partner_name);
        formData.append('card_type', card_type);
        formData.append('prepayment_percent', prepayment_percent);
        formData.append('prepayment_amount', prepayment_amount);
        formData.append('term_months', term_months);
        formData.append('monthly_payment', monthly_payment);
        formData.append('total_payment', total_payment);
        formData.append('difference_amount', difference_amount);
        formData.append('interest_rate', interest_rate);
        formData.append('is_trade_in', isTradeIn);
        formData.append('csrf_token', csrfToken);

        fetch(getApiUrl('save_installment.php'), {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeInstallmentModal();
                Swal.fire({
                    title: '<?php echo __('notification'); ?>',
                    text: '<?php echo __('installment_success_swal'); ?>',
                    icon: 'success',
                    timer: 3000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('<?php echo __('warning'); ?>', data.message || 'Lỗi đăng ký trả góp.', 'error');
            }
        })
        .catch(err => {
            Swal.fire('<?php echo __('warning'); ?>', 'Không thể gửi yêu cầu trả góp.', 'error');
        });
    }
</script>

<script>
    /**
     * MỞ RỘNG / THU GỌN MÔ TẢ SẢN PHẨM
     * Toggle chiều cao của container mô tả ngắn (350px) thành hiển thị đầy đủ (full).
     * Bật/tắt dải màu gradient che mờ phần dưới cùng.
     */
    function toggleDescription() {
        const container = document.getElementById('desc-container');
        const gradient = document.getElementById('desc-gradient');
        const btn = document.getElementById('btn-read-more');

        if (container.style.maxHeight) {
            container.style.maxHeight = null;
            gradient.style.display = 'none';
            btn.innerText = '<?= __("read_less") ?>';
        } else {
            container.style.maxHeight = '350px';
            gradient.style.display = 'block';
            btn.innerText = '<?= __("read_more") ?>';
        }
    }

    /**
     * ẨN / HIỆN FORM ĐÁNH GIÁ SẢN PHẨM
     * Xử lý ẩn/hiện và tự động cuộn trang (scrollInfoView) cho hộp thoại nhập review.
     */
    function toggleReviewForm() {
        const form = document.getElementById('review-form-container');
        form.classList.toggle('hidden');
        if (!form.classList.contains('hidden')) {
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    /**
     * ẨN / HIỆN FORM PHẢN HỒI ĐÁNH GIÁ
     */
    function toggleReplyForm(reviewId, userName = '') {
        const form = document.getElementById('reply-form-' + reviewId);
        if (form) {
            form.classList.toggle('hidden');
            if (!form.classList.contains('hidden')) {
                const textarea = form.querySelector('textarea');
                if (textarea) {
                    if (userName) {
                        textarea.value = '@' + userName + ' ';
                    }
                    textarea.focus();
                }
            }
        }
    }

    /**
     * ẨN / HIỆN DANH SÁCH PHẢN HỒI (KHI CÓ NHIỀU PHẢN HỒI)
     */
    function toggleReplies(reviewId) {
        const container = document.getElementById('replies-container-' + reviewId);
        const btn = document.getElementById('btn-show-replies-' + reviewId);
        if (container && btn) {
            container.classList.remove('hidden');
            btn.classList.add('hidden'); // Ẩn nút sau khi đã mở
        }
    }

    /**
     * TẢI THÊM ĐÁNH GIÁ (HIỆN CÁC ĐÁNH GIÁ ĐANG ẨN)
     */
    function loadMoreReviews() {
        const hiddenReviews = document.querySelectorAll('.review-item.hidden');
        hiddenReviews.forEach(el => el.classList.remove('hidden'));
        const btnContainer = document.getElementById('load-more-reviews-container');
        if (btnContainer) btnContainer.remove();
    }

    /**
     * TẢI LẠI DANH SÁCH ĐÁNH GIÁ QUA AJAX (KHÔNG RELOAD TRANG)
     */
    async function refreshReviewsList() {
        const container = document.getElementById('reviews-list-container');
        if (!container) return;
        try {
            const response = await fetch(`product_detail.php?id=<?= $id ?>&only_reviews=1`);
            const html = await response.text();
            container.innerHTML = html;
        } catch (error) {
            console.error('Lỗi khi tải lại danh sách đánh giá:', error);
        }
    }

    /**
     * XỬ LÝ GỬI ĐÁNH GIÁ/PHẢN HỒI QUA AJAX
     */
    async function handleReviewSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('ajax', '1');
        formData.append('submit_review', '1');

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang gửi...';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                // Hiển thị thông báo cảm ơn - Dạng pill sang xịn mịn tích hợp Nyan Cat ở vị trí checkmark
                Swal.fire({
                    html: `
                        <div class="flex items-center gap-3.5 px-5 py-2.5 bg-[#004bb9] rounded-full text-white shadow-[0_8px_30px_rgb(0,0,0,0.15)] border border-white/20 min-w-[300px] md:min-w-[450px] select-none">
                            <img src="https://sweetalert2.github.io/images/nyan-cat.gif" class="w-16 h-10 object-contain shrink-0 rounded-md" alt="Nyan Cat">
                            <span class="text-[14px] md:text-[14px] font-semibold text-left leading-snug"><?= __("thanks_for_comment") ?></span>
                        </div>
                    `,
                    background: 'transparent',
                    showConfirmButton: false,
                    timer: 3500,
                    backdrop: `rgba(0, 0, 0, 0.25)`, 
                    position: 'top',
                    customClass: {
                        popup: 'bg-transparent border-0 p-0 shadow-none'
                    },
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                });

                // Reset form và ẩn đi (nếu là form phản hồi)
                form.reset();
                if (form.closest('[id^="reply-form-"]')) {
                    form.closest('[id^="reply-form-"]').classList.add('hidden');
                }

                // Cập nhật lại danh sách đánh giá
                await refreshReviewsList();
            }
        } catch (error) {
            console.error('Lỗi khi gửi đánh giá:', error);
            Swal.fire('<?= __("error") ?>', '<?= __("review_submit_error") ?>', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }

    // Gắn sự kiện AJAX cho tất cả các form đánh giá (bao gồm cả các form mới được render qua AJAX)
    document.addEventListener('submit', function(e) {
        if (e.target && (e.target.id === 'main-review-form' || e.target.closest('[id^="reply-form-"]'))) {
            handleReviewSubmit(e);
        }
    });

    /**
     * HIỆN THÔNG BÁO CẢM ƠN SAU KHI ĐÁNH GIÁ THÀNH CÔNG (Dành cho trường hợp reload truyền thống nếu có)
     */
    <?php if (isset($_SESSION['review_success_msg'])): ?>
        Swal.fire({
            html: `
                <div class="flex items-center gap-3.5 px-5 py-2.5 bg-[#004bb9] rounded-full text-white shadow-[0_8px_30px_rgb(0,0,0,0.15)] border border-white/20 min-w-[300px] md:min-w-[450px] select-none">
                    <img src="https://sweetalert2.github.io/images/nyan-cat.gif" class="w-16 h-10 object-contain shrink-0 rounded-md" alt="Nyan Cat">
                    <span class="text-[14px] md:text-[15px] font-semibold text-left leading-snug"><?= __("thanks_for_comment") ?></span>
                </div>
            `,
            background: 'transparent',
            showConfirmButton: false,
            timer: 3500,
            backdrop: `rgba(0, 0, 0, 0.25)`, 
            position: 'top',
            customClass: {
                popup: 'bg-transparent border-0 p-0 shadow-none'
            },
            showClass: {
                popup: 'animate__animated animate__fadeInDown'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp'
            }
        });
        <?php unset($_SESSION['review_success_msg']); ?>
    <?php endif; ?>

    /**
     * CHỌN SỐ SAO CẢM NHẬN (TỪ 1 ĐẾN 5)
     * Nhận giá trị nguyên (rating), cập nhật màu sắc sao tương ứng, gán text và gắn vào input hidden.
     */
    const ratingLabels = { 
        1: '<?= __("very_bad") ?>', 
        2: '<?= __("bad") ?>', 
        3: '<?= __("normal") ?>', 
        4: '<?= __("good") ?>', 
        5: '<?= __("excellent") ?>' 
    };
    function setRating(rating) {
        document.getElementById('input_rating').value = rating;
        document.getElementById('rating-text').innerText = ratingLabels[rating];
        for (let i = 1; i <= 5; i++) {
            let star = document.getElementById('star_' + i);
            if (i <= rating) {
                star.classList.remove('text-gray-300');
                star.classList.add('text-yellow-400');
            } else {
                star.classList.remove('text-yellow-400');
                star.classList.add('text-gray-300');
            }
        }
    }
    setRating(5);

    /**
     * PREVIEW FILE MEDIA (ẢNH/VIDEO) TRƯỚC KHI UPLOAD
     * Sinh tự động vùng UI xem trước cho ảnh, hoặc khung player (không auto play) cho video MP4 sau khi chọn file từ máy tính.
     * Cảnh báo giới hạn số lượng <= 5 file.
     */
    function previewMedia(input) {
        const preview = document.getElementById('media-preview');
        const placeholder = document.getElementById('media-placeholder');
        preview.innerHTML = '';

        if (input.files.length > 5) {
            alert('<?= __("max_files_warning") ?>');
            input.value = '';
            return;
        }

        if (input.files.length > 0) {
            const filesSelectedText = '<?= __("files_selected") ?>'.replace('%s', input.files.length);
            const changeMediaText = '<?= __("change_media") ?>';
            placeholder.innerHTML = '<i class="fa-solid fa-circle-check text-green-500 text-2xl mb-1"></i><p class="text-sm text-green-600 font-medium">' + filesSelectedText + '</p><p class="text-xs text-gray-400 mt-1">' + changeMediaText + '</p>';
        }

        Array.from(input.files).forEach((file, idx) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'relative w-20 h-20 rounded-lg overflow-hidden border-2 border-gray-200 shadow-sm';

            if (file.type.startsWith('video/')) {
                const video = document.createElement('video');
                video.src = URL.createObjectURL(file);
                video.className = 'w-full h-full object-cover';
                wrapper.appendChild(video);
                const playIcon = document.createElement('div');
                playIcon.className = 'absolute inset-0 bg-black/30 flex items-center justify-center';
                playIcon.innerHTML = '<i class="fa-solid fa-play text-white"></i>';
                wrapper.appendChild(playIcon);
            } else {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.className = 'w-full h-full object-cover';
                wrapper.appendChild(img);
            }

            // File size badge
            const sizeBadge = document.createElement('div');
            const sizeMB = (file.size / 1024 / 1024).toFixed(1);
            sizeBadge.className = 'absolute bottom-0 left-0 right-0 bg-black/60 text-white text-[9px] text-center py-0.5';
            sizeBadge.innerText = sizeMB + ' MB';
            wrapper.appendChild(sizeBadge);

            preview.appendChild(wrapper);
        });
    }

    /**
     * LIGHTBOX VIEWER: POPUP XEM TO ẢNH / PHÁT VIDEO ĐÍNH KÈM
     * Phóng đại xem tập tin media trên 1 modal lightbox tối nền.
     */
    function openMediaViewer(src, isVideo) {
        const modal = document.getElementById('mediaViewerModal');
        const content = document.getElementById('mediaViewerContent');
        if (isVideo) {
            content.innerHTML = '<video src="' + src + '" controls autoplay class="max-w-full max-h-[80vh] rounded-lg shadow-2xl"></video>';
        } else {
            content.innerHTML = '<img src="' + src + '" class="max-w-full max-h-[80vh] rounded-lg shadow-2xl">';
        }
        modal.classList.remove('hidden');
    }
    function closeMediaViewer(e) {
        if (e.target === document.getElementById('mediaViewerModal')) {
            document.getElementById('mediaViewerModal').classList.add('hidden');
        }
    }

    /**
     * XÁC NHẬN XÓA ĐÁNH GIÁ SẢN PHẨM
     * Tạo UI modal overlay cảnh báo (không thể hoàn tác) bằng Javascript DOM Element tĩnh.
     * Nếu xác nhận: Sẽ trigger submit DOM form hidden chứa review_id tương ứng.
     */
    function confirmDeleteReview(reviewId) {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.id = 'delete-confirm-overlay';
        overlay.className = 'fixed inset-0 bg-black/50 z-[300] flex items-center justify-center backdrop-blur-sm p-4';
        overlay.style.animation = 'fadeIn 0.2s ease';

        overlay.innerHTML = `
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-[340px] p-6 text-center" style="animation: fadeIn 0.2s ease">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-trash-can text-red-500 text-2xl"></i>
                </div>
                <h3 class="font-bold text-gray-800 text-lg mb-2"><?= __("delete_review_title") ?></h3>
                <p class="text-sm text-gray-500 mb-5"><?= __("delete_review_warning") ?></p>
                <div class="flex gap-3">
                    <button onclick="document.getElementById('delete-confirm-overlay').remove()" class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium text-sm transition"><?= __("cancel") ?></button>
                    <button onclick="document.getElementById('delete-review-form-${reviewId}').submit()" class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-lg font-bold text-sm transition shadow-md"><?= __("confirm_delete") ?></button>
                </div>
            </div>
        `;

        // Close on overlay click
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.remove();
        });

        document.body.appendChild(overlay);
    }
</script>

<script>
    // Dữ liệu truyền ngầm cho AI PRO Chat
    const currentProductName = <?= json_encode($product['name']) ?>;
    const currentProductContext = `
        Tên: <?= htmlspecialchars($product['name']) ?>
        Hãng: <?= htmlspecialchars($product['brand_name']) ?>
        Giá bán: <?= number_format($product['price']) ?> VNĐ.
    `;
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>