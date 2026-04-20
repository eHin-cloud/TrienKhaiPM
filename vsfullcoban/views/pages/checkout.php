<?php
/**
 * ============================================================
 * CHECKOUT.PHP - TRANG THANH TOÁN & XÁC NHẬN ĐƠN HÀNG
 * (ĐÃ CẬP NHẬT TÍCH HỢP SERVICE LAYER)
 * ============================================================
 * 
 * Logic đặt hàng giờ đây được chuyển hoàn toàn sang services/checkout_services.php
 * 
 * LUỒNG HOẠT ĐỘNG:
 * 1. Nhận danh sách sản phẩm đã tick từ cart.php (selected_items[])
 * 2. Hiển thị form nhập thông tin người nhận (Họ tên, SĐT, Địa chỉ)
 * 3. Chọn phương thức thanh toán (QR / COD)
 * 4. Hiển thị hóa đơn tóm tắt (cột bên phải)
 * 5. Xử lý đặt hàng: Gọi createOrderFromCart() trong Service Layer.
 *    - Hàm service sẽ lo việc Transaction, Sinh mã, Xóa giỏ hàng.
 *    - Redirect/Success state được xử lý sau khi service trả về kết quả.
 * 
 * @requires database.php - Hàm getCartItems()
 * @requires services/checkout_services.php - Hàm xử lý nghiệp vụ chính
 * @requires header.php   - Giao diện header
 * @requires footer.php   - Giao diện footer
 */

// session_start() removed by Router
// database.php is auto-loaded by Router

use App\Service\CheckoutService;
use App\Repository\OrderRepository;
use App\Repository\CartRepository;
use App\Repository\CouponRepository;

// Bắt buộc đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
// Lấy TOÀN BỘ sản phẩm trong giỏ hàng của user
$all_cart_items = getCartItems($db, $user_id);

// ==========================================
// BƯỚC 1: NHẬN DANH SÁCH SP ĐÃ TICK TỪ CART.PHP
// ==========================================
// Cart.php gửi POST mảng selected_items[] chứa cart_id đã tick
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_items'])) {
    // Lưu vào session để không bị mất khi reload trang
    $_SESSION['selected_items'] = $_POST['selected_items'];
}

// Lấy danh sách cart_id đã chọn từ session
$selected_ids = $_SESSION['selected_items'] ?? [];

// Nếu không có SP nào được chọn -> redirect về giỏ hàng
if (empty($selected_ids)) {
    header("Location: cart.php");
    exit;
}

// ==========================================
// BƯỚC 2: LỌC CHỈ GIỮ SP ĐÃ TICK
// ==========================================
// Dùng array_filter để chỉ giữ lại các SP có cart_id nằm trong danh sách đã tick
$cart_items = array_filter($all_cart_items, function ($item) use ($selected_ids) {
    return in_array($item['cart_id'], $selected_ids);
});

// Nếu lọc xong mà không còn SP nào (có thể do SP đã bị xóa) -> về giỏ hàng
if (empty($cart_items)) {
    header("Location: cart.php");
    exit;
}

// Tính toán tổng tiền đơn hàng ban đầu (chỉ để hiển thị trên giao diện trước khi áp voucher)
$total_price = array_reduce($cart_items, function ($sum, $item) {
    return $sum + ($item['price'] * $item['quantity']);
}, 0);

$order_success = false;  // Cờ đánh dấu đặt hàng thành công (dùng cho COD)
$order_id = 0;           // Mã đơn hàng sau khi tạo xong

// Khởi tạo trạng thái voucher (sẽ được gọi lại bằng AJAX)
$applied_discount = 0;
$applied_voucher_code = '';
if (isset($_SESSION['applied_voucher'])) {
    $applied_voucher_code = $_SESSION['applied_voucher']['code'];
    $applied_discount = $_SESSION['applied_voucher']['discount_amount'];
}
$display_final_price = $total_price - $applied_discount;
if ($display_final_price < 0)
    $display_final_price = 0;


// ==========================================
// BƯỚC 3: XỬ LÝ KHI KHÁCH BẤM "XÁC NHẬN ĐẶT HÀNG"
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    // Thu thập thông tin người nhận từ form
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $note = trim($_POST['note']);
    $payment_method = $_POST['payment_method'] ?? 'cod'; // Phương thức: 'qr' hoặc 'cod'

    // Validate dữ liệu bắt buộc
    if (!empty($fullname) && !empty($phone) && !empty($address)) {
        try {
            // ***************************************************************
            // **** SỬ DỤNG SERVICE LAYER ĐỂ XỬ LÝ TOÀN BỘ TRANSACTION ****
            // ***************************************************************

            // 1. Khởi tạo Repository và Service Class chuẩn Giai đoạn 3
            $orderRepo = new OrderRepository($db);
            $cartRepo = new CartRepository($db);
            $couponRepo = new CouponRepository($db);
            $checkoutService = new CheckoutService($db, $orderRepo, $cartRepo, $couponRepo);

            // 2. Gọi method của Service (Sử dụng các biến đã được tính toán ở trên)
            $result = $checkoutService->createOrderFromCart(
                $user_id,
                $cart_items,
                $fullname,
                $phone,
                $address,
                $note,
                $payment_method,
                $applied_voucher_code,
                $applied_discount,
                $display_final_price
            );
            if ($result['success']) {
                $order_id = $result['order_id'];
                $order_success = ($payment_method === 'cod');

                // Cập nhật và reset session sau thành công
                unset($_SESSION['selected_items']);
                if (isset($_SESSION['applied_voucher'])) {
                    unset($_SESSION['applied_voucher']);
                }

                // Kiểm tra xem cần redirect hay chỉ cần hiển thị thành công
                if ($payment_method === 'qr') {
                    header("Location: payment.php?order_id=" . $order_id);
                    exit;
                }
                // Nếu là COD, $order_success = true, và form sẽ hiển thị thành công.
            } else {
                // Xử lý lỗi từ service
                die("Lỗi hệ thống khi đặt hàng: " . $result['message']);
            }

        } catch (Exception $e) {
            die("Lỗi hệ thống nghiêm trọng khi kết nối: " . $e->getMessage());
        }
    }
}

// Include giao diện header
require_once __DIR__ . '/../partials/header.php';
?>

<!-- ==========================================
     GIAO DIỆN TRANG THANH TOÁN
     ========================================== -->
<div class="container mx-auto px-4 py-8 max-w-5xl">

    <!-- ===== GIAO DIỆN ĐẶT HÀNG THÀNH CÔNG (Chỉ hiện khi COD đặt thành công) ===== -->
    <?php if ($order_success): ?>
        <div class="bg-white p-10 rounded-xl shadow-sm border border-gray-200 text-center max-w-2xl mx-auto mt-10">
            <!-- Icon check thành công -->
            <div
                class="w-20 h-20 bg-green-100 text-green-500 rounded-full flex items-center justify-center text-4xl mx-auto mb-5">
                <i class="fa-solid fa-check"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Đặt hàng thành công!</h2>
            <p class="text-gray-600 mb-6">Cảm ơn bạn đã mua sắm tại Điện Máy PRO. Mã đơn hàng của bạn là <b
                    class="text-primary text-lg">#
                    <?= $order_id ?>
                </b>.<br>Nhân viên của chúng tôi sẽ gọi điện xác nhận trong ít phút nữa.</p>
            <!-- Nút điều hướng -->
            <a href="index.php"
                class="bg-primary text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-800 transition shadow-md inline-block">Tiếp
                tục mua sắm</a>
            <a href="track_order.php"
                class="bg-gray-100 text-gray-700 px-8 py-3 rounded-lg font-bold hover:bg-gray-200 transition shadow-md inline-block ml-2 border border-gray-200">Xem
                lại đơn hàng</a>
        </div>

        <!-- ===== GIAO DIỆN FORM THANH TOÁN (Mặc định) ===== -->
    <?php else: ?>
        <h1 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
            <i class="fa-solid fa-money-check-dollar text-primary"></i> Thông tin thanh toán
        </h1>

        <form method="POST" action="checkout.php" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- === CỘT TRÁI: Form thông tin người nhận === -->
            <div class="lg:col-span-2">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Thông tin người nhận</h3>

                    <!-- Họ tên + SĐT -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="col-span-full">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Họ và tên *</label>
                            <!-- Tự động điền tên từ session nếu đã đăng nhập -->
                            <input type="text" name="fullname" required
                                class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none"
                                value="<?= htmlspecialchars($_SESSION['fullname'] ?? '') ?>">
                        </div>
                        <div class="col-span-full">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Số điện thoại *</label>
                            <!-- Validate pattern 10 chữ số -->
                            <input type="tel" name="phone" required pattern="[0-9]{10}" placeholder="VD: 0901234567"
                                class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                        </div>
                    </div>

                    <!-- Địa chỉ giao hàng -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Địa chỉ giao hàng chi tiết *</label>
                        <input type="text" name="address" required
                            placeholder="Số nhà, Tên đường, Phường/Xã, Quận/Huyện, Tỉnh/Thành phố..."
                            class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                    </div>

                    <!-- Ghi chú (tùy chọn) -->
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ghi chú đơn hàng (Tùy chọn)</label>
                        <textarea name="note" rows="3" placeholder="Ghi chú thêm về thời gian giao hàng, chỉ dẫn địa chỉ..."
                            class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none"></textarea>
                    </div>
                </div>

                <!-- === PHƯƠNG THỨC THANH TOÁN === -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mt-6">
                    <h3 class="font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Phương thức thanh toán</h3>

                    <!-- Lựa chọn 1: Chuyển khoản QR (khuyên dùng - checked mặc định) -->
                    <label
                        class="flex items-start gap-3 p-4 border border-blue-200 bg-blue-50 rounded-lg mb-3 cursor-pointer transition relative group">
                        <input type="radio" name="payment_method" value="qr" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] == 'qr') ? 'checked' : '' ?> class="mt-1 w-4 h-4 text-primary
                    accent-primary">
                        <div class="flex-1">
                            <div class="font-bold text-gray-800 flex items-center gap-2">Chuyển khoản QR Code <span
                                    class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full animate-pulse">Khuyên
                                    dùng</span></div>
                            <div class="text-sm text-gray-600 mt-1">Mở ứng dụng ngân hàng và quét mã QR. Nhanh chóng, tiện
                                lợi, tự động xác nhận.</div>
                            <!-- Logo đối tác thanh toán -->
                            <div class="flex gap-2 mt-2">
                                <img src="https://vnpay.vn/assets/images/logo-icon/logo-primary.svg" class="h-5">
                                <img src="https://cdn.haitrieu.com/wp-content/uploads/2022/10/Logo-MoMo-Circle.png"
                                    class="h-5">
                            </div>
                        </div>
                    </label>

                    <!-- Lựa chọn 2: Thanh toán khi nhận hàng (COD) -->
                    <label
                        class="flex items-start gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition">
                        <input type="radio" name="payment_method" value="cod" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cod') ? 'checked' : '' ?> class="mt-1 w-4 h-4 text-primary
                    accent-primary">
                        <div class="flex-1">
                            <div class="font-bold text-gray-800">Thanh toán khi nhận hàng (COD)</div>
                            <div class="text-sm text-gray-600 mt-1">Khách hàng thanh toán bằng tiền mặt khi shipper giao
                                hàng tới nơi.</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- === CỘT PHẢI: Hóa đơn tóm tắt === -->
            <div class="lg:col-span-1">
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 sticky top-24">
                    <h3 class="font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Đơn hàng của bạn (
                        <?= count($cart_items) ?> sp)
                    </h3>

                    <!-- Danh sách sản phẩm đã chọn (scrollable nếu nhiều) -->
                    <div class="space-y-3 mb-4 max-h-[300px] overflow-y-auto pr-2 hide-scrollbar">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="flex justify-between items-start gap-3 text-sm">
                                <div class="flex items-start gap-2 flex-1">
                                    <div class="font-medium text-gray-800 w-6 shrink-0">
                                        <?= $item['quantity'] ?>x
                                    </div>
                                    <div class="text-gray-600 line-clamp-2">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </div>
                                </div>
                                <div class="font-bold text-gray-800 shrink-0">
                                    <?= number_format($item['price'] * $item['quantity']) ?>đ
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Mã giảm giá -->
                    <div class="mb-4 bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mã giảm giá</label>
                        <div class="flex gap-2">
                            <input type="text" id="voucherCodeInput" value="<?= htmlspecialchars($applied_voucher_code) ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary outline-none uppercase"
                                placeholder="Nhập mã...">
                            <button type="button" onclick="applyVoucher()"
                                class="bg-gray-800 text-white px-4 shrink-0 rounded font-bold text-sm hover:bg-gray-700 transition">Áp
                                dụng</button>
                        </div>
                        <p id="voucherMessage"
                            class="text-xs mt-2 <?= $applied_discount > 0 ? 'text-green-600 font-bold' : 'hidden' ?>">
                            <?= $applied_discount > 0 ? 'Mã đã được áp dụng!' : '' ?>
                        </p>
                    </div>

                    <!-- Tóm tắt giá tiền -->
                    <div class="border-t border-gray-100 pt-4 mb-6" id="summaryBlock">
                        <div class="flex justify-between items-center mb-2 text-sm text-gray-600">
                            <span>Tạm tính:</span>
                            <span id="subTotalStr" data-value="<?= $total_price ?>">
                                <?= number_format($total_price) ?>đ
                            </span>
                        </div>
                        <div class="flex justify-between items-center mb-2 text-sm text-gray-600">
                            <span>Phí vận chuyển:</span>
                            <span class="text-green-600 font-medium">Miễn phí</span>
                        </div>
                        <div class="flex justify-between items-center mb-2 text-sm font-bold text-green-600 <?= $applied_discount > 0 ? '' : 'hidden' ?>"
                            id="discountRow">
                            <span>Giảm giá:</span>
                            <span id="discountValStr">-
                                <?= number_format($applied_discount) ?>đ
                            </span>
                        </div>
                        <div class="flex justify-between items-center mt-4 border-t border-gray-100 pt-3">
                            <span class="font-bold text-gray-800">Thành tiền:</span>
                            <span class="font-extrabold text-2xl text-danger" id="finalTotalStr">
                                <?= number_format($display_final_price) ?>đ
                            </span>
                        </div>
                        <div class="text-[11px] text-right text-gray-500 italic mt-1">(Đã bao gồm VAT nếu có)</div>
                    </div>

                    <!-- Nút xác nhận đặt hàng -->
                    <button type="submit" name="submit_order"
                        class="w-full bg-gradient-to-b from-[#fd3a3a] to-[#d70018] text-white rounded-lg py-3.5 font-bold text-lg shadow-md hover:opacity-90 transition">XÁC
                        NHẬN ĐẶT HÀNG</button>
                    <a href="cart.php" class="block text-center text-primary text-sm mt-4 hover:underline"><i
                            class="fa-solid fa-arrow-left mr-1"></i> Quay lại giỏ hàng</a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
    function applyVoucher() {
        const codeInput = document.getElementById('voucherCodeInput');
        const code = codeInput.value.trim();
        const msgEl = document.getElementById('voucherMessage');
        const subTotal = parseFloat(document.getElementById('subTotalStr').getAttribute('data-value'));

        if (!code) {
            msgEl.textContent = 'Vui lòng nhập mã giảm giá!';
            msgEl.className = 'text-xs mt-2 text-red-500 font-bold';
            return;
        }

        fetch('ajax_voucher.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ code: code, total_price: subTotal })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Thành công
                    msgEl.textContent = data.message + ' (' + data.discount_text + ')';
                    msgEl.className = 'text-xs mt-2 text-green-600 font-bold';

                    // Cập nhật DOM
                    const discountRow = document.getElementById('discountRow');
                    discountRow.classList.remove('hidden');
                    document.getElementById('discountValStr').textContent = '-' + new Intl.NumberFormat('vi-VN').format(data.discount_amount) + 'đ';
                    document.getElementById('finalTotalStr').textContent = new Intl.NumberFormat('vi-VN').format(data.new_total) + 'đ';
                    // Highlight input field
                    codeInput.classList.add('border-green-500', 'bg-green-50');
                } else {
                    // Lỗi
                    msgEl.textContent = data.message;
                    msgEl.className = 'text-xs mt-2 text-red-500 font-bold';
                    document.getElementById('discountRow').classList.add('hidden');
                    document.getElementById('finalTotalStr').textContent = new Intl.NumberFormat('vi-VN').format(subTotal) + 'đ';
                    codeInput.classList.remove('border-green-500', 'bg-green-50');
                }
            })
            .catch(err => {
                console.error('Error applying voucher:', err);
                msgEl.textContent = 'Đã xảy ra lỗi hệ thống khi áp dụng mã.';
                msgEl.className = 'text-xs mt-2 text-red-500 font-bold';
            });
    }
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>