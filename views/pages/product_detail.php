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
$meta_title = $product['name'] . " - Điện Máy PRO";
$meta_desc = mb_substr(strip_tags($product['description']), 0, 160) . "...";
$meta_image = $product['image'];

/**
 * XỬ LÝ DANH SÁCH ẢNH (GALLERY)
 * - Ảnh đầu tiên là ảnh chính (cột image).
 * - Các ảnh bổ sung nằm trong cột more_images (JSON array).
 */
$product_images = [$product['image']];
if (!empty($product['more_images'])) {
    $extra_images = json_decode($product['more_images'], true);
    if (is_array($extra_images)) {
        foreach($extra_images as $img) {
            if ($img !== $product['image']) {
                $product_images[] = $img;
            }
        }
    }
}


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
            <?= htmlspecialchars($product['name']) ?></h1>
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
            <!-- Khu vực hiển thị ảnh (Gallery) -->
            <div class="flex flex-col gap-4">
                <!-- Ảnh chính -->
                <div class="border border-gray-200 rounded-lg p-4 flex items-center justify-center h-[350px] md:h-[450px] bg-white relative overflow-hidden group">
                    <img id="main-product-image" src="<?= asset($product['image']) ?>" 
                         class="max-w-full max-h-full object-contain transition-all duration-500 ease-in-out group-hover:scale-105" 
                         alt="<?= htmlspecialchars($product['name']) ?>"
                         style="transition: opacity 0.4s ease, transform 0.4s ease, filter 0.4s ease;">
                    
                    <?php if ($product['old_price']):

                        $disc = round((($product['old_price'] - $product['price']) / $product['old_price']) * 100); ?>
                        <div class="absolute top-4 left-4 bg-gradient-to-r from-red-500 to-red-600 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md z-10">
                            -<?= $disc ?>%
                        </div>
                    <?php endif; ?>

                    <!-- Nút xem full ảnh -->
                    <button onclick="openMediaViewer(document.getElementById('main-product-image').src, false)" 
                        class="absolute bottom-4 right-4 w-10 h-10 bg-white/80 backdrop-blur shadow-md rounded-full flex items-center justify-center text-gray-600 hover:text-primary hover:bg-white transition opacity-0 group-hover:opacity-100">
                        <i class="fa-solid fa-expand"></i>
                    </button>
                </div>

                <!-- Danh sách ảnh thu nhỏ (Thumbnails) -->
                <?php if (count($product_images) > 1): ?>
                <div class="flex gap-2 overflow-x-auto pb-2 hide-scrollbar" id="thumbnail-container">
                    <?php foreach ($product_images as $index => $img_url): ?>
                    <div class="thumbnail-item shrink-0 w-20 h-20 border-2 <?= $index === 0 ? 'border-primary' : 'border-gray-100' ?> rounded-lg p-1 cursor-pointer hover:border-primary transition bg-white"
                         onclick="changeMainImage('<?= asset($img_url) ?>', this, true)">
                        <img src="<?= asset($img_url) ?>" class="w-full h-full object-contain" alt="thumbnail <?= $index + 1 ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
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
                                <span class="leading-tight"><?= htmlspecialchars(trim($gift)) ?></span>
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
                        onclick="document.getElementById('installmentModal').classList.remove('hidden')"
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
                    <img src="<?= asset($product['image']) ?>"
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
                    <?= translate_html_content($product['specifications'] ? $product['specifications'] : '<p>Chưa cập nhật thông số.</p>', 'prod_specs_' . $product['id']) ?>
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
        <h2 class="text-[18px] font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200"><?= __("order_history") ?> & <?= __("detail") ?>
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
                <form method="POST" action="product_detail.php?id=<?= $id ?>" enctype="multipart/form-data">
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
                    <textarea name="comment" required rows="3"
                        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:outline-none mb-3 text-sm resize-none"
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
<div id="installmentModal"
    class="hidden fixed inset-0 bg-black/60 z-[100] flex items-center justify-center backdrop-blur-sm px-4">
    <div class="bg-white rounded-xl w-full max-w-[400px] flex flex-col relative shadow-2xl">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50 rounded-t-xl">
            <h3 class="font-bold text-lg text-gray-800"><?= __("buy_installment") ?> </h3>
            <button onclick="document.getElementById('installmentModal').classList.add('hidden')"
                class="text-gray-400 hover:text-red-500 transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-red-50"><i
                    class="fa-solid fa-xmark text-xl"></i></button>
        </div>

        <form id="installmentForm" class="p-5" onsubmit="submitInstallment(event)">
            <input type="hidden" name="product_id" value="<?= $id ?>">
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __("fullname") ?> *</label>
                <input type="text" name="fullname" required
                    class="w-full px-3 py-2 border border-gray-300 rounded outline-none focus:ring-2 focus:ring-primary"
                    value="<?= isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : '' ?>">
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __("phone") ?> *</label>
                <input type="tel" name="phone" required pattern="[0-9]{10}"
                    class="w-full px-3 py-2 border border-gray-300 rounded outline-none focus:ring-2 focus:ring-primary"
                    placeholder="Nhập số điện thoại...">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __("desired_term") ?></label>
                <select name="term"
                    class="w-full px-3 py-2 border border-gray-300 rounded outline-none focus:ring-2 focus:ring-primary">
                    <option value="Gói 3 tháng (Lãi suất 0%)">Gói 3 tháng (Lãi suất 0%)</option>
                    <option value="Gói 6 tháng (Lãi suất 5%)">Gói 6 tháng (Lãi suất 5%)</option>
                    <option value="Gói 9 tháng (Lãi suất 10%)">Gói 9 tháng (Lãi suất 10%)</option>
                    <option value="Gói 12 tháng (Lãi suất 20%)">Gói 12 tháng (Lãi suất 20%)</option>
                </select>
            </div>
            <button type="submit"
                class="w-full bg-primary text-white font-bold py-2.5 rounded-lg hover:bg-blue-800 transition shadow"><?= __("confirm_registration") ?></button>
            <p class="text-center text-[11px] text-gray-500 mt-3"><?= __("callback_hint") ?></p>
        </form>
    </div>
</div>

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
     * CHỌN SỐ SAO CẢM NHẬN (TỪ 1 ĐẾN 5)
     */
    const ratingLabels = { 
        1: '<?= __("very_bad") ?>', 
        2: '<?= __("bad") ?>', 
        3: '<?= __("normal") ?>', 
        4: '<?= __("good") ?>', 
        5: '<?= __("excellent") ?>' 
    };
    function setRating(rating) {
        const input = document.getElementById('input_rating');
        if (input) input.value = rating;
        const text = document.getElementById('rating-text');
        if (text) text.innerText = ratingLabels[rating];
        for (let i = 1; i <= 5; i++) {
            let star = document.getElementById('star_' + i);
            if (star) {
                if (i <= rating) {
                    star.classList.remove('text-gray-300');
                    star.classList.add('text-yellow-400');
                } else {
                    star.classList.remove('text-yellow-400');
                    star.classList.add('text-gray-300');
                }
            }
        }
    }
    // Chỉ gọi setRating nếu có các ngôi sao đánh giá (tức là user đã login và form hiển thị)
    if (document.getElementById('star_1')) {
        setRating(5);
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
     * HIỆN THÔNG BÁO CẢM ON SAU KHI ĐÁNH GIÁ THÀNH CÔNG (Dành cho trường hợp reload truyền thống nếu có)
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

    /**
     * AUTO SLIDE LOGIC
     * Tự động chuyển ảnh sau mỗi 5 giây
     */
    const allProductImages = <?= json_encode(array_map('asset', $product_images), JSON_UNESCAPED_SLASHES) ?>;

    let currentImgIndex = 0;
    let slideInterval = null;


    /**
     * THAY ĐỔI ẢNH CHÍNH KHI CLICK THUMBNAIL
     * Cập nhật src của ảnh chính và đổi style viền cho thumbnail đang chọn.
     * Áp dụng hiệu ứng Fade + Blur + Scale chuyên nghiệp.
     */
    function changeMainImage(src, thumbElement, isManual = false) {
        const mainImg = document.getElementById('main-product-image');
        if (!mainImg || mainImg.src === src) return;

        // Bước 1: Hiệu ứng thoát (Exit animation)
        mainImg.style.opacity = '0';
        mainImg.style.transform = 'scale(0.95) translateY(10px)';
        mainImg.style.filter = 'blur(10px)';
        
        // Bước 2: Thay đổi nguồn ảnh sau khi ảnh cũ đã mờ đi
        setTimeout(() => {
            mainImg.src = src;
            
            // Bước 3: Hiệu ứng vào (Entrance animation)
            mainImg.onload = () => {
                mainImg.style.opacity = '1';
                mainImg.style.transform = 'scale(1) translateY(0)';
                mainImg.style.filter = 'blur(0)';
            };
        }, 300);

        // Cập nhật class border cho thumbnails
        document.querySelectorAll('.thumbnail-item').forEach(el => {
            el.classList.remove('border-primary', 'shadow-md', 'scale-105');
            el.classList.add('border-gray-100', 'opacity-60');
        });
        
        if (thumbElement) {
            thumbElement.classList.remove('border-gray-100', 'opacity-60');
            thumbElement.classList.add('border-primary', 'shadow-md', 'scale-105');
        }

        // Nếu người dùng click thủ công, reset lại auto-slide để tránh bị nhảy hình đột ngột
        if (isManual) {
            const index = allProductImages.indexOf(src);
            if (index !== -1) currentImgIndex = index;
            resetAutoSlide();
        }
    }


    function startAutoSlide() {
        if (allProductImages.length <= 1) {
            console.log("Gallery: Chỉ có 1 ảnh, không chạy auto slide.");
            return;
        }
        
        console.log("Gallery: Khởi động auto slide với " + allProductImages.length + " ảnh.");
        
        if (slideInterval) clearInterval(slideInterval);
        
        slideInterval = setInterval(() => {
            currentImgIndex = (currentImgIndex + 1) % allProductImages.length;
            const nextSrc = allProductImages[currentImgIndex];
            const thumbnails = document.querySelectorAll('.thumbnail-item');
            
            console.log("Gallery: Tự động chuyển sang ảnh index " + currentImgIndex);
            
            if (thumbnails[currentImgIndex]) {
                changeMainImage(nextSrc, thumbnails[currentImgIndex], false);
                
                // Tự động cuộn thanh thumbnail nếu ảnh bị khuất (Cách cuộn an toàn không nhảy trang)
                const container = document.getElementById('thumbnail-container');
                if (container) {
                    const thumb = thumbnails[currentImgIndex];
                    const scrollPos = thumb.offsetLeft - (container.offsetWidth / 2) + (thumb.offsetWidth / 2);
                    container.scrollTo({ 
                        left: scrollPos, 
                        behavior: 'smooth' 
                    });
                }
            }

        }, 5000);
    }

    function resetAutoSlide() {
        console.log("Gallery: Reset auto slide do tương tác người dùng.");
        clearInterval(slideInterval);
        startAutoSlide();
    }

    // Khởi chạy ngay lập tức
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        startAutoSlide();
    } else {
        document.addEventListener('DOMContentLoaded', startAutoSlide);
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