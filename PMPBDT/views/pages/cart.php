<?php
/**
 * ============================================================
 * CART.PHP - TRANG GIỎ HÀNG
 * ============================================================
 * 
 * Hiển thị danh sách sản phẩm trong giỏ hàng của khách đang đăng nhập.
 * 
 * CHỨC NĂNG CHÍNH:
 * 1. Hiển thị danh sách sản phẩm trong giỏ (tên, ảnh, giá, số lượng)
 * 2. Checkbox chọn/bỏ chọn từng sản phẩm hoặc chọn tất cả
 * 3. Tăng/giảm số lượng, xóa sản phẩm khỏi giỏ
 * 4. Tính tổng tiền theo các sản phẩm đã tick chọn (JavaScript real-time)
 * 5. Gửi danh sách sản phẩm đã chọn sang trang checkout.php
 * 
 * LUỒNG HOẠT ĐỘNG:
 * - Khách thêm SP vào giỏ (addToCartAjax) -> Vào trang này
 * - Tick chọn SP muốn mua -> Bấm "TIẾN HÀNH ĐẶT HÀNG"
 * - JS validate (phải chọn ít nhất 1 SP) -> Gửi POST sang checkout.php
 * 
 * @requires database.php - Hàm getCartItems(), updateCartItem()
 * @requires header.php   - Giao diện header chung
 * @requires footer.php   - Giao diện footer chung
 */

// session_start() removed by Router
// database.php is auto-loaded by Router // Vẫn giữ tạm thời vì header.php chưa gỡ hết

use App\Repository\CartRepository;
use App\Service\CartService;

$cartService = new CartService(new CartRepository($db));

// Bắt buộc đăng nhập mới xem được giỏ hàng
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?login_required=1");
    exit;
}

$user_id = $_SESSION['user_id'];

// ==========================================
// XỬ LÝ CẬP NHẬT GIỎ HÀNG (Tăng/Giảm/Xóa)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $cartService->changeItemQuantityOrRemove((int)$_POST['cart_id'], $user_id, $_POST['action']);
    header("Location: cart.php");
    exit;
}

// 2. Lấy danh sách sản phẩm thông qua Service
$cart_items = $cartService->getUserCartItems($user_id);

// Include giao diện header
require_once __DIR__ . '/../partials/header.php';
?>

<!-- ==========================================
     GIAO DIỆN TRANG GIỎ HÀNG
     ========================================== -->
<div class="container mx-auto px-4 py-8 max-w-5xl">
    <!-- Tiêu đề trang -->
    <h1 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-cart-shopping text-primary"></i> <?= __("your_cart") ?>
    </h1>

    <!-- TRƯỜNG HỢP 1: Giỏ hàng trống -->
    <?php if (empty($cart_items)): ?>
        <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 text-center">
            <h3 class="text-lg font-bold text-gray-700 mb-2"><?= __("cart_empty") ?></h3>
            <p class="text-gray-500 mb-6"><?= __("cart_empty_desc") ?></p>
            <a href="index.php" class="bg-primary text-white px-6 py-2.5 rounded-lg font-bold hover:bg-blue-800 transition shadow"><?= __("continue_shopping") ?></a>
        </div>

    <!-- TRƯỜNG HỢP 2: Có sản phẩm trong giỏ -->
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- === CỘT TRÁI: Danh sách sản phẩm === -->
            <div class="lg:col-span-2 space-y-4">
                
                <!-- Thanh "Chọn tất cả" -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex items-center justify-between">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <!-- Checkbox chọn/bỏ chọn tất cả - mặc định checked -->
                        <input type="checkbox" id="selectAll" class="w-5 h-5 text-primary rounded cursor-pointer accent-primary" onchange="toggleSelectAll(this)" checked>
                        <span class="font-medium text-gray-700"><?= __("select_all") ?> (<span id="total-items-count"><?= count($cart_items) ?></span> <?= __("products_count") ?>)</span>
                    </label>
                </div>

                <!-- Lặp qua từng sản phẩm trong giỏ -->
                <?php foreach ($cart_items as $item): ?>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex items-center gap-4 relative">
                        
                        <!-- Checkbox chọn sản phẩm - data-price và data-qty dùng cho JS tính tổng -->
                        <input type="checkbox" class="item-checkbox w-5 h-5 text-primary rounded cursor-pointer shrink-0 accent-primary" 
                               value="<?= $item['cart_id'] ?>" 
                               data-price="<?= $item['price'] ?>" 
                               data-qty="<?= $item['quantity'] ?>" 
                               onchange="calculateTotal()" checked>

                        <!-- Nút xóa sản phẩm (góc trên bên phải) -->
                        <form method="POST" class="absolute top-2 right-2">
                            <?= csrf_input_field() ?>
                            <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" name="update_cart" class="text-gray-400 hover:text-red-500 w-8 h-8 rounded-full flex items-center justify-center hover:bg-red-50 transition" title="Xóa">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>

                        <!-- Ảnh sản phẩm -->
                        <div class="w-20 h-20 md:w-24 md:h-24 shrink-0 flex items-center justify-center bg-gray-50 rounded-lg p-1">
                            <img src="<?= htmlspecialchars($item['image']) ?>" class="max-w-full max-h-full object-contain">
                        </div>
                        
                        <!-- Thông tin sản phẩm: Tên, Giá, Nút tăng giảm số lượng -->
                        <div class="flex-1 flex flex-col justify-between">
                            <a href="product_detail.php?id=<?= $item['product_id'] ?>" class="font-medium text-gray-800 hover:text-primary pr-8 line-clamp-2 leading-snug">
                                <?= htmlspecialchars($item['name']) ?>
                            </a>
                            <div class="text-danger font-bold text-lg mt-1"><?= number_format($item['price']) ?>đ</div>
                            
                            <!-- Nút tăng/giảm số lượng -->
                            <div class="flex items-center gap-2 mt-2 w-fit">
                                <form method="POST" class="flex items-center border border-gray-300 rounded overflow-hidden shadow-sm">
                                    <?= csrf_input_field() ?>
                                    <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                                    <!-- Nút giảm: set action='decrease' trước khi submit -->
                                    <button type="submit" name="update_cart" value="1" class="px-3 py-1 bg-gray-50 hover:bg-gray-200 text-gray-600 font-bold transition" onclick="this.form.action.value='decrease'">-</button>
                                    <input type="hidden" name="action" value="">
                                    <!-- Hiển thị số lượng hiện tại (chỉ đọc) -->
                                    <div class="w-10 text-center text-sm font-medium border-x border-gray-300 py-1 bg-white"><?= $item['quantity'] ?></div>
                                    <!-- Nút tăng: set action='increase' trước khi submit -->
                                    <button type="submit" name="update_cart" value="1" class="px-3 py-1 bg-gray-50 hover:bg-gray-200 text-gray-600 font-bold transition" onclick="this.form.action.value='increase'">+</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- === CỘT PHẢI: Tóm tắt đơn hàng & Nút đặt hàng === -->
            <div class="lg:col-span-1">
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 sticky top-24">
                    <h3 class="font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2"><?= __("order_summary") ?></h3>
                    <div class="flex justify-between items-center mb-6">
                        <span class="font-bold text-gray-800"><?= __("total") ?> (<span id="selected-count-display"><?= count($cart_items) ?></span>):</span>
                        <!-- Tổng tiền được JS tính toán real-time -->
                        <span class="font-extrabold text-2xl text-danger" id="total-price-display">0đ</span>
                    </div>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- Form gửi danh sách sản phẩm đã tick sang trang Thanh Toán -->
                        <form action="checkout.php" method="POST" id="checkoutForm" onsubmit="return validateCheckout()">
                            <?= csrf_input_field() ?>
                            <button type="submit" class="w-full bg-gradient-to-b from-[#fd3a3a] to-[#d70018] text-white rounded-lg py-3 font-bold text-lg shadow-md hover:from-[#e32424] hover:to-[#c30016] transition"><?= __("proceed_to_checkout") ?></button>
                        </form>
                    <?php else: ?>
                        <!-- Nếu chưa đăng nhập -> Hiển thị nút đăng nhập -->
                        <button class="w-full bg-primary text-white rounded-lg py-3 font-bold text-lg shadow-md" onclick="document.getElementById('loginModal').classList.remove('hidden')"><?= __("login_to_order") ?></button>
                        <p class="text-xs text-center text-gray-500 mt-2"><?= __("cart_save_login_hint") ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ==========================================
     JAVASCRIPT: Tính tổng tiền & Validate
     ========================================== -->
<script>
    /**
     * Tính toán tổng tiền dựa trên các checkbox đã được tick
     * Được gọi mỗi khi user tick/bỏ tick 1 sản phẩm
     * Đọc data-price và data-qty từ attribute của checkbox
     */
    function calculateTotal() {
        let total = 0;
        let checkedCount = 0;
        const checkboxes = document.querySelectorAll('.item-checkbox');
        
        // Duyệt qua tất cả checkbox sản phẩm
        checkboxes.forEach(cb => {
            if(cb.checked) {
                // Cộng dồn: giá × số lượng
                total += parseFloat(cb.dataset.price) * parseInt(cb.dataset.qty);
                checkedCount++;
            }
        });
        
        // Cập nhật hiển thị tổng tiền (format theo kiểu Việt Nam)
        document.getElementById('total-price-display').innerText = new Intl.NumberFormat('vi-VN').format(total) + 'đ';
        // Cập nhật số lượng sản phẩm đã chọn
        document.getElementById('selected-count-display').innerText = checkedCount;
        
        // Cập nhật trạng thái checkbox "Chọn tất cả"
        const selectAllCb = document.getElementById('selectAll');
        if(selectAllCb) {
            selectAllCb.checked = (checkedCount === checkboxes.length && checkboxes.length > 0);
        }
    }

    /**
     * Xử lý tick/bỏ tick "Chọn tất cả"
     * @param {HTMLInputElement} source - Checkbox "Chọn tất cả"
     */
    function toggleSelectAll(source) {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        // Đồng bộ trạng thái checked cho tất cả checkbox
        checkboxes.forEach(cb => cb.checked = source.checked);
        calculateTotal(); // Tính lại tổng tiền
    }

    /**
     * Validate và chuẩn bị dữ liệu trước khi chuyển sang trang Checkout
     * - Kiểm tra có ít nhất 1 sản phẩm được chọn
     * - Thêm các input hidden chứa cart_id đã tick vào form
     * @returns {boolean} - true nếu hợp lệ, false nếu không
     */
    function validateCheckout() {
        const checked = document.querySelectorAll('.item-checkbox:checked');
        
        // Validate: phải chọn ít nhất 1 sản phẩm
        if(checked.length === 0) {
            if(typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: '<?= __("cart_empty") ?>', text: '<?= __("select_at_least_one") ?>', confirmButtonColor: '#0046ab'});
            } else {
                alert('<?= __("select_at_least_one") ?>');
            }
            return false;
        }
        
        const form = document.getElementById('checkoutForm');
        // Xóa các input ẩn cũ (để tránh lỗi duplicate khi bấm nhiều lần)
        form.querySelectorAll('input[name="selected_items[]"]').forEach(el => el.remove());
        
        // Thêm input hidden cho mỗi sản phẩm đã tick -> gửi sang checkout.php
        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_items[]';  // Mảng cart_id[]
            input.value = cb.value;           // cart_id của sản phẩm
            form.appendChild(input);
        });
        
        return true; // Cho phép form submit
    }

    // Tự động tính tổng tiền ngay khi trang được tải xong
    document.addEventListener('DOMContentLoaded', calculateTotal);
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>