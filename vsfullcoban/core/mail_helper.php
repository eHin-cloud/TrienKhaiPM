<?php
/**
 * ============================================================
 * MAIL_HELPER.PHP - HỆ THỐNG GỬI EMAIL THÔNG BÁO
 * ============================================================
 * 
 * CHỨC NĂNG:
 * Tích hợp PHPMailer để gửi email thông báo tự động đến khách hàng
 * khi Admin cập nhật trạng thái bảo hành / đổi trả.
 * 
 * CẤU HÌNH:
 * Sử dụng Gmail SMTP. Cần bật "App Password" trong tài khoản Google.
 * Hướng dẫn: https://support.google.com/accounts/answer/185833
 * 
 * @requires vendor/autoload.php (PHPMailer)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ============================================================
// CẤU HÌNH SMTP - THAY ĐỔI GIÁ TRỊ NÀY THEO TÀI KHOẢN CỦA BẠN
// ============================================================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'kaitovng@gmail.com');     // ← Thay bằng email thật
define('SMTP_PASSWORD', 'fvbg ygzh thmm vshy');          // ← Thay bằng App Password
define('SMTP_FROM_NAME', 'Điện Máy PRO');
define('SMTP_FROM_EMAIL', 'kaitovng@gmail.com');    // ← Thay bằng email thật

/**
 * Gửi email thông báo cập nhật trạng thái bảo hành
 * 
 * @param string $to_email   - Email khách hàng
 * @param string $to_name    - Tên khách hàng
 * @param int    $warranty_id - ID yêu cầu bảo hành
 * @param string $product_name - Tên sản phẩm
 * @param string $new_status - Trạng thái mới
 * @param string $admin_note - Ghi chú từ Admin (nếu có)
 * @return bool              - true nếu gửi thành công
 */
function sendWarrantyStatusEmail($to_email, $to_name, $warranty_id, $product_name, $new_status, $admin_note = '')
{
    // Chuyển đổi mã trạng thái sang tên tiếng Việt
    $status_labels = [
        'pending' => 'Chờ duyệt',
        'processing' => 'Đang xử lý',
        'completed' => 'Hoàn thành',
        'rejected' => 'Từ chối'
    ];
    $status_text = $status_labels[$new_status] ?? $new_status;

    // Xác định màu badge theo trạng thái
    $status_color = match ($new_status) {
        'pending' => '#eab308',
        'processing' => '#3b82f6',
        'completed' => '#22c55e',
        'rejected' => '#ef4444',
        default => '#6b7280'
    };

    // Tạo nội dung HTML email
    $body = buildEmailTemplate([
        'title' => 'Cập nhật yêu cầu Bảo hành',
        'greeting' => "Xin chào <b>{$to_name}</b>,",
        'message' => "Yêu cầu bảo hành <b>#{$warranty_id}</b> cho sản phẩm <b>" . htmlspecialchars($product_name) . "</b> đã được cập nhật.",
        'status_text' => $status_text,
        'status_color' => $status_color,
        'admin_note' => $admin_note,
        'type_icon' => '🔧',
        'accent_color' => '#3b82f6'
    ]);

    return sendEmail($to_email, $to_name, "Điện Máy PRO - Cập nhật bảo hành #{$warranty_id}", $body);
}

/**
 * Gửi email thông báo cập nhật trạng thái đổi trả
 * 
 * @param string $to_email   - Email khách hàng
 * @param string $to_name    - Tên khách hàng
 * @param int    $return_id  - ID yêu cầu đổi trả
 * @param int    $order_id   - ID đơn hàng
 * @param string $new_status - Trạng thái mới
 * @param string $admin_note - Ghi chú từ Admin (nếu có)
 * @return bool              - true nếu gửi thành công
 */
function sendReturnStatusEmail($to_email, $to_name, $return_id, $order_id, $new_status, $admin_note = '')
{
    $status_labels = [
        'pending' => 'Chờ xử lý',
        'approved' => 'Đã thu hồi hàng',
        'refunded' => 'Đã hoàn tiền',
        'rejected' => 'Từ chối'
    ];
    $status_text = $status_labels[$new_status] ?? $new_status;

    $status_color = match ($new_status) {
        'pending' => '#eab308',
        'approved' => '#3b82f6',
        'refunded' => '#22c55e',
        'rejected' => '#ef4444',
        default => '#6b7280'
    };

    $body = buildEmailTemplate([
        'title' => 'Cập nhật yêu cầu Đổi trả',
        'greeting' => "Xin chào <b>{$to_name}</b>,",
        'message' => "Yêu cầu trả hàng <b>#{$return_id}</b> cho đơn hàng <b>#{$order_id}</b> đã được cập nhật.",
        'status_text' => $status_text,
        'status_color' => $status_color,
        'admin_note' => $admin_note,
        'type_icon' => '🔄',
        'accent_color' => '#a855f7'
    ]);

    return sendEmail($to_email, $to_name, "Điện Máy PRO - Cập nhật đổi trả #{$return_id}", $body);
}

/**
 * Xây dựng template HTML email chuyên nghiệp
 * 
 * @param array $data - Dữ liệu template (title, greeting, message, status, note...)
 * @return string     - HTML email hoàn chỉnh
 */
function buildEmailTemplate($data)
{
    $note_section = '';
    if (!empty($data['admin_note'])) {
        $note_section = '
        <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px; padding: 16px; margin-top: 16px;">
            <p style="margin:0 0 6px 0; font-size:12px; font-weight:bold; color:#0369a1;">💬 Phản hồi từ cửa hàng:</p>
            <p style="margin:0; font-size:14px; color:#1e3a5f; line-height:1.6;">' . nl2br(htmlspecialchars($data['admin_note'])) . '</p>
        </div>';
    }

    return '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0; padding:0; background:#f5f5f5; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5; padding: 30px 0;">
            <tr><td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:16px; overflow:hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, ' . $data['accent_color'] . ', ' . $data['accent_color'] . 'dd); padding: 30px 40px; text-align:center;">
                            <h1 style="margin:0; color:#ffffff; font-size:22px; font-weight:700;">' . $data['type_icon'] . ' ' . $data['title'] . '</h1>
                            <p style="margin:8px 0 0 0; color:rgba(255,255,255,0.85); font-size:13px;">Điện Máy PRO</p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding: 30px 40px;">
                            <p style="font-size:15px; color:#333; line-height:1.7; margin:0 0 16px 0;">' . $data['greeting'] . '</p>
                            <p style="font-size:14px; color:#555; line-height:1.7; margin:0 0 20px 0;">' . $data['message'] . '</p>
                            
                            <!-- Status Badge -->
                            <div style="text-align:center; margin: 24px 0;">
                                <span style="display:inline-block; background:' . $data['status_color'] . '15; color:' . $data['status_color'] . '; border: 2px solid ' . $data['status_color'] . '40; padding: 10px 28px; border-radius: 50px; font-size: 16px; font-weight: 700; letter-spacing: 0.5px;">
                                    ● ' . $data['status_text'] . '
                                </span>
                            </div>
                            
                            ' . $note_section . '
                            
                            <p style="font-size:13px; color:#888; margin:24px 0 0 0; line-height:1.6;">
                                Bạn có thể theo dõi chi tiết tại mục <b>\"Theo dõi đơn hàng\"</b> trên website của chúng tôi.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background:#f9fafb; padding: 20px 40px; border-top: 1px solid #e5e7eb; text-align:center;">
                            <p style="margin:0; font-size:12px; color:#9ca3af;">© Điện Máy PRO • Hotline: 1800.1061</p>
                            <p style="margin:4px 0 0 0; font-size:11px; color:#c0c0c0;">Email này được gửi tự động, vui lòng không trả lời.</p>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>';
}

/**
 * Hàm gửi email cốt lõi sử dụng PHPMailer
 * 
 * @param string $to_email - Địa chỉ email nhận
 * @param string $to_name  - Tên người nhận
 * @param string $subject  - Tiêu đề email
 * @param string $body     - Nội dung HTML
 * @return bool            - true nếu gửi thành công, false nếu lỗi
 */
function sendEmail($to_email, $to_name, $subject, $body)
{
    // Bỏ qua nếu email rỗng hoặc chưa cấu hình
    if (empty($to_email) || SMTP_USERNAME === 'your-email@gmail.com') {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Cấu hình máy chủ SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Thiết lập thông tin người gửi / nhận
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        // Nội dung email
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log lỗi (không hiển thị cho user)
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>