<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?login_required=1");
    exit;
}

$user_id = $_SESSION['user_id'];

// Xử lý Cập nhật / Xóa bằng cách gọi hàm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    updateCartItem($db, (int)$_POST['cart_id'], $user_id, $_POST['action']);
    header("Location: cart.php");
    exit;
}

$cart_items = getCartItems($db, $user_id);

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-8 max-w-5xl">
    <h1 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-cart-shopping text-primary"></i> Giỏ hàng của bạn
    </h1>

    <?php if (empty($cart_items)): ?>
        <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 text-center">
            <h3 class="text-lg font-bold text-gray-700 mb-2">Giỏ hàng trống</h3>
            <p class="text-gray-500 mb-6">Bạn chưa có sản phẩm nào trong giỏ hàng.</p>
            <a href="index.php" class="bg-primary text-white px-6 py-2.5 rounded-lg font-bold hover:bg-blue-800 transition shadow">Tiếp tục mua sắm</a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                
                <!-- Bảng Chọn tất cả -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex items-center justify-between">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" id="selectAll" class="w-5 h-5 text-primary rounded cursor-pointer accent-primary" onchange="toggleSelectAll(this)" checked>
                        <span class="font-medium text-gray-700">Chọn tất cả (<span id="total-items-count"><?= count($cart_items) ?></span> sản phẩm)</span>
                    </label>
                </div>

                <?php foreach ($cart_items as $item): ?>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex items-center gap-4 relative">
                        
                        <!-- Checkbox chọn Sản phẩm -->
                        <input type="checkbox" class="item-checkbox w-5 h-5 text-primary rounded cursor-pointer shrink-0 accent-primary" 
                               value="<?= $item['cart_id'] ?>" 
                               data-price="<?= $item['price'] ?>" 
                               data-qty="<?= $item['quantity'] ?>" 
                               onchange="calculateTotal()" checked>

                        <!-- Nút Xóa -->
                        <form method="POST" class="absolute top-2 right-2">
                            <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" name="update_cart" class="text-gray-400 hover:text-red-500 w-8 h-8 rounded-full flex items-center justify-center hover:bg-red-50 transition" title="Xóa">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>

                        <!-- Ảnh -->
                        <div class="w-20 h-20 md:w-24 md:h-24 shrink-0 flex items-center justify-center bg-gray-50 rounded-lg p-1">
                            <img src="<?= htmlspecialchars($item['image']) ?>" class="max-w-full max-h-full object-contain">
                        </div>
                        
                        <!-- Thông tin -->
                        <div class="flex-1 flex flex-col justify-between">
                            <a href="product_detail.php?id=<?= $item['product_id'] ?>" class="font-medium text-gray-800 hover:text-primary pr-8 line-clamp-2 leading-snug">
                                <?= htmlspecialchars($item['name']) ?>
                            </a>
                            <div class="text-danger font-bold text-lg mt-1"><?= number_format($item['price']) ?>đ</div>
                            
                            <!-- Tăng giảm số lượng -->
                            <div class="flex items-center gap-2 mt-2 w-fit">
                                <form method="POST" class="flex items-center border border-gray-300 rounded overflow-hidden shadow-sm">
                                    <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                                    <button type="submit" name="update_cart" value="1" class="px-3 py-1 bg-gray-50 hover:bg-gray-200 text-gray-600 font-bold transition" onclick="this.form.action.value='decrease'">-</button>
                                    <input type="hidden" name="action" value="">
                                    <div class="w-10 text-center text-sm font-medium border-x border-gray-300 py-1 bg-white"><?= $item['quantity'] ?></div>
                                    <button type="submit" name="update_cart" value="1" class="px-3 py-1 bg-gray-50 hover:bg-gray-200 text-gray-600 font-bold transition" onclick="this.form.action.value='increase'">+</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 sticky top-24">
                    <h3 class="font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Tóm tắt đơn hàng</h3>
                    <div class="flex justify-between items-center mb-6">
                        <span class="font-bold text-gray-800">Tổng cộng (<span id="selected-count-display"><?= count($cart_items) ?></span>):</span>
                        <span class="font-extrabold text-2xl text-danger" id="total-price-display">0đ</span>
                    </div>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- Gửi các item được tick qua trang Thanh Toán -->
                        <form action="checkout.php" method="POST" id="checkoutForm" onsubmit="return validateCheckout()">
                            <button type="submit" class="w-full bg-gradient-to-b from-[#fd3a3a] to-[#d70018] text-white rounded-lg py-3 font-bold text-lg shadow-md hover:from-[#e32424] hover:to-[#c30016] transition">TIẾN HÀNH ĐẶT HÀNG</button>
                        </form>
                    <?php else: ?>
                        <button class="w-full bg-primary text-white rounded-lg py-3 font-bold text-lg shadow-md" onclick="document.getElementById('loginModal').classList.remove('hidden')">ĐĂNG NHẬP ĐỂ ĐẶT HÀNG</button>
                        <p class="text-xs text-center text-gray-500 mt-2">Dữ liệu giỏ hàng sẽ được lưu lại sau khi bạn đăng nhập.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // Hàm tính toán tổng tiền dựa trên các ô đã đánh dấu tick
    function calculateTotal() {
        let total = 0;
        let checkedCount = 0;
        const checkboxes = document.querySelectorAll('.item-checkbox');
        
        checkboxes.forEach(cb => {
            if(cb.checked) {
                total += parseFloat(cb.dataset.price) * parseInt(cb.dataset.qty);
                checkedCount++;
            }
        });
        
        document.getElementById('total-price-display').innerText = new Intl.NumberFormat('vi-VN').format(total) + 'đ';
        document.getElementById('selected-count-display').innerText = checkedCount;
        
        // Cập nhật trạng thái nút "Chọn tất cả"
        const selectAllCb = document.getElementById('selectAll');
        if(selectAllCb) {
            selectAllCb.checked = (checkedCount === checkboxes.length && checkboxes.length > 0);
        }
    }

    // Hàm tick chọn / bỏ chọn tất cả
    function toggleSelectAll(source) {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        checkboxes.forEach(cb => cb.checked = source.checked);
        calculateTotal();
    }

    // Xử lý trước khi gửi sang trang Checkout
    function validateCheckout() {
        const checked = document.querySelectorAll('.item-checkbox:checked');
        if(checked.length === 0) {
            if(typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Giỏ hàng trống', text: 'Vui lòng đánh dấu tick chọn ít nhất 1 sản phẩm để thanh toán!', confirmButtonColor: '#0046ab'});
            } else {
                alert('Vui lòng chọn ít nhất 1 sản phẩm để thanh toán!');
            }
            return false;
        }
        
        const form = document.getElementById('checkoutForm');
        // Xóa các input ẩn cũ (để tránh lỗi khi bấm nhiều lần)
        form.querySelectorAll('input[name="selected_items[]"]').forEach(el => el.remove());
        
        // Thêm danh sách ID các sản phẩm đã tick vào form gửi đi
        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_items[]';
            input.value = cb.value;
            form.appendChild(input);
        });
        
        return true;
    }

    // Tự động tính toán lại tổng tiền ngay khi load trang
    document.addEventListener('DOMContentLoaded', calculateTotal);
</script>

<?php require_once 'footer.php'; ?>