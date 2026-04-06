<?php
session_start();
require_once "database.php"; 

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<h2 class='text-center mt-10 font-bold text-red-500'>Sản phẩm không hợp lệ!</h2>");
}
$id = (int)$_GET['id'];

// Xử lý gửi Đánh Giá 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (isset($_SESSION['user_id'])) {
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);
        $user_id = $_SESSION['user_id'];
        
        // Xử lý upload ảnh/video
        $media_paths = [];
        if (isset($_FILES['review_media'])) {
            $upload_dir = 'uploads/reviews/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $allowed_types = ['image/jpeg','image/png','image/gif','image/webp','video/mp4','video/webm','video/quicktime'];
            $max_files = 5;
            $file_count = count($_FILES['review_media']['name']);
            
            for ($f = 0; $f < min($file_count, $max_files); $f++) {
                if ($_FILES['review_media']['error'][$f] === UPLOAD_ERR_OK) {
                    $mime = $_FILES['review_media']['type'][$f];
                    if (in_array($mime, $allowed_types) && $_FILES['review_media']['size'][$f] <= 20 * 1024 * 1024) {
                        $ext = pathinfo($_FILES['review_media']['name'][$f], PATHINFO_EXTENSION);
                        $new_name = 'rev_' . time() . '_' . $f . '.' . $ext;
                        $target = $upload_dir . $new_name;
                        if (move_uploaded_file($_FILES['review_media']['tmp_name'][$f], $target)) {
                            $media_paths[] = $target;
                        }
                    }
                }
            }
        }
        $media_json = !empty($media_paths) ? json_encode($media_paths) : null;
        
        if ($rating > 0 && $rating <= 5 && !empty($comment)) {
            $stmtRev = $db->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, media) VALUES (?, ?, ?, ?, ?)");
            if ($stmtRev->execute([$id, $user_id, $rating, $comment, $media_json])) {
                // Cập nhật lại rate_star trung bình
                $avgStmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE product_id = ?");
                $avgStmt->execute([$id]);
                $avgData = $avgStmt->fetch(PDO::FETCH_ASSOC);
                $db->prepare("UPDATE products SET rate_star = ?, total_reviews = ? WHERE id = ?")->execute([round($avgData['avg_rating'], 1), $avgData['total'], $id]);
                
                header("Location: product_detail.php?id=$id#reviews");
                exit;
            }
        }
    }
}

// Xử lý XÓA đánh giá của chính mình
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    if (isset($_SESSION['user_id'])) {
        $review_id = (int)$_POST['review_id'];
        $user_id = $_SESSION['user_id'];
        
        // Chỉ cho phép xóa review của chính mình (hoặc admin)
        $checkOwner = $db->prepare("SELECT * FROM reviews WHERE id = ? AND (user_id = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))");
        $checkOwner->execute([$review_id, $user_id, $user_id]);
        $reviewToDelete = $checkOwner->fetch(PDO::FETCH_ASSOC);
        
        if ($reviewToDelete) {
            // Xóa file media nếu có
            if (!empty($reviewToDelete['media'])) {
                $mediaFiles = json_decode($reviewToDelete['media'], true);
                if (is_array($mediaFiles)) {
                    foreach ($mediaFiles as $file) {
                        if (file_exists($file)) unlink($file);
                    }
                }
            }
            
            $db->prepare("DELETE FROM reviews WHERE id = ?")->execute([$review_id]);
            
            // Cập nhật lại rate_star trung bình
            $avgStmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE product_id = ?");
            $avgStmt->execute([$id]);
            $avgData = $avgStmt->fetch(PDO::FETCH_ASSOC);
            $newAvg = $avgData['total'] > 0 ? round($avgData['avg_rating'], 1) : 0;
            $db->prepare("UPDATE products SET rate_star = ?, total_reviews = ? WHERE id = ?")->execute([$newAvg, $avgData['total'], $id]);
        }
        
        header("Location: product_detail.php?id=$id#reviews");
        exit;
    }
}

$product = getProductById($db, $id);

if (!$product) {
    die("<h2 class='text-center mt-10 font-bold text-red-500'>Không tìm thấy sản phẩm!</h2>");
}

$related = getRelatedProducts($db, $product['category_id'], $id, 6);
$same_brand_products = getSameBrandProducts($db, $product['brand_id'], $id, 6);
$reviews = getProductReviews($db, $id);
$reviewStats = getReviewStats($reviews);

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-4 md:py-6">
    <!-- Breadcrumb -->
    <div class="text-[13px] text-primary mb-4 flex items-center gap-2 overflow-x-auto whitespace-nowrap hide-scrollbar">
        <a href="index.php" class="hover:underline font-medium">Trang chủ</a>
        <i class="fa-solid fa-angle-right text-[10px] text-gray-400"></i>
        <a href="index.php?cat_id=<?= $product['category_id'] ?>" class="hover:underline font-medium"><?= htmlspecialchars($product['category_name']) ?></a>
        <i class="fa-solid fa-angle-right text-[10px] text-gray-400"></i>
        <a href="index.php?brand_id=<?= $product['brand_id'] ?>" class="hover:underline font-medium"><?= htmlspecialchars($product['brand_name']) ?></a>
    </div>

    <!-- Tiêu đề Sản Phẩm -->
    <div class="mb-4 pb-4 border-b border-gray-200">
        <h1 class="text-[22px] md:text-2xl font-bold text-gray-800 leading-snug mb-2"><?= htmlspecialchars($product['name']) ?></h1>
        <div class="flex flex-wrap items-center gap-4 text-[13px]">
            <div class="flex items-center gap-1 text-yellow-400">
                <span class="font-bold text-gray-700"><?= $product['rate_star'] ?></span>
                <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-solid fa-star <?= $i <= $product['rate_star'] ? '' : 'text-gray-200' ?>"></i><?php endfor; ?>
                <a href="#reviews" class="text-primary hover:underline ml-1">(<?= $product['total_reviews'] ?> đánh giá)</a>
            </div>
        </div>
    </div>

    <!-- PRODUCT MAIN CONTENT -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- CỘT TRÁI -->
        <div class="lg:col-span-7 flex flex-col gap-4">
            <div class="border border-gray-200 rounded-lg p-4 flex items-center justify-center h-[350px] md:h-[450px] bg-white relative">
                <img src="<?= $product['image'] ?>" class="max-w-full max-h-full object-contain">
                <?php if ($product['old_price']): $disc = round((($product['old_price'] - $product['price']) / $product['old_price']) * 100); ?>
                    <div class="absolute top-4 left-4 bg-gradient-to-r from-red-500 to-red-600 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md">-<?= $disc ?>%</div>
                <?php endif; ?>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[13px] text-gray-700 bg-white p-4 border border-gray-200 rounded-lg">
                <div class="flex items-start gap-2"><i class="fa-solid fa-rotate text-primary mt-1 text-base w-5"></i><p>Hư gì đổi nấy <b>12 tháng</b> tại siêu thị toàn quốc.</p></div>
                <div class="flex items-start gap-2"><i class="fa-solid fa-shield-halved text-primary mt-1 text-base w-5"></i><p>Bảo hành chính hãng <b>2 năm</b>, có người đến tận nhà.</p></div>
            </div>
        </div>

        <!-- CỘT PHẢI -->
        <div class="lg:col-span-5 flex flex-col gap-4">
            
            <!-- Box Giá -->
            <div class="bg-gray-50 rounded-lg p-5 border border-gray-200 shadow-sm relative overflow-hidden">
                <p class="text-gray-500 text-sm font-medium mb-1 uppercase tracking-wider">Giá Bán Online</p>
                <div class="flex items-end gap-3 mb-2">
                    <span class="text-4xl font-extrabold text-danger"><?= number_format($product['price']) ?>đ</span>
                </div>
                <?php if ($product['old_price']): ?>
                    <div class="flex items-center gap-2">
                        <span class="text-sm md:text-base text-gray-500 line-through"><?= number_format($product['old_price']) ?>đ</span>
                        <span class="text-[11px] bg-red-100 text-red-600 font-bold px-2 py-0.5 rounded border border-red-200">Giảm <?= $disc ?? 0 ?>%</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Box Khuyến Mãi -->
            <?php if ($product['gift_text']): 
                $gifts = array_filter(explode(';', $product['gift_text']));
            ?>
            <div class="border border-red-200 rounded-lg overflow-hidden shadow-sm">
                <div class="bg-red-50 px-4 py-3 border-b border-red-200 text-danger font-bold text-[15px] flex items-center gap-2">
                    <i class="fa-solid fa-gift text-lg"></i> Khuyến mãi trị giá đến 500.000đ
                </div>
                <div class="p-4 text-[13.5px] text-gray-700 bg-white flex flex-col gap-3">
                    <?php foreach ($gifts as $index => $gift): ?>
                    <div class="flex items-start gap-2.5">
                        <div class="w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center shrink-0 font-bold text-[10px]"><?= $index + 1 ?></div>
                        <span class="leading-tight"><?= htmlspecialchars(trim($gift)) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- CÁC NÚT MUA HÀNG TÍCH HỢP AJAX -->
            <div class="flex flex-col gap-3 mt-2">
                <div class="flex gap-3 h-[60px]">
                    <button type="button" onclick="addToCartAjax(<?= $id ?>)" class="flex-1 bg-white border border-[#2e7dd6] text-[#2e7dd6] rounded-lg hover:bg-blue-50 transition shadow-sm flex flex-col items-center justify-center text-center">
                        <i class="fa-solid fa-cart-plus text-lg mb-0.5"></i>
                        <span class="text-[14px] font-medium leading-tight">Thêm vào giỏ</span>
                    </button>
                    <button type="button" onclick="buyNowAjax(<?= $id ?>)" class="flex-1 bg-[#ff7a00] text-white rounded-lg hover:bg-[#e66e00] transition shadow-sm flex flex-col items-center justify-center text-center">
                        <span class="font-medium text-[16px] leading-tight">Mua ngay</span>
                    </button>
                </div>
                <button class="w-full bg-[#2e7dd6] text-white rounded-lg py-2.5 hover:bg-[#2368b8] transition shadow-sm flex flex-col items-center justify-center" onclick="document.getElementById('installmentModal').classList.remove('hidden')">
                    <span class="font-medium text-[15px] mb-0.5">Mua trả chậm 0% <i class="fa-solid fa-angle-right text-[12px]"></i></span>
                    <span class="text-[12px] font-normal opacity-90">Nhân viên tổng đài sẽ liên hệ lại với quý khách trong 5 phút.</span>
                </button>
            </div>
            
            <div class="text-center text-[13px] text-gray-600 mt-2">
                Gọi đặt mua <a href="tel:18001061" class="text-primary font-bold hover:underline">1800.1061</a> (7:30 - 22:00)
            </div>
        </div>
    </div>

    <!-- MÔ TẢ & THÔNG SỐ KỸ THUẬT -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mt-8">
        <div class="lg:col-span-7 bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-8">
            <h2 class="text-[18px] font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200">Đặc điểm nổi bật</h2>
            <div id="desc-container" class="relative overflow-hidden" style="max-height: 350px;">
                <div class="prose prose-sm max-w-none text-gray-700 leading-relaxed text-[15px] text-justify">
                    <?= $product['description'] ? $product['description'] : '<p>Chưa có thông tin mô tả chi tiết cho sản phẩm này.</p>' ?>
                    <img src="<?= $product['image'] ?>" class="w-full max-w-[500px] mx-auto my-6 rounded-lg border border-gray-100" alt="<?= $product['name'] ?>">
                </div>
                <div id="desc-gradient" class="absolute bottom-0 left-0 w-full h-24 bg-gradient-to-t from-white to-transparent"></div>
            </div>
            <button id="btn-read-more" onclick="toggleDescription()" class="mt-4 w-full text-primary border border-primary hover:bg-blue-50 py-2 rounded-lg text-[14px] font-medium transition">Đọc thêm bài viết</button>
        </div>

        <div class="lg:col-span-5">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-6 sticky top-20">
                <h2 class="text-[18px] font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">Thông số kỹ thuật</h2>
                <div class="text-[14px] text-gray-700 specs-table overflow-hidden" style="max-height: 300px;">
                     <?= $product['specifications'] ? $product['specifications'] : '<p>Chưa cập nhật thông số.</p>' ?>
                </div>
                <style>
                    .specs-table ul { padding: 0; margin: 0; list-style: none; display: flex; flex-direction: column; }
                    .specs-table li { padding: 12px 15px; display: flex; gap: 10px; border-bottom: 1px solid #f1f2f6; }
                    .specs-table li:nth-child(odd) { background-color: #f9fafb; }
                </style>
                <button onclick="document.getElementById('specsModal').classList.remove('hidden')" class="mt-4 w-full text-primary border border-primary hover:bg-blue-50 py-2 rounded-lg text-[14px] font-medium transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-list"></i> Xem cấu hình chi tiết
                </button>
            </div>
        </div>
    </div>

    <!-- KHỐI ĐÁNH GIÁ VÀ NHẬN XÉT -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-8 mt-8" id="reviews">
        <h2 class="text-[18px] font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200">Đánh giá & Nhận xét <?= htmlspecialchars($product['name']) ?></h2>
        
        <!-- PHẦN TỔNG HỢP ĐÁNH GIÁ -->
        <div class="flex flex-col md:flex-row gap-6 items-center border-b border-gray-100 pb-6 mb-6">
            <!-- Điểm trung bình -->
            <div class="flex flex-col items-center md:w-1/4 shrink-0">
                <div class="text-5xl font-extrabold text-primary mb-1"><?= number_format($reviewStats['avg'], 1) ?></div>
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
                <div class="text-sm text-gray-500 font-medium"><?= $reviewStats['total'] ?> lượt đánh giá</div>
            </div>

            <!-- Biểu đồ phân phối sao -->
            <div class="flex-1 w-full max-w-sm">
                <?php for ($s = 5; $s >= 1; $s--): 
                    $count = $reviewStats['dist'][$s];
                    $pct = $reviewStats['total'] > 0 ? round(($count / $reviewStats['total']) * 100) : 0;
                ?>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="text-xs font-bold text-gray-600 w-8 text-right"><?= $s ?> <i class="fa-solid fa-star text-yellow-400 text-[10px]"></i></span>
                    <div class="flex-1 h-2.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 <?= $s >= 4 ? 'bg-green-400' : ($s === 3 ? 'bg-yellow-400' : 'bg-orange-400') ?>" style="width: <?= $pct ?>%"></div>
                    </div>
                    <span class="text-[11px] text-gray-400 w-12"><?= $count ?> <span class="hidden sm:inline">lượt</span></span>
                </div>
                <?php endfor; ?>
            </div>
            
            <!-- Nút gửi đánh giá -->
            <div class="flex flex-col items-center justify-center shrink-0">
                <p class="text-sm text-gray-600 mb-3 text-center">Bạn đã dùng sản phẩm này?</p>
                <button onclick="toggleReviewForm()" class="bg-primary text-white py-2.5 px-8 rounded-lg text-sm font-bold shadow-md hover:bg-blue-800 transition flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square"></i> Gửi đánh giá
                </button>
            </div>
        </div>

        <!-- FORM ĐÁNH GIÁ -->
        <div id="review-form-container" class="hidden mb-8 max-w-2xl mx-auto bg-gray-50 p-5 rounded-xl border border-gray-200 shadow-sm">
            <?php if (isset($_SESSION['user_id'])): ?>
                <form method="POST" action="product_detail.php?id=<?= $id ?>" enctype="multipart/form-data">
                    <h4 class="font-bold mb-4 text-center text-gray-800">Mời bạn đánh giá sản phẩm</h4>
                    
                    <!-- Chọn sao -->
                    <div class="flex justify-center gap-2 mb-1 text-3xl text-gray-300">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <i class="fa-solid fa-star cursor-pointer star-select transition hover:scale-125" id="star_<?= $i ?>" onclick="setRating(<?= $i ?>)"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-center text-sm text-gray-500 mb-4" id="rating-text">Tuyệt vời</p>
                    
                    <input type="hidden" name="rating" id="input_rating" value="5">
                    <textarea name="comment" required rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:outline-none mb-3 text-sm resize-none" placeholder="Mời bạn chia sẻ cảm nhận về sản phẩm..."></textarea>
                    
                    <!-- Upload ảnh/video -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><i class="fa-solid fa-camera mr-1"></i> Thêm ảnh / video (tối đa 5 file, mỗi file ≤ 20MB)</label>
                        <div id="media-drop-zone" class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-primary hover:bg-blue-50/30 transition relative">
                            <input type="file" name="review_media[]" id="review-media-input" multiple accept="image/*,video/mp4,video/webm,video/quicktime" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="previewMedia(this)">
                            <div id="media-placeholder">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-300 mb-2"></i>
                                <p class="text-sm text-gray-500">Kéo thả hoặc <span class="text-primary font-medium">bấm để chọn</span> ảnh/video</p>
                                <p class="text-xs text-gray-400 mt-1">Hỗ trợ: JPG, PNG, GIF, WEBP, MP4, WEBM</p>
                            </div>
                        </div>
                        <div id="media-preview" class="flex flex-wrap gap-2 mt-3"></div>
                    </div>
                    
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="toggleReviewForm()" class="px-4 py-2 text-gray-500 hover:bg-gray-200 rounded-lg text-sm font-medium transition">Hủy</button>
                        <button type="submit" name="submit_review" class="bg-primary text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-blue-800 transition shadow-md flex items-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i> Gửi đánh giá
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fa-solid fa-circle-exclamation text-yellow-500 text-3xl mb-2"></i>
                    <p class="text-gray-700 mb-3">Vui lòng đăng nhập để gửi đánh giá cho sản phẩm.</p>
                    <button onclick="document.getElementById('loginModal').classList.remove('hidden')" class="bg-primary text-white py-2 px-6 rounded-lg text-sm font-bold shadow hover:bg-blue-800 transition">Đăng nhập ngay</button>
                </div>
            <?php endif; ?>
        </div>

        <!-- DANH SÁCH ĐÁNH GIÁ -->
        <div>
            <?php if(empty($reviews)): ?>
                <p class="text-center text-gray-500 italic py-4">Chưa có đánh giá nào cho sản phẩm này.</p>
            <?php else: foreach($reviews as $rev): ?>
                <div class="border-b border-gray-100 py-5 last:border-0">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-bold text-[14px] flex items-center gap-2">
                            <span class="w-8 h-8 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xs font-bold"><?= mb_substr($rev['fullname'], 0, 1) ?></span>
                            <?= htmlspecialchars($rev['fullname']) ?>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($rev['created_at'])) ?></span>
                            <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $rev['user_id'] || $_SESSION['role'] === 'admin')): ?>
                                <button onclick="confirmDeleteReview(<?= $rev['id'] ?>)" class="text-gray-300 hover:text-red-500 transition text-sm" title="Xóa đánh giá">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                                <form id="delete-review-form-<?= $rev['id'] ?>" method="POST" action="product_detail.php?id=<?= $id ?>" class="hidden">
                                    <input type="hidden" name="delete_review" value="1">
                                    <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mb-2 pl-10">
                        <div class="flex text-yellow-400 text-[12px] gap-0.5">
                            <?php for($i=1; $i<=5; $i++) echo "<i class='fa-solid fa-star " . ($i <= $rev['rating'] ? '' : 'text-gray-200') . "'></i>"; ?>
                        </div>
                        <span class="text-xs font-medium text-gray-500">
                            <?php 
                                $labels = [1=>'Rất tệ', 2=>'Tệ', 3=>'Bình thường', 4=>'Tốt', 5=>'Tuyệt vời'];
                                echo $labels[$rev['rating']] ?? '';
                            ?>
                        </span>
                    </div>
                    <p class="text-[14px] text-gray-700 pl-10 leading-relaxed"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                    
                    <?php 
                    // Hiển thị ảnh/video đính kèm
                    $media = isset($rev['media']) ? json_decode($rev['media'], true) : [];
                    if (!empty($media)): ?>
                    <div class="flex flex-wrap gap-2 mt-3 pl-10">
                        <?php foreach ($media as $idx => $file): 
                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            $is_video = in_array($ext, ['mp4', 'webm', 'mov']);
                        ?>
                            <?php if ($is_video): ?>
                                <div class="relative w-20 h-20 rounded-lg overflow-hidden border border-gray-200 cursor-pointer group" onclick="openMediaViewer('<?= $file ?>', true)">
                                    <video src="<?= $file ?>" class="w-full h-full object-cover"></video>
                                    <div class="absolute inset-0 bg-black/30 flex items-center justify-center group-hover:bg-black/50 transition">
                                        <i class="fa-solid fa-play text-white text-lg"></i>
                                    </div>
                                </div>
                            <?php else: ?>
                                <img src="<?= $file ?>" onclick="openMediaViewer('<?= $file ?>', false)" class="w-20 h-20 object-cover rounded-lg border border-gray-200 cursor-pointer hover:opacity-80 transition hover:shadow-md">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- LIGHTBOX XEM ẢNH/VIDEO -->
    <div id="mediaViewerModal" class="hidden fixed inset-0 bg-black/80 z-[200] flex items-center justify-center backdrop-blur-sm p-4" onclick="closeMediaViewer(event)">
        <button onclick="document.getElementById('mediaViewerModal').classList.add('hidden')" class="absolute top-4 right-4 text-white/80 hover:text-white text-3xl z-10 w-10 h-10 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
        <div id="mediaViewerContent" class="max-w-4xl max-h-[85vh] flex items-center justify-center"></div>
    </div>

    <!-- SẢN PHẨM CÙNG DANH MỤC -->
    <?php if(!empty($related)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-8 mt-8">
        <h2 class="text-lg font-bold text-gray-800 mb-4 uppercase border-b border-gray-100 pb-2">Sản phẩm cùng danh mục</h2>
        <div class="flex overflow-x-auto gap-3 pb-4 hide-scrollbar">
            <?php foreach ($related as $r): 
                $r_disc = $r['old_price'] ? round((($r['old_price'] - $r['price']) / $r['old_price']) * 100) : 0;
            ?>
                <a href="product_detail.php?id=<?= $r['id'] ?>" class="min-w-[160px] md:min-w-[200px] w-[160px] md:w-[200px] flex-shrink-0 border border-gray-100 hover:border-primary p-3 rounded-lg group transition block shadow-sm hover:shadow-md">
                    <div class="h-32 flex items-center justify-center mb-3">
                        <img src="<?= $r['image'] ?>" class="max-w-full max-h-full object-contain group-hover:scale-105 transition">
                    </div>
                    <h4 class="text-[13px] text-gray-800 line-clamp-2 h-10 leading-snug mb-1 group-hover:text-primary font-medium"><?= htmlspecialchars($r['name']) ?></h4>
                    <div class="text-danger font-bold text-[15px]"><?= number_format($r['price']) ?>đ</div>
                    <?php if($r_disc > 0): ?>
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

    <!-- SẢN PHẨM CÙNG THƯƠNG HIỆU -->
    <?php if(!empty($same_brand_products)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-8 mt-8">
        <h2 class="text-lg font-bold text-gray-800 mb-4 uppercase border-b border-gray-100 pb-2">Sản phẩm cùng hãng <?= htmlspecialchars($product['brand_name']) ?></h2>
        <div class="flex overflow-x-auto gap-3 pb-4 hide-scrollbar">
            <?php foreach ($same_brand_products as $r): ?>
                <a href="product_detail.php?id=<?= $r['id'] ?>" class="min-w-[160px] w-[160px] flex-shrink-0 border border-gray-100 hover:border-primary p-3 rounded-lg group transition block shadow-sm hover:shadow-md">
                    <div class="h-32 flex items-center justify-center mb-3">
                        <img src="<?= $r['image'] ?>" class="max-w-full max-h-full object-contain group-hover:scale-105 transition">
                    </div>
                    <h4 class="text-[13px] text-gray-800 line-clamp-2 h-10 leading-snug mb-1 group-hover:text-primary font-medium"><?= htmlspecialchars($r['name']) ?></h4>
                    <div class="text-danger font-bold text-[15px]"><?= number_format($r['price']) ?>đ</div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- MODAL CẤU HÌNH CHI TIẾT -->
<div id="specsModal" class="hidden fixed inset-0 bg-black/60 z-[100] flex items-center justify-center backdrop-blur-sm px-4">
    <div class="bg-white rounded-xl w-full max-w-[600px] max-h-[85vh] flex flex-col relative shadow-2xl">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50 rounded-t-xl">
            <h3 class="font-bold text-lg text-gray-800">Thông số kỹ thuật chi tiết</h3>
            <button onclick="document.getElementById('specsModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-red-50"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div class="p-6 overflow-y-auto specs-table">
            <h4 class="font-bold text-primary mb-3"><?= htmlspecialchars($product['name']) ?></h4>
            <?= $product['specifications'] ?>
        </div>
    </div>
</div>

<!-- MODAL TRẢ GÓP -->
<div id="installmentModal" class="hidden fixed inset-0 bg-black/60 z-[100] flex items-center justify-center backdrop-blur-sm px-4">
    <div class="bg-white rounded-xl w-full max-w-[400px] flex flex-col relative shadow-2xl">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50 rounded-t-xl">
            <h3 class="font-bold text-lg text-gray-800">Đăng ký trả góp </h3>
            <button onclick="document.getElementById('installmentModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 transition w-8 h-8 flex items-center justify-center rounded-full hover:bg-red-50"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        
        <form id="installmentForm" class="p-5" onsubmit="submitInstallment(event)">
            <input type="hidden" name="product_id" value="<?= $id ?>">
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Họ và tên *</label>
                <input type="text" name="fullname" required class="w-full px-3 py-2 border border-gray-300 rounded outline-none focus:ring-2 focus:ring-primary" value="<?= isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : '' ?>">
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Số điện thoại *</label>
                <input type="tel" name="phone" required pattern="[0-9]{10}" class="w-full px-3 py-2 border border-gray-300 rounded outline-none focus:ring-2 focus:ring-primary" placeholder="Nhập số điện thoại...">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Kỳ hạn mong muốn</label>
                <select name="term" class="w-full px-3 py-2 border border-gray-300 rounded outline-none focus:ring-2 focus:ring-primary">
                    <option value="Gói 3 tháng (Lãi suất 0%)">Gói 3 tháng (Lãi suất 0%)</option>
                    <option value="Gói 6 tháng (Lãi suất 5%)">Gói 6 tháng (Lãi suất 5%)</option>
                    <option value="Gói 9 tháng (Lãi suất 10%)">Gói 9 tháng (Lãi suất 10%)</option>
                    <option value="Gói 12 tháng (Lãi suất 20%)">Gói 12 tháng (Lãi suất 20%)</option>
                </select>
            </div>
            <button type="submit" class="w-full bg-primary text-white font-bold py-2.5 rounded-lg hover:bg-blue-800 transition shadow">Xác nhận Đăng ký</button>
            <p class="text-center text-[11px] text-gray-500 mt-3">Nhân viên tổng đài sẽ liên hệ lại với quý khách trong 5 phút.</p>
        </form>
    </div>
</div>

<script>
    function toggleDescription() {
        const container = document.getElementById('desc-container');
        const gradient = document.getElementById('desc-gradient');
        const btn = document.getElementById('btn-read-more');
        
        if (container.style.maxHeight) {
            container.style.maxHeight = null;
            gradient.style.display = 'none';
            btn.innerText = 'Thu gọn bài viết';
        } else {
            container.style.maxHeight = '350px';
            gradient.style.display = 'block';
            btn.innerText = 'Đọc thêm bài viết';
        }
    }

    function toggleReviewForm() {
        const form = document.getElementById('review-form-container');
        form.classList.toggle('hidden');
        if (!form.classList.contains('hidden')) {
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    const ratingLabels = {1: 'Rất tệ', 2: 'Tệ', 3: 'Bình thường', 4: 'Tốt', 5: 'Tuyệt vời'};
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

    // Preview media files
    function previewMedia(input) {
        const preview = document.getElementById('media-preview');
        const placeholder = document.getElementById('media-placeholder');
        preview.innerHTML = '';

        if (input.files.length > 5) {
            alert('Tối đa 5 file!');
            input.value = '';
            return;
        }

        if (input.files.length > 0) {
            placeholder.innerHTML = '<i class="fa-solid fa-circle-check text-green-500 text-2xl mb-1"></i><p class="text-sm text-green-600 font-medium">Đã chọn ' + input.files.length + ' file</p><p class="text-xs text-gray-400 mt-1">Bấm lại để thay đổi</p>';
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

    // Lightbox viewer
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

    // Delete review confirmation
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
                <h3 class="font-bold text-gray-800 text-lg mb-2">Xóa đánh giá?</h3>
                <p class="text-sm text-gray-500 mb-5">Đánh giá của bạn sẽ bị xóa vĩnh viễn và không thể khôi phục.</p>
                <div class="flex gap-3">
                    <button onclick="document.getElementById('delete-confirm-overlay').remove()" class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium text-sm transition">Hủy</button>
                    <button onclick="document.getElementById('delete-review-form-${reviewId}').submit()" class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-lg font-bold text-sm transition shadow-md">Xóa</button>
                </div>
            </div>
        `;
        
        // Close on overlay click
        overlay.addEventListener('click', function(e) {
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

<?php require_once 'footer.php'; ?>