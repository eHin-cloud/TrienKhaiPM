<?php
session_start();
require_once "database.php"; // Gọi file DB chứa các hàm dùng chung

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<h2 class='text-center mt-10 font-bold text-red-500'>Sản phẩm không hợp lệ!</h2>");
}
$id = (int)$_GET['id'];

// Xử lý gửi Đánh Giá (Lưu vào database)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (isset($_SESSION['user_id'])) {
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);
        $user_id = $_SESSION['user_id'];
        
        if ($rating > 0 && $rating <= 5 && !empty($comment)) {
            $stmtRev = $db->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
            if ($stmtRev->execute([$id, $user_id, $rating, $comment])) {
                // Cập nhật lại số lượt đánh giá tổng cho sản phẩm
                $db->query("UPDATE products SET total_reviews = total_reviews + 1 WHERE id = $id");
                header("Location: product_detail.php?id=$id#reviews");
                exit;
            }
        }
    }
}

// Lấy Chi Tiết Sản Phẩm thông qua hàm từ database.php
$product = getProductById($db, $id);

if (!$product) {
    die("<h2 class='text-center mt-10 font-bold text-red-500'>Không tìm thấy sản phẩm!</h2>");
}

// Lấy sản phẩm liên quan
$related = getRelatedProducts($db, $product['category_id'], $id, 6);

// Lấy sản phẩm cùng thương hiệu
$same_brand_products = getSameBrandProducts($db, $product['brand_id'], $id, 6);

// Lấy danh sách nhận xét
$reviews = getProductReviews($db, $id);

// Load Header (Chứa menu và form đăng nhập)
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
        
        <!-- CỘT TRÁI: Ảnh & Chính sách (7/12) -->
        <div class="lg:col-span-7 flex flex-col gap-4">
            <div class="border border-gray-200 rounded-lg p-4 flex items-center justify-center h-[350px] md:h-[450px] bg-white relative">
                <img src="<?= $product['image'] ?>" class="max-w-full max-h-full object-contain">
                <?php if ($product['old_price']): $disc = round((($product['old_price'] - $product['price']) / $product['old_price']) * 100); ?>
                    <div class="absolute top-4 left-4 bg-gradient-to-r from-red-500 to-red-600 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md">-<?= $disc ?>%</div>
                <?php endif; ?>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[13px] text-gray-700 bg-white p-4 border border-gray-200 rounded-lg">
                <div class="flex items-start gap-2"><i class="fa-solid fa-rotate text-primary mt-1 text-base w-5"></i><p>Hư gì đổi nấy <b>12 tháng</b> tại 3000 siêu thị toàn quốc.</p></div>
                <div class="flex items-start gap-2"><i class="fa-solid fa-shield-halved text-primary mt-1 text-base w-5"></i><p>Bảo hành chính hãng <b>2 năm</b>, có người đến tận nhà.</p></div>
            </div>
        </div>

        <!-- CỘT PHẢI: Giá, Quà tặng & Đặt mua (5/12) -->
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

            <!-- Box Khuyến Mãi (Tách dữ liệu SQL bằng dấu chấm phẩy) -->
            <?php if ($product['gift_text']): 
                // Xử lý tách chuỗi khuyến mãi từ SQL
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

            <!-- Các Nút Mua Hàng -->
            <div class="flex flex-col gap-3 mt-2">
                <button class="w-full bg-gradient-to-b from-[#fd3a3a] to-[#d70018] text-white rounded-lg py-3 hover:from-[#e32424] hover:to-[#c30016] transition shadow flex flex-col items-center justify-center border border-[#c30016]" onclick="alert('Đã thêm sản phẩm vào giỏ!')">
                    <span class="font-bold text-lg leading-tight">MUA NGAY</span>
                    <span class="text-[12px] font-normal">Giao hàng miễn phí hoặc nhận tại siêu thị</span>
                </button>
                <div class="flex gap-3">
                    <button class="flex-1 bg-gradient-to-b from-[#2e7dd6] to-[#0046ab] text-white rounded-lg py-2.5 hover:from-[#2368b8] hover:to-[#00388a] transition shadow flex flex-col items-center justify-center border border-[#00388a]">
                        <span class="font-bold text-[14px]">TRẢ GÓP 0%</span>
                        <span class="text-[11px]">Duyệt hồ sơ trong 5 phút</span>
                    </button>
                    <button class="flex-1 bg-gradient-to-b from-[#2e7dd6] to-[#0046ab] text-white rounded-lg py-2.5 hover:from-[#2368b8] hover:to-[#00388a] transition shadow flex flex-col items-center justify-center border border-[#00388a]">
                        <span class="font-bold text-[14px]">TRẢ GÓP QUA THẺ</span>
                        <span class="text-[11px]">Visa, Mastercard, JCB</span>
                    </button>
                </div>
            </div>
            
            <div class="text-center text-[13px] text-gray-600 mt-2">
                Gọi đặt mua <a href="tel:18001061" class="text-primary font-bold hover:underline">1800.1061</a> (7:30 - 22:00)
            </div>
        </div>
    </div>

    <!-- MÔ TẢ & THÔNG SỐ KỸ THUẬT -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mt-8">
        
        <!-- CỘT TRÁI: Mô tả chi tiết (Bài Viết) -->
        <div class="lg:col-span-7 bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-8">
            <h2 class="text-[18px] font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200">Đặc điểm nổi bật</h2>
            
            <!-- Vùng hiển thị bài viết có thể thu gọn -->
            <div id="desc-container" class="relative overflow-hidden" style="max-height: 350px;">
                <div class="prose prose-sm max-w-none text-gray-700 leading-relaxed text-[15px] text-justify">
                    <?= $product['description'] ? $product['description'] : '<p>Chưa có thông tin mô tả chi tiết cho sản phẩm này.</p>' ?>
                    <img src="<?= $product['image'] ?>" class="w-full max-w-[500px] mx-auto my-6 rounded-lg border border-gray-100" alt="<?= $product['name'] ?>">
                </div>
                <!-- Hiệu ứng gradient mờ ở dưới -->
                <div id="desc-gradient" class="absolute bottom-0 left-0 w-full h-24 bg-gradient-to-t from-white to-transparent"></div>
            </div>
            
            <!-- Nút xử lý Đọc Thêm -->
            <button id="btn-read-more" onclick="toggleDescription()" class="mt-4 w-full text-primary border border-primary hover:bg-blue-50 py-2 rounded-lg text-[14px] font-medium transition">Đọc thêm bài viết</button>
        </div>

        <!-- CỘT PHẢI: Thông số kỹ thuật -->
        <div class="lg:col-span-5">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-6 sticky top-20">
                <h2 class="text-[18px] font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">Thông số kỹ thuật</h2>
                
                <!-- Bảng Thông Số Rút Gọn -->
                <div class="text-[14px] text-gray-700 specs-table overflow-hidden" style="max-height: 300px;">
                     <?= $product['specifications'] ? $product['specifications'] : '<p>Chưa cập nhật thông số.</p>' ?>
                </div>
                <style>
                    .specs-table ul { padding: 0; margin: 0; list-style: none; display: flex; flex-direction: column; }
                    .specs-table li { padding: 12px 15px; display: flex; gap: 10px; border-bottom: 1px solid #f1f2f6; }
                    .specs-table li:nth-child(odd) { background-color: #f9fafb; }
                </style>

                <!-- Nút Gọi Modal -->
                <button onclick="document.getElementById('specsModal').classList.remove('hidden')" class="mt-4 w-full text-primary border border-primary hover:bg-blue-50 py-2 rounded-lg text-[14px] font-medium transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-list"></i> Xem cấu hình chi tiết
                </button>
            </div>
        </div>
    </div>

    <!-- KHỐI ĐÁNH GIÁ VÀ NHẬN XÉT -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-8 mt-8" id="reviews">
        <h2 class="text-[18px] font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200">Đánh giá & Nhận xét <?= htmlspecialchars($product['name']) ?></h2>
        <div class="flex flex-col md:flex-row gap-8 items-center border-b border-gray-100 pb-6 mb-6">
            <div class="flex flex-col items-center md:w-1/4">
                <div class="text-5xl font-extrabold text-yellow-500 mb-1"><?= number_format($product['rate_star'], 1) ?></div>
                <div class="flex text-yellow-400 text-lg mb-1">
                    <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-solid fa-star <?= $i <= $product['rate_star'] ? '' : 'text-gray-300' ?>"></i><?php endfor; ?>
                </div>
                <div class="text-sm text-gray-500"><?= $product['total_reviews'] ?> lượt đánh giá</div>
            </div>
            
            <div class="flex flex-col items-center justify-center w-full md:w-2/4">
                <p class="text-sm text-gray-600 mb-3 text-center">Bạn đã dùng sản phẩm này?</p>
                <button onclick="toggleReviewForm()" class="bg-primary text-white py-2 px-6 rounded-lg text-sm font-bold shadow hover:bg-blue-800 transition">Gửi đánh giá</button>
            </div>
        </div>

        <!-- FORM GỬI ĐÁNH GIÁ (Ẩn mặc định) -->
        <div id="review-form-container" class="hidden mb-8 max-w-2xl mx-auto bg-gray-50 p-5 rounded-lg border border-gray-200">
            <?php if (isset($_SESSION['user_id'])): ?>
                <form method="POST" action="product_detail.php?id=<?= $id ?>">
                    <h4 class="font-bold mb-3 text-center">Mời bạn đánh giá sản phẩm</h4>
                    <!-- Khối Click Chọn Sao -->
                    <div class="flex justify-center gap-2 mb-4 text-3xl text-gray-300">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <i class="fa-solid fa-star cursor-pointer star-select transition" id="star_<?= $i ?>" onclick="setRating(<?= $i ?>)"></i>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="input_rating" value="5">
                    
                    <textarea name="comment" required rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:outline-none mb-3 text-sm" placeholder="Mời bạn chia sẻ cảm nhận về sản phẩm..."></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="toggleReviewForm()" class="px-4 py-2 text-gray-500 hover:bg-gray-200 rounded text-sm font-medium">Hủy</button>
                        <button type="submit" name="submit_review" class="bg-primary text-white px-5 py-2 rounded-lg text-sm font-bold hover:bg-blue-800 transition shadow">Gửi đánh giá</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fa-solid fa-circle-exclamation text-yellow-500 text-3xl mb-2"></i>
                    <p class="text-gray-700 mb-3">Vui lòng đăng nhập để gửi đánh giá cho sản phẩm.</p>
                    <button onclick="document.getElementById('loginModal').classList.remove('hidden')" class="bg-primary text-white py-2 px-6 rounded text-sm font-bold">Đăng nhập ngay</button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Danh sách nhận xét -->
        <div>
            <?php if(empty($reviews)): ?>
                <p class="text-center text-gray-500 italic py-4">Chưa có đánh giá nào cho sản phẩm này.</p>
            <?php else: foreach($reviews as $rev): ?>
                <div class="border-b border-gray-100 py-4 last:border-0">
                    <div class="flex items-center justify-between mb-1">
                        <div class="font-bold text-[14px] flex items-center gap-2">
                            <span class="w-6 h-6 bg-gray-200 text-gray-600 rounded-full flex items-center justify-center text-xs"><?= mb_substr($rev['fullname'], 0, 1) ?></span>
                            <?= htmlspecialchars($rev['fullname']) ?>
                        </div>
                        <span class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($rev['created_at'])) ?></span>
                    </div>
                    <div class="flex text-yellow-400 text-[10px] mb-2 pl-8">
                        <?php for($i=1; $i<=5; $i++) echo "<i class='fa-solid fa-star " . ($i <= $rev['rating'] ? '' : 'text-gray-300') . "'></i>"; ?>
                    </div>
                    <p class="text-[14px] text-gray-700 pl-8 leading-relaxed"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- SẢN PHẨM CÙNG DANH MỤC -->
    <?php if(!empty($related)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-8 mt-8">
        <h2 class="text-lg font-bold text-gray-800 mb-4 uppercase border-b border-gray-100 pb-2">Sản phẩm cùng danh mục</h2>
        <div class="flex overflow-x-auto gap-3 pb-4 hide-scrollbar">
            <?php foreach ($related as $r): 
                $r_disc = $r['old_price'] ? round((($r['old_price'] - $r['price']) / $r['old_price']) * 100) : 0;
            ?>
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

    <!-- SẢN PHẨM CÙNG THƯƠNG HIỆU -->
    <?php if(!empty($same_brand_products)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 md:p-8 mt-8">
        <h2 class="text-lg font-bold text-gray-800 mb-4 uppercase border-b border-gray-100 pb-2">Sản phẩm cùng hãng <?= htmlspecialchars($product['brand_name']) ?></h2>
        <div class="flex overflow-x-auto gap-3 pb-4 hide-scrollbar">
            <?php foreach ($same_brand_products as $r): 
                $r_disc = $r['old_price'] ? round((($r['old_price'] - $r['price']) / $r['old_price']) * 100) : 0;
            ?>
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

<!-- JAVASCRIPT XỬ LÝ GIAO DIỆN -->
<script>
    // Xử lý nút "Đọc thêm bài viết"
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

    // Xử lý ẩn hiện Form Đánh giá
    function toggleReviewForm() {
        const form = document.getElementById('review-form-container');
        form.classList.toggle('hidden');
    }

    // Xử lý hiệu ứng chọn Sao Đánh Giá
    function setRating(rating) {
        document.getElementById('input_rating').value = rating;
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
    // Set mặc định 5 sao khi tải form
    setRating(5);
</script>

<?php require_once 'footer.php'; ?>