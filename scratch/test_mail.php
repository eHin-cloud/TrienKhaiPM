<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Nạp biến môi trường từ .env
if (class_exists('\App\Support\Env')) {
    \App\Support\Env::load(__DIR__ . '/../.env');
}

require_once __DIR__ . '/../core/mail_helper.php';

echo "Bắt đầu chạy thử nghiệm gửi email qua SMTP...\n";
echo "SMTP Host: " . SMTP_HOST . "\n";
echo "SMTP Port: " . SMTP_PORT . "\n";
echo "SMTP Username: " . SMTP_USERNAME . "\n";
echo "SMTP From Email: " . SMTP_FROM_EMAIL . "\n";

// Hãy thử gửi mail tới chính email người gửi hoặc email kiểm tra
$test_email = SMTP_USERNAME;
if (empty($test_email)) {
    echo "LỖI: Chưa cấu hình SMTP_USERNAME trong file .env!\n";
    exit;
}

echo "Đang cố gắng gửi thử email đến: $test_email...\n";

// Sử dụng trực tiếp PHPMailer để hiển thị lỗi chi tiết
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Kích hoạt debug chi tiết
    $mail->SMTPDebug = 3; // Lấy thông tin debug chi tiết nhất
    $mail->Debugoutput = function($str, $level) {
        echo "[SMTP DEBUG $level] $str\n";
    };

    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($test_email, "Test Recipient");

    $mail->isHTML(true);
    $mail->Subject = "Điện Máy PRO - Thử nghiệm kết nối SMTP";
    $mail->Body = "Chào bạn, đây là email kiểm tra tính năng kết nối SMTP của DienMayPro. Nếu bạn nhận được email này, tính năng gửi OTP đã hoạt động hoàn toàn chính xác!";

    $mail->send();
    echo "\n=== KẾT QUẢ: GỬI MAIL THÀNH CÔNG RỰC RỠ! ===\n";
} catch (Exception $e) {
    echo "\n=== KẾT QUẢ: GỬI MAIL THẤT BẠI! ===\n";
    echo "Chi tiết lỗi: " . $e->getMessage() . "\n";
    echo "Thông tin lỗi từ PHPMailer: " . $mail->ErrorInfo . "\n";
}
