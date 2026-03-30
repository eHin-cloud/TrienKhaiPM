<?php
session_start();
require_once 'database.php';

// Bắt buộc đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cart_items = getCartItems($db, $user_id);

// Nếu giỏ hàng trống thì đuổi về trang chủ
if (empty($cart_items)) {
    header("Location: index.php");
    exit;
}

$total_price = array_reduce($cart_items, function($sum, $item) {
    return $sum + ($item['price'] * $item['quantity']);
}, 0);

$order_success = false;

// XỬ LÝ KHI KHÁCH BẤM ĐẶT HÀNG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $note = trim($_POST['note']);

    if (!empty($fullname) && !empty($phone) && !empty($address)) {
        try {
            // Bắt đầu Transaction (Đảm bảo an toàn dữ liệu, lỗi 1 bước là hủy toàn bộ)
            $db->beginTransaction();

            // 1. Tạo đơn hàng vào bảng orders
            $stmtOrder = $db->prepare("INSERT INTO orders (user_id, fullname, phone, address, note, total_price) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtOrder->execute([$user_id, $fullname, $phone, $address, $note, $total_price]);
            $order_id = $db->lastInsertId(); // Lấy mã đơn hàng vừa tạo

            // 2. Lưu chi tiết sản phẩm vào order_details
            $stmtDetail = $db->prepare("INSERT INTO order_details (order_id, product_id, price, quantity) VALUES (?, ?, ?, ?)");
            foreach ($cart_items as $item) {
                $stmtDetail->execute([$order_id, $item['product_id'], $item['price'], $item['quantity']]);
            }

            // 3. Xóa sạch giỏ hàng của user này
            $stmtClearCart = $db->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmtClearCart->execute([$user_id]);

            // Lưu thành công
            $db->commit();
            $order_success = true;

        } catch (Exception $e) {
            $db->rollBack();
            die("Lỗi hệ thống khi đặt hàng: " . $e->getMessage());
        }
    }
}

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-8 max-w-5xl">
    
    <?php if ($order_success): ?>
        <!-- GIAO DIỆN ĐẶT HÀNG THÀNH CÔNG -->
        <div class="bg-white p-10 rounded-xl shadow-sm border border-gray-200 text-center max-w-2xl mx-auto mt-10">
            <div class="w-20 h-20 bg-green-100 text-green-500 rounded-full flex items-center justify-center text-4xl mx-auto mb-5">
                <i class="fa-solid fa-check"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Đặt hàng thành công!</h2>
            <p class="text-gray-600 mb-6">Cảm ơn bạn đã mua sắm tại Điện Máy PRO. Mã đơn hàng của bạn là <b>#<?= $order_id ?></b>.<br>Nhân viên của chúng tôi sẽ gọi điện xác nhận trong ít phút nữa.</p>
            <a href="index.php" class="bg-primary text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-800 transition shadow-md inline-block">Tiếp tục mua sắm</a>
        </div>

    <?php else: ?>
        <!-- GIAO DIỆN FORM THANH TOÁN -->
        <h1 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
            <i class="fa-solid fa-money-check-dollar text-primary"></i> Thông tin thanh toán
        </h1>

        <form method="POST" action="checkout.php" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Cột trái: Form thông tin -->
            <div class="lg:col-span-2">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Thông tin người nhận</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Họ và tên *</label>
                            <input type="text" name="fullname" required class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none" value="<?= htmlspecialchars($_SESSION['fullname'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Số điện thoại *</label>
                            <input type="tel" name="phone" required pattern="[0-9]{10}" placeholder="VD: 0901234567" class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Địa chỉ giao hàng chi tiết *</label>
                        <input type="text" name="address" required placeholder="Số nhà, Tên đường, Phường/Xã, Quận/Huyện, Tỉnh/Thành phố..." class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                    </div>

                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ghi chú đơn hàng (Tùy chọn)</label>
                        <textarea name="note" rows="3" placeholder="Ghi chú thêm về thời gian giao hàng, chỉ dẫn địa chỉ..." class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none"></textarea>
                    </div>
                </div>
            </div>

            <!-- Cột phải: Hóa đơn -->
            <div class="lg:col-span-1">
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 sticky top-24">
                    <h3 class="font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Đơn hàng của bạn (<?= count($cart_items) ?> sp)</h3>
                    
                    <div class="space-y-3 mb-4 max-h-[300px] overflow-y-auto pr-2 hide-scrollbar">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="flex justify-between items-start gap-3 text-sm">
                                <div class="flex items-start gap-2 flex-1">
                                    <div class="font-medium text-gray-800 w-6 shrink-0"><?= $item['quantity'] ?>x</div>
                                    <div class="text-gray-600 line-clamp-2"><?= htmlspecialchars($item['name']) ?></div>
                                </div>
                                <div class="font-bold text-gray-800 shrink-0"><?= number_format($item['price'] * $item['quantity']) ?>đ</div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="border-t border-gray-100 pt-4 mb-6">
                        <div class="flex justify-between items-center mb-2 text-sm text-gray-600">
                            <span>Tạm tính:</span>
                            <span><?= number_format($total_price) ?>đ</span>
                        </div>
                        <div class="flex justify-between items-center mb-2 text-sm text-gray-600">
                            <span>Phí vận chuyển:</span>
                            <span class="text-green-600 font-medium">Miễn phí</span>
                        </div>
                        <div class="flex justify-between items-center mt-4">
                            <span class="font-bold text-gray-800">Thành tiền:</span>
                            <span class="font-extrabold text-2xl text-danger"><?= number_format($total_price) ?>đ</span>
                        </div>
                        <div class="text-[11px] text-right text-gray-500 italic mt-1">(Đã bao gồm VAT nếu có)</div>
                    </div>

                    <div class="bg-blue-50 p-3 rounded-lg mb-6 border border-blue-100 text-sm text-blue-800 flex gap-2 items-start">
                        <i class="fa-solid fa-truck-fast mt-1"></i>
                        <p>Thanh toán bằng tiền mặt khi nhận hàng (COD). Quý khách được kiểm tra hàng trước khi thanh toán.</p>
                    </div>

                    <button type="submit" name="submit_order" class="w-full bg-gradient-to-b from-[#fd3a3a] to-[#d70018] text-white rounded-lg py-3.5 font-bold text-lg shadow-md hover:opacity-90 transition">XÁC NHẬN ĐẶT HÀNG</button>
                    <a href="cart.php" class="block text-center text-primary text-sm mt-4 hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i> Quay lại giỏ hàng</a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>