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
    die("<div style='text-align:center; margin-top: 50px; font-family: sans-serif;'><h1>" . __("error") . "!</h1><p>" . __("order_not_found_or_not_yours") . "</p></div>");
}

$payment_success = false;

// ==========================================
// XỬ LÝ TRẠNG THÁI THANH TOÁN
// ==========================================
// Nếu đơn hàng đã được Webhook (hoặc admin) đổi trạng thái khác pending -> Hiện thành công
if ($order['status'] !== 'pending' && $order['status'] !== 'cancelled') {
    $payment_success = true;
}

// Xử lý khi khách bấm nút "TÔI ĐÃ CHUYỂN KHOẢN" thủ công (Trường hợp Webhook chậm/lỗi)
if (!$payment_success && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    // User phàn nàn không muốn ghi '[Khách báo đã chuyển khoản QR]', ta đổi thành tự động duyệt luôn (processing)
    $stmtUpdate = $db->prepare("UPDATE orders SET status = 'processing', note = CONCAT(IFNULL(note, ''), ' [Hệ thống ghi nhận khách bấm xác nhận thủ công]') WHERE id = ? AND user_id = ?");
    $stmtUpdate->execute([$order_id, $user_id]);
    $payment_success = true;
}

// ==========================================
// THÔNG TIN TÀI KHOẢN NGÂN HÀNG CỦA SHOP
// (Thay đổi thông tin này khi cần đổi TK nhận tiền)
// ==========================================
$bank_id = 'MB';                          // Mã ngân hàng (MB, VCB, TCB, ACB, BIDV...)
$account_no = '31220066649668';            // Số tài khoản ngân hàng
$account_name = 'NGUYEN ANH QUY';          // Tên chủ tài khoản (viết không dấu)
$amount = $order['total_price'];           // Số tiền cần chuyển
$addInfo = 'DMPRO' . $order_id; // Mã chuyển khoản ngắn gọn (Prefix DMPRO + ID) để Webhook dễ bóc tách

// ==========================================
// TẠO URL ẢNH QR TỰ ĐỘNG TỪ VIETQR API (DỰ PHÒNG)
// ==========================================
// VietQR API: Miễn phí, uy tín, hỗ trợ tất cả ngân hàng VN
$qr_url = "https://img.vietqr.io/image/{$bank_id}-{$account_no}-compact2.png?amount={$amount}&addInfo=" . urlencode($addInfo) . "&accountName=" . urlencode($account_name);

// ==========================================
// TÍCH HỢP PAYOS TỰ ĐỘNG CHUYỂN HƯỚNG THANH TOÁN
// ==========================================
require_once __DIR__ . '/../../core/payos_config.php';

$payos_error = '';
// Nếu đã cấu hình PayOS và đơn hàng đang pending, không phải do khách hủy, tiến hành tạo link thanh toán
if (PAYOS_CLIENT_ID !== 'YOUR_CLIENT_ID_HERE' && !$payment_success && !isset($_GET['cancel']) && !isset($_GET['payos_success'])) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'];
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $browserDir = rtrim(dirname($requestUri), '/\\');
    $browserDir = str_replace('\\', '/', $browserDir);
    // Loại bỏ hoàn toàn thư mục /public dư thừa nếu có để sinh ra URL sạch đẹp cho người dùng
    $browserDir = preg_replace('/\/public$/i', '', $browserDir);
    $baseUrl = $protocol . "://" . $host . ($browserDir === '/' ? '' : $browserDir);
    
    // Trả về trang này nếu thành công hoặc hủy
    $returnUrl = $baseUrl . "/payment.php?order_id=" . $order_id . "&payos_success=1";
    $cancelUrl = $baseUrl . "/payment.php?order_id=" . $order_id . "&cancel=1";
    
    $data = [
        "orderCode" => intval($order_id),
        "amount" => intval($amount),
        "description" => substr($addInfo, 0, 25),
        "returnUrl" => $returnUrl,
        "cancelUrl" => $cancelUrl
    ];
    
    // Tạo chữ ký bảo mật (Signature)
    ksort($data);
    $signData = [];
    foreach ($data as $key => $value) {
        if ($value === '' || $value === null || is_array($value)) continue;
        $signData[] = $key . '=' . $value;
    }
    $signString = implode('&', $signData);
    $signature = hash_hmac('sha256', $signString, PAYOS_CHECKSUM_KEY);
    $data['signature'] = $signature;
    
    // Gọi API PayOS (Sử dụng fallback nếu thiếu cURL)
    $payos_url = 'https://api-merchant.payos.vn/v2/payment-requests';
    $payos_payload = json_encode($data);
    $payos_headers = [
        'Content-Type: application/json',
        'x-client-id: ' . PAYOS_CLIENT_ID,
        'x-api-key: ' . PAYOS_API_KEY
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($payos_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payos_payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $payos_headers);
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $payos_headers),
                'content' => $payos_payload,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        $context = stream_context_create($options);
        $response = file_get_contents($payos_url, false, $context);
    }
    $resData = json_decode($response, true);
    
    // Nếu tạo thành công, chuyển hướng người dùng sang my.payos.vn
    if ($resData && $resData['code'] == '00' && isset($resData['data']['checkoutUrl'])) {
        header("Location: " . $resData['data']['checkoutUrl']);
        exit;
    } else {
        $payos_error = $resData['desc'] ?? __("unknown_system_error");
    }
}

// Xử lý khi người dùng thanh toán xong từ PayOS trả về (Redirect ReturnUrl)
if (isset($_GET['payos_success'])) {
    $payment_success = true;
    
    // Đôi khi Webhook của PayOS có thể tới trễ 1-2s, nếu khách vừa được PayOS redirect về mà đơn vẫn đang pending
    // ta nên chủ động cho đơn thành processing để khách thấy trang Thành Công ngay lập tức (trải nghiệm mượt)
    // Khi webhook tới sau, nó cũng sẽ update lại note/status một cách chuẩn xác hơn.
    $stmtCheck = $db->prepare("SELECT status FROM orders WHERE id = ?");
    $stmtCheck->execute([$order_id]);
    $currentStatus = $stmtCheck->fetchColumn();
    
    if ($currentStatus === 'pending') {
        $stmtUpdate = $db->prepare("UPDATE orders SET status = 'processing', note = CONCAT(IFNULL(note, ''), ' [PayOS Redirect] Đã xác nhận trên cổng thanh toán') WHERE id = ?");
        $stmtUpdate->execute([$order_id]);
    }
}

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
            <h2 class="text-2xl font-bold text-gray-800 mb-2"><?= __("payment_received_title") ?></h2>
            <p class="text-gray-600 mb-6"><?= sprintf(__("payment_received_msg"), $order_id) ?></p>
            <a href="index.php" class="bg-primary text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-800 transition shadow-md inline-block"><?= __("back_to_home") ?></a>
        </div>

    <!-- TRƯỜNG HỢP 2: Hiển thị mã QR để khách quét thanh toán -->
    <?php else: ?>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
            
            <!-- Header xanh với mã đơn hàng -->
            <div class="bg-primary text-white p-4 text-center">
                <h2 class="text-xl font-bold"><?= __("payment_order_title") ?> #<?= $order_id ?></h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 p-6 md:p-10 gap-8 items-center">
                
                <!-- === CỘT TRÁI: Ảnh mã QR === -->
                <div class="flex flex-col items-center justify-center bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <p class="font-bold text-gray-800 mb-4 text-center"><?= __("open_bank_app_qr") ?></p>
                    
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
                    <h3 class="text-lg font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200"><?= __("transfer_details") ?></h3>
                    
                    <div class="space-y-4 text-sm md:text-base">
                        <!-- Tên ngân hàng -->
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                            <span class="text-gray-500"><?= __("bank_name") ?>:</span>
                            <span class="font-bold text-gray-800"><?= $bank_id ?></span>
                        </div>
                        <!-- Chủ tài khoản -->
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                            <span class="text-gray-500"><?= __("account_holder") ?>:</span>
                            <span class="font-bold text-gray-800 uppercase"><?= $account_name ?></span>
                        </div>
                        <!-- Số tài khoản (có nút copy) -->
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                            <span class="text-gray-500"><?= __("account_number") ?>:</span>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-primary text-lg tracking-wider" id="acc_no"><?= $account_no ?></span>
                                <button onclick="copyText('<?= $account_no ?>')" class="text-gray-400 hover:text-primary transition" title="Copy"><i class="fa-regular fa-copy"></i></button>
                            </div>
                        </div>
                        <!-- Số tiền cần chuyển (có nút copy) -->
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                            <span class="text-gray-500"><?= __("transfer_amount") ?>:</span>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-danger text-xl"><?= number_format($amount) ?> VNĐ</span>
                                <button onclick="copyText('<?= $amount ?>')" class="text-gray-400 hover:text-primary transition" title="Copy"><i class="fa-regular fa-copy"></i></button>
                            </div>
                        </div>
                        <!-- Nội dung chuyển khoản - BẮT BUỘC ghi đúng (có nút copy) -->
                        <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                            <span class="text-gray-500"><?= __("transfer_content") ?>:</span>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-gray-800 bg-yellow-100 px-2 py-0.5 rounded"><?= $addInfo ?></span>
                                <button onclick="copyText('<?= $addInfo ?>')" class="text-gray-400 hover:text-primary transition" title="Copy"><i class="fa-regular fa-copy"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Hướng dẫn chờ tự động & Nút dự phòng -->
                    <div class="mt-8 bg-blue-50 border border-blue-100 p-4 rounded-lg">
                        <div class="flex items-center gap-3 mb-4">
                            <i class="fa-solid fa-spinner fa-spin text-primary text-2xl"></i>
                            <div>
                                <p class="text-sm font-bold text-blue-800"><?= __("waiting_payment") ?></p>
                                <p class="text-[13px] text-gray-600 mt-1"><?= __("waiting_payment_desc") ?></p>
                            </div>
                        </div>
                        
                        <div class="border-t border-blue-200 pt-3">
                            <p class="text-xs text-gray-500 mb-2 italic"><?= __("manual_confirm_hint") ?></p>
                            <form method="POST">
                                <?= csrf_input_field() ?>
                                <button type="submit" name="confirm_payment" class="w-full bg-white border border-primary text-primary font-bold py-2.5 rounded-lg hover:bg-blue-50 transition shadow-sm text-sm">
                                    <?= __("manual_confirm_btn") ?>
                                </button>
                            </form>
                        </div>
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
                    title: '<?= __("copied") ?>',
                    text: text,
                    timer: 1500,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                alert("<?= __("copied") ?>: " + text);
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>

<!-- Script tự động kiểm tra trạng thái thanh toán từ Webhook -->
<?php if (!$payment_success): ?>
<script>
    let pollingInterval = setInterval(() => {
        fetch('check_order_status.php?id=<?= $order_id ?>')
            .then(res => res.json())
            .then(data => {
                // Nếu trạng thái khác pending (đã thanh toán thành công qua Webhook)
                if (data.status && data.status !== 'pending' && data.status !== 'not_found' && data.status !== 'error') {
                    clearInterval(pollingInterval); // Dừng polling
                    
                    // Hiển thị thông báo SweetAlert nhỏ trước khi reload
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '<?= __("payment_received_swal") ?>',
                            text: '<?= __("payment_received_swal_desc") ?>',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload(); // Reload để hiển thị UI thành công
                        });
                    } else {
                        window.location.reload();
                    }
                }
            })
            .catch(err => console.error("Lỗi kiểm tra trạng thái:", err));
    }, 3000); // Polling mỗi 3 giây
</script>
<?php endif; ?>