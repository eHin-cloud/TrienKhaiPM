<?php
/**
 * ============================================================
 * PAYMENT.PHP - TRANG THANH TOÁN QR CODE
 * ============================================================
 * 
 * Trang hiển thị mã QR để khách chuyển khoản ngân hàng.
 * Được redirect tới từ checkout.php khi khách chọn phương thức QR.
 * 
 * LUỒNG HOẠT ĐỘNG:
 * 1. Nhận order_id từ URL (?order_id=XXX)
 * 2. Kiểm tra đơn hàng tồn tại và thuộc về user đang đăng nhập
 * 3. Sinh URL ảnh QR từ VietQR API (miễn phí, uy tín)
 * 4. Hiển thị: Mã QR + Thông tin TK ngân hàng + Nút "Tôi đã CK"
 * 5. Khi khách bấm "TÔI ĐÃ CHUYỂN KHOẢN":
 *    - Cập nhật status đơn hàng: pending -> processing
 *    - Thêm ghi chú "[Khách báo đã chuyển khoản QR]"
 *    - Hiển thị UI xác nhận thành công
 * 
 * THÔNG TIN NGÂN HÀNG:
 * - Ngân hàng: MB (MBBank)
 * - QR được tạo tự động từ VietQR API: img.vietqr.io
 * 
 * @requires database.php - Kết nối CSDL
 * @requires header.php   - Giao diện header
 * @requires footer.php   - Giao diện footer
 */

// session_start() removed by Router
// database.php is auto-loaded by Router

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
// Lấy mã đơn hàng từ URL, ép kiểu int để bảo mật
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// Truy vấn đơn hàng - kiểm tra cả user_id để đảm bảo quyền sở hữu
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Nếu không tìm thấy đơn hàng -> hiển thị lỗi
if (!$order) {
    die("<div style='text-align:center; margin-top: 50px; font-family: sans-serif;'><h1>Lỗi!</h1><p>Đơn hàng không tồn tại hoặc không thuộc quyền sở hữu của bạn.</p></div>");
}

$payment_success = false;

// ==========================================
// XỬ LÝ KHI KHÁCH BẤM "TÔI ĐÃ CHUYỂN KHOẢN"
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    // Cập nhật trạng thái đơn hàng: pending -> processing (Đang xử lý / Chờ xác nhận CK)
    // Thêm ghi chú vào cột note để admin biết khách đã báo CK
    $stmtUpdate = $db->prepare("UPDATE orders SET status = 'processing', note = CONCAT(IFNULL(note, ''), ' [Khách báo đã chuyển khoản QR]') WHERE id = ? AND user_id = ?");
    $stmtUpdate->execute([$order_id, $user_id]);
    $payment_success = true; // Đánh dấu đã xác nhận
}

// ==========================================
// THÔNG TIN TÀI KHOẢN NGÂN HÀNG CỦA SHOP
// (Thay đổi thông tin này khi cần đổi TK nhận tiền)
// ==========================================
$bank_id = 'MB';                          // Mã ngân hàng (MB, VCB, TCB, ACB, BIDV...)
$account_no = '31220066649668';            // Số tài khoản ngân hàng
$account_name = 'NGUYEN ANH QUY';          // Tên chủ tài khoản (viết không dấu)
$amount = $order['total_price'];           // Số tiền cần chuyển
$addInfo = 'Thanh toan don hang ' . $order_id; // Nội dung chuyển khoản (dùng để đối soát)

// ==========================================
// TẠO URL ẢNH QR TỰ ĐỘNG TỪ VIETQR API
// ==========================================
// VietQR API: Miễn phí, uy tín, hỗ trợ tất cả ngân hàng VN
// Format: https://img.vietqr.io/image/{bank}-{account}-compact2.png?amount=X&addInfo=Y
$qr_url = "https://img.vietqr.io/image/{$bank_id}-{$account_no}-compact2.png?amount={$amount}&addInfo=" . urlencode($addInfo) . "&accountName=" . urlencode($account_name);

// Include giao diện header
require_once __DIR__ . '/../partials/header.php';
?>

<!-- ==========================================
     GIAO DIỆN TRANG THANH TOÁN QR
     ========================================== -->
<div class="container mx-auto px-4 py-10 max-w-4xl min-h-[60vh]">
    
    <!-- TRƯỜNG HỢP 1: Đã bấm xác nhận chuyển khoản -> Hiện UI thành công -->
    <?php if ($payment_success): ?>
        <div class="bg-white p-10 rounded-xl shadow-sm border border-gray-200 text-center max-w-2xl mx-auto">
            <div class="w-20 h-20 bg-green-100 text-green-500 rounded-full flex items-center justify-center text-4xl mx-auto mb-5">
                <i class="fa-solid fa-check-double"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Ghi nhận thanh toán!</h2>
            <p class="text-gray-600 mb-6">Cảm ơn bạn. Chúng tôi đã ghi nhận yêu cầu thanh toán cho đơn hàng <b>#<?= $order_id ?></b>.<br>Hệ thống sẽ kiểm tra đối soát giao dịch và nhân viên sẽ liên hệ với bạn trong ít phút để tiến hành giao hàng.</p>
            <a href="index.php" class="bg-primary text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-800 transition shadow-md inline-block">Trở về Trang chủ</a>
        </div>

    <!-- TRƯỜNG HỢP 2: Hiển thị mã QR để khách quét thanh toán -->
    <?php else: ?>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
            
            <!-- Header xanh với mã đơn hàng -->
            <div class="bg-primary text-white p-4 text-center">
                <h2 class="text-xl font-bold">THANH TOÁN ĐƠN HÀNG #<?= $order_id ?></h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 p-6 md:p-10 gap-8 items-center">
                
                <!-- === CỘT TRÁI: Ảnh mã QR === -->
                <div class="flex flex-col items-center justify-center bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <p class="font-bold text-gray-800 mb-4 text-center">Mở App Ngân hàng <br> Quét mã QR để thanh toán</p>
                    
                    <!-- Khung chứa QR với hiệu ứng scan line -->
                    <div class="bg-white p-2 rounded-xl shadow-sm border border-blue-100 relative">
                        <!-- Ảnh QR được tự động sinh từ VietQR API -->
                        <img src="<?= $qr_url ?>" alt="QR Code" class="w-64 h-64 object-contain">
                        
                        <!-- Hiệu ứng đường quét (scan line animation) -->
                        <div class="absolute top-0 left-0 w-full h-full overflow-hidden rounded-xl pointer-events-none">
                            <div class="w-full h-1 bg-green-400 shadow-[0_0_10px_2px_rgba(74,222,128,0.5)] absolute top-0 animate-[scan_2s_ease-in-out_infinite]"></div>
                        </div>
                    </div>
                    
                    <!-- Logo đối tác thanh toán -->
                    <div class="mt-4 flex gap-2 justify-center">
                        <img src="https://vnpay.vn/assets/images/logo-icon/logo-primary.svg" class="h-6 object-contain">
                        <img src="https://cdn.haitrieu.com/wp-content/uploads/2022/10/Logo-MoMo-Circle.png" class="h-6 object-contain">
                        <img src="https://cdn.haitrieu.com/wp-content/uploads/2022/10/Logo-ZaloPay-Square.png" class="h-6 object-contain">
                    </div>
                </div>

                <!-- === CỘT PHẢI: Chi tiết chuyển khoản === -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">Chi tiết chuyển khoản</h3>
                    
                    <div class="space-y-4 text-sm md:text-base">
                        <!-- Tên ngân hàng -->
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                            <span class="text-gray-500">Ngân hàng:</span>
                            <span class="font-bold text-gray-800"><?= $bank_id ?></span>
                        </div>
                        <!-- Chủ tài khoản -->
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                            <span class="text-gray-500">Chủ tài khoản:</span>
                            <span class="font-bold text-gray-800 uppercase"><?= $account_name ?></span>
                        </div>
                        <!-- Số tài khoản (có nút copy) -->
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                            <span class="text-gray-500">Số tài khoản:</span>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-primary text-lg tracking-wider" id="acc_no"><?= $account_no ?></span>
                                <button onclick="copyText('<?= $account_no ?>')" class="text-gray-400 hover:text-primary transition" title="Copy"><i class="fa-regular fa-copy"></i></button>
                            </div>
                        </div>
                        <!-- Số tiền cần chuyển (có nút copy) -->
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                            <span class="text-gray-500">Số tiền cần chuyển:</span>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-danger text-xl"><?= number_format($amount) ?> VNĐ</span>
                                <button onclick="copyText('<?= $amount ?>')" class="text-gray-400 hover:text-primary transition" title="Copy"><i class="fa-regular fa-copy"></i></button>
                            </div>
                        </div>
                        <!-- Nội dung chuyển khoản - BẮT BUỘC ghi đúng (có nút copy) -->
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                            <span class="text-gray-500">Nội dung (Bắt buộc):</span>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-gray-800 bg-yellow-100 px-2 py-0.5 rounded"><?= $addInfo ?></span>
                                <button onclick="copyText('<?= $addInfo ?>')" class="text-gray-400 hover:text-primary transition" title="Copy"><i class="fa-regular fa-copy"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Hướng dẫn và nút xác nhận -->
                    <div class="mt-8 bg-blue-50 border border-blue-100 p-4 rounded-lg">
                        <p class="text-[13px] text-blue-800 mb-3"><i class="fa-solid fa-circle-info mr-1"></i> Sau khi chuyển khoản thành công, vui lòng nhấn nút xác nhận bên dưới để chúng tôi xử lý đơn hàng nhanh nhất.</p>
                        
                        <!-- Form xác nhận đã chuyển khoản -->
                        <form method="POST">
                            <input type="hidden" name="order_id" value="<?= $order_id ?>">
                            <button type="submit" name="confirm_payment" class="w-full bg-primary text-white font-bold py-3 rounded-lg hover:bg-blue-800 transition shadow-md flex items-center justify-center gap-2">
                                <i class="fa-solid fa-check-circle"></i> TÔI ĐÃ CHUYỂN KHOẢN
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ==========================================
     CSS & JAVASCRIPT HỖ TRỢ
     ========================================== -->

<!-- CSS: Animation đường quét QR -->
<style>
    @keyframes scan {
        0% { transform: translateY(0); opacity: 0; }
        10% { opacity: 1; }
        90% { opacity: 1; }
        100% { transform: translateY(256px); opacity: 0; } /* 256px = chiều cao QR */
    }
</style>

<script>
    /**
     * Copy text vào clipboard và hiện thông báo toast
     * Sử dụng SweetAlert2 nếu có, fallback về alert()
     * @param {string} text - Nội dung cần copy
     */
    function copyText(text) {
        navigator.clipboard.writeText(text).then(() => {
            if(typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Đã copy',
                    text: text,
                    timer: 1500,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                alert("Đã copy: " + text);
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>