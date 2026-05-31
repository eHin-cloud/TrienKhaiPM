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
use App\Repository\UserRepository; // Thêm use cho UserRepository

// Bắt buộc đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Lấy thông tin user hiện tại từ Database để tự động điền form
$userRepo = new UserRepository($db);
$currentUser = $userRepo->getUserById($user_id);

// Lấy danh sách địa chỉ từ Address Book
$stmt_addr = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stmt_addr->execute([$user_id]);
$saved_addresses = $stmt_addr->fetchAll();

// Xác định địa chỉ mặc định để pre-fill
$default_addr = null;
foreach ($saved_addresses as $addr) {
    if ($addr['is_default']) {
        $default_addr = $addr;
        break;
    }
}

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

// Khởi tạo Service để tính toán bundle discount và giá cuối
$orderRepo = new OrderRepository($db);
$cartRepo = new CartRepository($db);
$couponRepo = new CouponRepository($db);
$checkoutService = new CheckoutService($db, $orderRepo, $cartRepo, $couponRepo);
$bundleData = $checkoutService->calculateBundleDiscount($cart_items);
$bundle_discount = $bundleData['discount'];
$bundle_message = $bundleData['message'];

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
$total_discount = $applied_discount + $bundle_discount;
$display_final_price = $total_price - $total_discount;
if ($display_final_price < 0) {
    $display_final_price = 0;
}


// ==========================================
// BƯỚC 3: XỬ LÝ KHI KHÁCH BẤM "XÁC NHẬN ĐẶT HÀNG"
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    // Thu thập thông tin người nhận từ form
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $note = trim($_POST['note']);
    $payment_method = $_POST['payment_method'] ?? 'qr'; // Phương thức: 'qr' hoặc 'cod'

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
            $total_discount = $applied_discount + $bundle_discount;
            $result = $checkoutService->createOrderFromCart(
                $user_id,
                $cart_items,
                $fullname,
                $phone,
                $address,
                $note,
                $payment_method,
                $applied_voucher_code,
                $total_discount,
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
                die(__("system_error") . ": " . $result['message']);
            }

        } catch (Exception $e) {
            die(__("system_error") . ": " . $e->getMessage());
        }
    }
}

// Include giao diện header
require_once __DIR__ . '/../partials/header.php';
?>

<!-- ==========================================
     GIAO DIỆN TRANG THANH TOÁN (PREMIUM STYLE)
     ========================================== -->
<style>
@import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap');
.checkout-wrap { font-family: 'Be Vietnam Pro', sans-serif; background-color: #f8fafc; min-height: 100vh; }
.checkout-wrap * { box-sizing: border-box; }
.checkout-wrap .fa, .checkout-wrap .fa-solid, .checkout-wrap .fa-regular, .checkout-wrap .fa-brands, .checkout-wrap [class*="fa-"] {
    font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands", "FontAwesome" !important;
}

/* ── Checkout Hero ── */
.checkout-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4c1d95 100%);
    padding: 36px 20px 80px;
    position: relative; overflow: hidden;
}
.checkout-hero::before {
    content:''; position:absolute; inset:0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.checkout-hero-orb { position:absolute; border-radius:50%; filter:blur(60px); pointer-events:none; }
.checkout-hero-orb-1 { width:300px;height:300px;background:rgba(139,92,246,0.25);top:-100px;right:-50px; }
.checkout-hero-orb-2 { width:200px;height:200px;background:rgba(99,102,241,0.2);bottom:-60px;left:10%; }
.checkout-hero-inner { max-width:1100px;margin:0 auto;display:flex;align-items:center;gap:16px;position:relative;z-index:1; }
.checkout-hero h1 { color:#fff;font-size:clamp(22px,4vw,32px);font-weight:800;margin:0; }
.checkout-hero-sub { color:rgba(255,255,255,0.6);font-size:13px;margin-top:4px; }
.checkout-hero-icon { width:52px;height:52px;background:rgba(255,255,255,0.1);border-radius:16px;
    display:flex;align-items:center;justify-content:center;font-size:22px;color:#c4b5fd;
    border:1px solid rgba(255,255,255,0.15);flex-shrink:0; }

/* ── Main Layout ── */
.checkout-layout { max-width:1100px;margin:-48px auto 0;padding:0 16px 60px;display:grid;grid-template-columns:1fr 350px;gap:20px;align-items:start;position:relative;z-index:5; }

@media(max-width:900px){
    .checkout-layout {
        grid-template-columns:1fr;margin-top:-40px;padding-bottom:90px !important;
    }
    .checkout-summary-wrap {
        position: fixed !important;
        bottom: 0;
        left: 0;
        right: 0;
        top: auto !important;
        z-index: 998;
        border-radius: 20px 20px 0 0;
        box-shadow: 0 -8px 24px rgba(0,0,0,0.12);
        padding: 14px 20px;
        margin: 0;
        border-top: 1px solid #f1f5f9;
        background: #fff;
    }
    .checkout-summary-wrap h3,
    .checkout-summary-wrap .summary-list-products,
    .checkout-summary-wrap .voucher-box,
    .checkout-summary-wrap .summary-item-row:not(.total),
    .checkout-summary-wrap .vat-hint,
    .checkout-summary-wrap .btn-back-link {
        display: none !important;
    }
    .checkout-summary-wrap .summary-item-row.total {
        border-top: none;
        padding-top: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    .checkout-summary-wrap .summary-item-row.total span {
        font-size: 11px;
        color: #94a3b8;
    }
    .checkout-summary-wrap .summary-item-row.total .amount {
        font-size: 18px;
        line-height: 1.2;
    }
    .checkout-summary-wrap .btn-confirm-order {
        margin-top: 0;
        padding: 10px 24px;
        border-radius: 10px;
        font-size: 14px;
        height: 42px;
        width: auto;
    }
    .checkout-summary-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
    }
}

/* ── Cards ── */
.checkout-card {
    background:#fff;border-radius:18px;padding:24px;
    box-shadow:0 2px 12px rgba(0,0,0,0.04);border:1px solid #e2e8f0;
}
.checkout-card h3 {
    font-size:15px;font-weight:800;color:#1e293b;margin:0 0 16px;
    padding-bottom:12px;border-bottom:1.5px solid #f1f5f9;
    display:flex;align-items:center;gap:8px;
}

/* Input styles */
.form-label {
    display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;
}
.form-input {
    width: 100%;
    padding: 10px 16px;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    outline: none;
    transition: all 0.2s;
    font-size: 14px;
    font-weight: 500;
    color: #1e293b;
    display: block;
    box-sizing: border-box;
}
.form-input:focus {
    background: #fff; border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
}

/* Payment Option Labels */
.payment-option-label {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    margin-bottom: 12px;
}
.payment-option-label.active {
    border-color: #c7d2fe; background-color: #f5f3ff;
}
.payment-option-label:hover:not(.active) {
    background-color: #f8fafc; border-color: #cbd5e1;
}

/* Scrollbar styling */
.hide-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.hide-scrollbar::-webkit-scrollbar-track {
    background: #f1f5f9;
}
.hide-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

/* Voucher box */
.voucher-box {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-bottom: 16px;
}
.voucher-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    outline: none;
    text-transform: uppercase;
    font-size: 14px;
    font-weight: 600;
    background-color: #fff;
    transition: all 0.2s;
    box-sizing: border-box;
    display: block;
}
.voucher-input:focus {
    border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.btn-apply-voucher {
    background-color: #1e293b;
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    transition: all 0.2s;
    cursor: pointer;
    border: none;
    outline: none;
}
.btn-apply-voucher:hover {
    background: #0f172a;
}

/* Summary item rows */
.checkout-summary-wrap {
    background:#fff;border-radius:20px;padding:24px;
    box-shadow:0 4px 20px rgba(0,0,0,0.06);border:1px solid #f1f5f9;
    position:sticky;top:80px;
}
.checkout-summary-wrap h3 {
    font-size:15px;font-weight:800;color:#1e293b;margin:0 0 16px;
    padding-bottom:12px;border-bottom:1px solid #f1f5f9;
}
.summary-item-row {
    display:flex;justify-content:space-between;align-items:center;
    font-size:13.5px;color:#64748b;margin-bottom:12px;
}
.summary-item-row b { color:#1e293b;font-weight:700; }
.summary-item-row.total {
    border-top:1px dashed #e2e8f0;padding-top:16px;margin-top:8px;
    font-size:14px;font-weight:700;color:#1e293b;
}
.summary-item-row.total .amount { font-size:22px;font-weight:900;color:#ef4444; }

.btn-confirm-order {
    width: 100%; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #fff; border: none; border-radius: 12px; padding: 14px;
    font-size: 15px; font-weight: 800; cursor: pointer; margin-top: 16px;
    box-shadow: 0 6px 20px rgba(239,68,68,0.3); transition: all 0.25s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-confirm-order:hover {
    transform: translateY(-2px); box-shadow: 0 10px 28px rgba(239,68,68,0.4);
}
.btn-confirm-order:active {
    transform: translateY(0);
}
</style>

<div class="checkout-wrap pb-12">

    <!-- HERO -->
    <div class="checkout-hero">
        <div class="checkout-hero-orb checkout-hero-orb-1"></div>
        <div class="checkout-hero-orb checkout-hero-orb-2"></div>
        <div class="checkout-hero-inner">
            <div class="checkout-hero-icon"><i class="fa-solid fa-money-check-dollar"></i></div>
            <div>
                <h1><?= __("checkout_info") ?></h1>
                <div class="checkout-hero-sub"><?= count($cart_items) ?> <?= __("products_count") ?> <?= __("cart_items_waiting") ?></div>
            </div>
        </div>
    </div>

    <!-- ===== GIAO DIỆN ĐẶT HÀNG THÀNH CÔNG (Chỉ hiện khi COD đặt thành công) ===== -->
    <?php if ($order_success): ?>
        <div class="checkout-layout mt-10" style="grid-template-columns: 1fr; max-w: 650px;">
            <div class="checkout-card text-center py-12 px-8">
                <!-- Icon check thành công -->
                <div class="w-20 h-20 bg-green-50 text-green-500 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 border border-green-100 shadow-sm">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h2 class="text-2xl font-extrabold text-gray-800 mb-3"><?= __("order_success_title") ?></h2>
                <p class="text-gray-600 mb-8 max-w-md mx-auto leading-relaxed">
                    <?= __("order_success_msg_prefix") ?> 
                    <span class="text-indigo-600 font-extrabold text-xl">#<?= $order_id ?></span>.<br>
                    <?= __("order_success_callback") ?>
                </p>
                <!-- Nút điều hướng -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                    <a href="index.php" class="w-full sm:w-auto bg-gradient-to-r from-indigo-600 to-violet-600 text-white px-8 py-3.5 rounded-xl font-bold hover:opacity-95 transition shadow-lg shadow-indigo-600/20 text-center">
                        <i class="fa-solid fa-bag-shopping mr-2"></i> <?= __("continue_shopping") ?>
                    </a>
                    <a href="track_order.php" class="w-full sm:w-auto bg-slate-100 text-slate-700 px-8 py-3.5 rounded-xl font-bold hover:bg-slate-200 transition text-center border border-slate-200">
                        <i class="fa-solid fa-receipt mr-2"></i> <?= __("view_order") ?>
                    </a>
                </div>
            </div>
        </div>

    <!-- ===== GIAO DIỆN FORM THANH TOÁN (Mặc định) ===== -->
    <?php else: ?>
        <form method="POST" action="checkout.php" class="checkout-layout" id="checkoutForm" onsubmit="return validateCheckout()">
            <?= csrf_input_field() ?>

            <!-- === CỘT TRÁI: Form thông tin người nhận === -->
            <div class="space-y-6">
                <div class="checkout-card">
                    <div class="flex justify-between items-center mb-5 pb-2 border-b border-slate-100">
                        <h3 class="font-bold text-gray-800" style="margin: 0; padding: 0; border: none;">
                            <i class="fa-solid fa-user-gear text-indigo-500 mr-1"></i><?= __("receiver_info") ?>
                        </h3>
                        <?php if (!empty($saved_addresses)): ?>
                            <button type="button" onclick="openAddressPicker()" class="text-xs text-indigo-600 font-bold hover:text-indigo-800 transition flex items-center gap-1">
                                <i class="fa-solid fa-address-book"></i> <?= __("select_saved_address") ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Họ tên + SĐT -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="form-label"><?= __("fullname") ?> *</label>
                            <input type="text" name="fullname" id="checkout-fullname" required
                                class="form-input"
                                value="<?= htmlspecialchars($default_addr['fullname'] ?? ($_SESSION['fullname'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="form-label"><?= __("phone") ?> *</label>
                            <input type="tel" name="phone" id="checkout-phone" required pattern="[0-9]{10}" placeholder="<?= __("phone_placeholder") ?>"
                                value="<?= htmlspecialchars($default_addr['phone'] ?? ($currentUser['phone'] ?? '')) ?>"
                                class="form-input">
                        </div>
                    </div>

                    <!-- Địa chỉ giao hàng -->
                    <div class="mb-4">
                        <label class="form-label"><?= __("detailed_address") ?> *</label>
                        <input type="text" name="address" id="checkout-address" required
                            value="<?= htmlspecialchars($default_addr['address'] ?? ($currentUser['address'] ?? '')) ?>"
                            placeholder="<?= __("address_placeholder") ?>"
                            class="form-input">
                    </div>

                    <!-- Ghi chú (tùy chọn) -->
                    <div class="mb-2">
                        <label class="form-label"><?= __("order_note") ?></label>
                        <textarea name="note" rows="3" placeholder="<?= __("order_note_placeholder") ?>"
                            class="form-input" style="resize: none;"></textarea>
                    </div>
                </div>

                <!-- === PHƯƠNG THỨC THANH TOÁN === -->
                <div class="checkout-card">
                    <h3><i class="fa-solid fa-credit-card text-indigo-500 mr-1"></i><?= __("payment_method") ?></h3>

                    <?php $activeMethod = $_POST['payment_method'] ?? 'qr'; ?>

                    <!-- Lựa chọn 1: Chuyển khoản QR (khuyên dùng - checked mặc định) -->
                    <label id="payment-label-qr"
                        class="payment-option-label <?= $activeMethod === 'qr' ? 'active' : '' ?>">
                        <input type="radio" name="payment_method" value="qr" <?= $activeMethod === 'qr' ? 'checked' : '' ?> class="mt-1 w-4 h-4 text-indigo-600 accent-indigo-600">
                        <div class="flex-1">
                            <div class="font-bold text-gray-800 flex items-center gap-2" style="font-size:13.5px">
                                <?= __("qr_payment") ?> 
                                <span class="bg-gradient-to-r from-red-500 to-orange-500 text-white text-[9px] px-2 py-0.5 rounded-full font-bold animate-pulse">
                                    <?= __("recommended") ?>
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1 leading-relaxed"><?= __("qr_desc") ?></div>
                            <!-- Logo đối tác thanh toán -->
                            <div class="flex gap-2 mt-2.5">
                                <img src="https://vnpay.vn/assets/images/logo-icon/logo-primary.svg" class="object-contain" style="height: 18px;">
                                <img src="https://cdn.haitrieu.com/wp-content/uploads/2022/10/Logo-MoMo-Circle.png" class="object-contain" style="height: 18px;">
                            </div>
                        </div>
                    </label>

                    <!-- Lựa chọn 2: Thanh toán khi nhận hàng (COD) -->
                    <label id="payment-label-cod"
                        class="payment-option-label <?= $activeMethod === 'cod' ? 'active' : '' ?>">
                        <input type="radio" name="payment_method" value="cod" <?= $activeMethod === 'cod' ? 'checked' : '' ?> class="mt-1 w-4 h-4 text-indigo-600 accent-indigo-600">
                        <div class="flex-1">
                            <div class="font-bold text-gray-800" style="font-size:13.5px"><?= __("cod_payment") ?></div>
                            <div class="text-xs text-gray-500 mt-1 leading-relaxed"><?= __("cod_desc") ?></div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- === CỘT PHẢI: Hóa đơn tóm tắt === -->
            <div class="checkout-summary-wrap">
                <h3><i class="fa-solid fa-receipt text-indigo-500 mr-1"></i><?= __("your_order") ?> (<?= count($cart_items) ?>)</h3>

                <!-- Danh sách sản phẩm đã chọn (scrollable nếu nhiều) -->
                <div class="space-y-3 mb-4 max-h-[220px] overflow-y-auto pr-1.5 hide-scrollbar summary-list-products">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="flex justify-between items-start gap-3 text-xs border-b border-slate-50 pb-2">
                            <div class="flex items-start gap-2 flex-1">
                                <div class="font-bold text-indigo-600 w-5 shrink-0 text-center bg-indigo-50 rounded py-0.5">
                                    <?= $item['quantity'] ?>
                                </div>
                                <div class="text-gray-700 font-medium line-clamp-2">
                                    <?= htmlspecialchars(getCurrentLang() === 'en' ? translate_text($item['name'], 'prod_name_' . $item['product_id']) : $item['name']) ?>
                                </div>
                            </div>
                            <div class="font-bold text-gray-800 shrink-0">
                                <?= number_format($item['price'] * $item['quantity']) ?>đ
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Mã giảm giá -->
                <div class="voucher-box">
                    <label class="block text-xs font-bold text-gray-600 mb-2"><?= __("coupon_code") ?></label>
                    <div class="flex gap-2">
                        <input type="text" id="voucherCodeInput" value="<?= htmlspecialchars($applied_voucher_code) ?>"
                            class="voucher-input"
                            placeholder="<?= __("enter_coupon") ?>">
                        <button type="button" onclick="applyVoucher()" class="btn-apply-voucher"><?= __("apply") ?></button>
                    </div>
                    <p id="voucherMessage"
                        class="text-xs mt-2 <?= $applied_discount > 0 ? 'text-green-600 font-bold' : 'hidden' ?>">
                        <?= $applied_discount > 0 ? __("coupon_applied") : '' ?>
                    </p>
                </div>

                <!-- Tóm tắt giá tiền -->
                <div class="pt-2" id="summaryBlock">
                    <div class="checkout-summary-container">
                        <div class="summary-item-row">
                            <span><?= __("subtotal") ?>:</span>
                            <span id="subTotalStr" data-value="<?= $total_price ?>" class="font-bold text-gray-800">
                                <?= number_format($total_price) ?>đ
                            </span>
                        </div>
                        <div class="summary-item-row">
                            <span><?= __("shipping_fee") ?>:</span>
                            <span class="text-green-600 font-bold"><?= __("free") ?></span>
                        </div>
                        <div class="summary-item-row font-bold text-green-600 <?= $applied_discount > 0 ? '' : 'hidden' ?>"
                            id="discountRow">
                            <span><?= __("voucher_discount") ?>:</span>
                            <span id="discountValStr">-<?= number_format($applied_discount) ?>đ</span>
                        </div>
                        <div class="summary-item-row font-bold text-green-600 <?= $bundle_discount > 0 ? '' : 'hidden' ?>"
                            id="bundleRow">
                            <span><?= __("combo_discount") ?>:</span>
                            <span id="bundleValStr" data-value="<?= $bundle_discount ?>">-<?= number_format($bundle_discount) ?>đ</span>
                        </div>
                        <?php if ($bundle_discount > 0 && !empty($bundle_message)): ?>
                            <div class="text-[11px] text-gray-500 mb-2 bg-indigo-50/50 p-2 rounded-lg border border-indigo-100/50"><i class="fa-solid fa-sparkles text-indigo-500 mr-1"></i><?= htmlspecialchars($bundle_message) ?></div>
                        <?php endif; ?>
                        <div class="summary-item-row total">
                            <span><?= __("final_total") ?></span>
                            <span class="amount" id="finalTotalStr"><?= number_format($display_final_price) ?>đ</span>
                        </div>
                    </div>
                    <div class="text-[10px] text-right text-gray-400 italic mt-1 vat-hint"><?= __("vat_included") ?></div>
                </div>

                <!-- Nút xác nhận đặt hàng -->
                <button type="submit" name="submit_order" class="btn-confirm-order">
                    <i class="fa-solid fa-circle-check"></i> <?= __("confirm_order") ?>
                </button>
                
                <a href="cart.php" class="block text-center text-xs text-indigo-600 font-bold mt-4 hover:underline btn-back-link">
                    <i class="fa-solid fa-arrow-left mr-1"></i> <?= __("back_to_cart") ?>
                </a>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
    // --- VALIDATION FORM ---
    function validateCheckout() {
        const fullname = document.getElementById('checkout-fullname').value.trim();
        const phone = document.getElementById('checkout-phone').value.trim();
        const address = document.getElementById('checkout-address').value.trim();
        
        if (!fullname) {
            Swal.fire({
                icon: 'error',
                title: '<?= __("error") ?>',
                text: '<?= __("please_enter_fullname") ?>',
                confirmButtonColor: '#6366f1'
            });
            return false;
        }
        if (!phone || !/^\d{10}$/.test(phone)) {
            Swal.fire({
                icon: 'error',
                title: '<?= __("error") ?>',
                text: '<?= __("please_enter_valid_phone") ?>',
                confirmButtonColor: '#6366f1'
            });
            return false;
        }
        if (!address) {
            Swal.fire({
                icon: 'error',
                title: '<?= __("error") ?>',
                text: '<?= __("fill_all_info") ?>',
                confirmButtonColor: '#6366f1'
            });
            return false;
        }
        return true;
    }

    // --- ÁP DỤNG MÃ GIẢM GIÁ ---
    function applyVoucher() {
        const codeInput = document.getElementById('voucherCodeInput');
        const code = codeInput.value.trim();
        const msgEl = document.getElementById('voucherMessage');
        const subTotal = parseFloat(document.getElementById('subTotalStr').getAttribute('data-value'));
        const bundleDiscount = parseFloat(document.getElementById('bundleValStr').dataset.value || 0);

        if (!code) {
            msgEl.textContent = '<?= __("please_enter_coupon") ?>';
            msgEl.className = 'text-xs mt-2 text-red-500 font-bold';
            msgEl.classList.remove('hidden');
            return;
        }

        fetch('ajax_voucher.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ code: code, total_price: subTotal, bundle_discount: bundleDiscount, csrf_token: '<?= generate_csrf_token() ?>' })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Thành công
                    msgEl.textContent = data.message + ' (' + data.discount_text + ')';
                    msgEl.className = 'text-xs mt-2 text-green-600 font-bold';
                    msgEl.classList.remove('hidden');

                    // Cập nhật DOM
                    const discountRow = document.getElementById('discountRow');
                    discountRow.classList.remove('hidden');
                    document.getElementById('discountValStr').textContent = '-' + new Intl.NumberFormat('vi-VN').format(data.discount_amount) + 'đ';
                    document.getElementById('finalTotalStr').textContent = new Intl.NumberFormat('vi-VN').format(data.new_total) + 'đ';
                    // Highlight input field
                    codeInput.classList.add('border-green-500', 'bg-green-50');
                    codeInput.classList.remove('border-red-500', 'bg-red-50');
                } else {
                    // Lỗi
                    msgEl.textContent = data.message;
                    msgEl.className = 'text-xs mt-2 text-red-500 font-bold';
                    msgEl.classList.remove('hidden');
                    document.getElementById('discountRow').classList.add('hidden');
                    const bundleDiscount = parseFloat(document.getElementById('bundleValStr').dataset.value || 0);
                    document.getElementById('finalTotalStr').textContent = new Intl.NumberFormat('vi-VN').format(subTotal - bundleDiscount) + 'đ';
                    codeInput.classList.add('border-red-500', 'bg-red-50');
                    codeInput.classList.remove('border-green-500', 'bg-green-50');
                }
            })
            .catch(err => {
                console.error('Error applying voucher:', err);
                msgEl.textContent = '<?= __("coupon_error") ?>';
                msgEl.className = 'text-xs mt-2 text-red-500 font-bold';
                msgEl.classList.remove('hidden');
            });
    }
</script>

<!-- Modal Chọn địa chỉ đã lưu -->
<div id="addressPickerModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden" style="font-family: 'Be Vietnam Pro', sans-serif;">
        <div class="p-5 border-b flex justify-between items-center bg-gray-50">
            <h3 class="font-extrabold text-gray-800 text-sm flex items-center gap-2" style="margin: 0; padding: 0; border: none;">
                <i class="fa-solid fa-map-location-dot text-indigo-500"></i><?= __("choose_shipping_address") ?>
            </h3>
            <button onclick="closeAddressPicker()" class="text-gray-400 hover:text-gray-600 transition"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 max-h-[380px] overflow-y-auto space-y-3 hide-scrollbar">
            <?php if (!empty($saved_addresses)): ?>
                <?php foreach ($saved_addresses as $addr): ?>
                    <div class="p-4 border border-gray-100 rounded-xl hover:border-indigo-400 hover:bg-indigo-50/30 transition-all cursor-pointer group shadow-sm" 
                         onclick='selectAddress(<?= json_encode($addr) ?>)'>
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="font-bold text-gray-800 group-hover:text-indigo-600 transition text-[13.5px]"><?= htmlspecialchars($addr['fullname']) ?></span>
                            <span class="text-gray-300">|</span>
                            <span class="text-gray-600 font-medium text-xs"><?= htmlspecialchars($addr['phone']) ?></span>
                            <?php if ($addr['is_default']): ?>
                                <span class="bg-indigo-600 text-white text-[8px] px-1.5 py-0.5 rounded font-extrabold uppercase tracking-wide ml-auto"><?= __("default") ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-gray-500 leading-relaxed"><?= htmlspecialchars($addr['address']) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="p-4 border-t bg-gray-50 text-center">
            <a href="profile.php?tab=addresses" class="text-xs text-indigo-600 font-extrabold hover:text-indigo-800 transition"><?= __("add_new_address_link") ?></a>
        </div>
    </div>
</div>

<script>
    function openAddressPicker() {
        const modal = document.getElementById('addressPickerModal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeAddressPicker() {
        const modal = document.getElementById('addressPickerModal');
        if (modal) modal.classList.add('hidden');
    }

    function selectAddress(addr) {
        const nameInput = document.getElementById('checkout-fullname');
        const phoneInput = document.getElementById('checkout-phone');
        const addrInput = document.getElementById('checkout-address');
        
        if (nameInput) nameInput.value = addr.fullname;
        if (phoneInput) phoneInput.value = addr.phone;
        if (addrInput) addrInput.value = addr.address;
        
        closeAddressPicker();
    }

    // --- CHUYỂN ĐỔI GIAO DIỆN PHƯƠNG THỨC THANH TOÁN ---
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.payment-option-label').forEach(label => label.classList.remove('active'));
            const activeLabel = document.getElementById('payment-label-' + this.value);
            if (activeLabel) activeLabel.classList.add('active');
        });
    });
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>