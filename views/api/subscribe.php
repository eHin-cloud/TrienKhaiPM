<?php
/**
 * views/api/subscribe.php - Xử lý đăng ký nhận ưu đãi (Newsletter)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../core/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
if (!$email && isset($_POST['email'])) $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email không hợp lệ!']);
    exit;
}

try {
    // 1. Kiểm tra xem email đã đăng ký chưa
    $stmt = $db->prepare("SELECT id FROM newsletters WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email này đã được đăng ký nhận ưu đãi rồi!']);
        exit;
    }

    // 2. Cố gắng map user_id nếu có
    $user_id = null;
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    } else {
        // Tìm user theo email
        $stmt_user = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt_user->execute([$email]);
        $user = $stmt_user->fetch();
        if ($user) {
            $user_id = $user['id'];
        }
    }

    // 3. Xử lý dựa trên loại người dùng
    if ($user_id) {
        // Có tài khoản: Chờ admin duyệt (như cũ)
        $stmt_insert = $db->prepare("INSERT INTO newsletters (user_id, email, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt_insert->execute([$user_id, $email]);
        echo json_encode(['success' => true, 'message' => 'Đăng ký thành công! Hệ thống sẽ duyệt và gửi thông báo tặng mã qua tài khoản của bạn.']);
    } else {
        // Khách vãng lai: Tự động cấp mã giảm giá & Gửi trực tiếp vào Gmail (Không cần Admin duyệt)
        require_once __DIR__ . '/../../core/mail_helper.php';
        
        // Tạo một mã ngẫu nhiên 50K
        $code = 'GUEST' . strtoupper(substr(md5(time() . $email), 0, 5));
        $db->prepare("INSERT INTO vouchers (code, discount_amount, discount_type, usage_limit) VALUES (?, 50000, 'fixed', 1)")->execute([$code]);
        
        // Tự động nhận diện URL trang chủ
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '/';
        $baseUrl = $protocol . "://" . $host . dirname($script_name);
        if (substr($baseUrl, -1) !== '/') $baseUrl .= '/';
        $websiteLink = $baseUrl . "index.php";

        // Xây dựng giao diện Email siêu đẹp từ template hệ thống
        $body = buildEmailTemplate([
            'title' => 'Quà tặng Nhận Ưu đãi Đặc Biệt',
            'greeting' => "Xin chào <b>Khách hàng</b>,",
            'message' => "Cảm ơn bạn đã đăng ký nhận bản tin từ Điện Máy PRO. Tặng bạn mã giảm giá <b>50.000đ</b> (Áp dụng cho mọi đơn hàng). Trải nghiệm mua sắm ngay tại website: <a href='{$websiteLink}' style='color:#2563eb;font-weight:bold;text-decoration:none;'>Điện Máy PRO</a>.",
            'status_text' => 'Mã Voucher: ' . $code,
            'status_color' => '#22c55e',
            'admin_note' => 'Hãy nhập mã voucher này ở bước thanh toán để được giảm ngay tiền mặt nhé! LƯU Ý: Mã chỉ được phép sử dụng 1 lần duy nhất.',
            'type_icon' => '🎁',
            'accent_color' => '#f59e0b'
        ]);
        
        // Đánh dấu người này đã "approved" luôn, Admin chỉ việc ngồi xem báo cáo
        $stmt_insert = $db->prepare("INSERT INTO newsletters (user_id, email, status, created_at) VALUES (NULL, ?, 'approved', NOW())");
        $stmt_insert->execute([$email]);
        
        // TRICK: Phản hồi JSON ngay lập tức cho Frontend để nó hiện Cảm ơn tức thì
        ob_end_clean();
        header("Connection: close");
        ob_start();
        echo json_encode(['success' => true, 'message' => 'Tuyệt vời! Một mã giảm giá đã được gửi tự động vào hòm thư Gmail của bạn. Vui lòng kiểm tra nhé!']);
        $size = ob_get_length();
        header("Content-Length: $size");
        ob_end_flush(); 
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        // Sau khi đã phản hồi xong, PHP sẽ âm thầm chạy tiếp đoạn dưới để bắn Mail (Mất 3-5s)
        sendEmail($email, 'Khách hàng', 'Điện Máy PRO - Mã Giảm Giá Đặc Biệt', $body);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
