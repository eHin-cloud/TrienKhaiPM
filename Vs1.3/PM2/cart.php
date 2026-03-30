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
$total_price = array_reduce($cart_items, function($sum, $item) {
    return $sum + ($item['price'] * $item['quantity']);
}, 0);

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
                <?php foreach ($cart_items as $item): ?>
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex gap-4 relative">
                        <!-- Nút Xóa -->
                        <form method="POST" class="absolute top-2 right-2">
                            <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" name="update_cart" class="text-gray-400 hover:text-red-500 w-8 h-8 rounded-full flex items-center justify-center hover:bg-red-50 transition" title="Xóa">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>

                        <div class="w-24 h-24 shrink-0 flex items-center justify-center">
                            <img src="<?= htmlspecialchars($item['image']) ?>" class="max-w-full max-h-full object-contain">
                        </div>
                        
                        <div class="flex-1 flex flex-col justify-between">
                            <a href="product_detail.php?id=<?= $item['product_id'] ?>" class="font-medium text-gray-800 hover:text-primary pr-8 line-clamp-2 leading-snug">
                                <?= htmlspecialchars($item['name']) ?>
                            </a>
                            <div class="text-danger font-bold text-lg"><?= number_format($item['price']) ?>đ</div>
                            
                            <!-- Tăng giảm số lượng -->
                            <div class="flex items-center gap-2 mt-2">
                                <form method="POST" class="flex items-center border border-gray-300 rounded overflow-hidden">
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
                        <span class="font-bold text-gray-800">Tổng cộng:</span>
                        <span class="font-extrabold text-2xl text-danger"><?= number_format($total_price) ?>đ</span>
                    </div>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="checkout.php" class="block text-center w-full bg-gradient-to-b from-[#fd3a3a] to-[#d70018] text-white rounded-lg py-3 font-bold text-lg shadow-md hover:from-[#e32424] hover:to-[#c30016] transition">TIẾN HÀNH ĐẶT HÀNG</a>
                    <?php else: ?>
                        <button class="w-full bg-primary text-white rounded-lg py-3 font-bold text-lg shadow-md" onclick="document.getElementById('loginModal').classList.remove('hidden')">ĐĂNG NHẬP ĐỂ ĐẶT HÀNG</button>
                        <p class="text-xs text-center text-gray-500 mt-2">Dữ liệu giỏ hàng sẽ được lưu lại sau khi bạn đăng nhập.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>