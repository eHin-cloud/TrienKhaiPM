<?php
/**
 * ============================================================
 * SAVE_INSTALLMENT.PHP - LƯU YÊU CẦU ĐĂNG KÝ TRẢ GÓP CHI TIẾT
 * ============================================================
 * 
 * File API nhận yêu cầu đăng ký trả góp từ form modal trên 
 * trang chi tiết sản phẩm (product_detail.php).
 * 
 * LUỒNG HOẠT ĐỘNG:
 * 1. Khách hàng bấm nút "Mua trả chậm 0%" trên product_detail.php
 * 2. Modal trả góp hiện ra -> khách nhập Họ tên, SĐT, Kỳ hạn
 * 3. Form submit qua AJAX với tất cả các thông tin tính toán chi tiết
 * 4. File này lưu thông tin đầy đủ vào bảng installment_requests
 * 5. Trả về JSON thành công/thất bại
 * 
 * DỮ LIỆU NHẬN VÀO (POST):
 * - product_id          : ID sản phẩm muốn trả góp
 * - fullname            : Họ tên khách hàng
 * - phone               : Số điện thoại liên hệ
 * - term                : Gói đăng ký dạng chữ
 * - payment_method      : Phương thức trả góp ('finance', 'credit_card', 'bnpl')
 * - partner_name        : Tên ngân hàng/tổ chức tài chính (Vietcombank, Shinhan Finance,...)
 * - card_type           : Loại thẻ tín dụng (Visa, MasterCard, JCB)
 * - prepayment_percent  : Phần trăm trả trước (%)
 * - prepayment_amount   : Số tiền trả trước (đ)
 * - term_months         : Kỳ hạn trả góp (tháng)
 * - monthly_payment     : Số tiền thanh toán mỗi tháng (đ)
 * - total_payment       : Tổng tiền phải trả (đ)
 * - difference_amount   : Khoản chênh lệch so với giá gốc (đ)
 * - interest_rate       : Lãi suất mỗi tháng (%)
 * - is_trade_in         : Lựa chọn Thu cũ đổi mới (1: có, 0: không)
 * 
 * @see product_detail.php -> Modal trả góp (#installmentModal)
 */

// Thiết lập header trả về JSON
header('Content-Type: application/json');

// Chỉ xử lý khi phương thức là POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Thu thập dữ liệu từ form
    $product_id = (int)$_POST['product_id'];              // ID sản phẩm
    $fullname = trim($_POST['fullname']);                   // Họ tên khách hàng
    $phone = trim($_POST['phone']);                         // Số điện thoại
    $term = trim($_POST['term']);                           // Gói đăng ký (dạng chữ)
    $user_id = $_SESSION['user_id'] ?? null;                // ID user nếu đã đăng nhập

    // Các trường chi tiết mới được bổ sung
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : null;
    $partner_name = isset($_POST['partner_name']) ? trim($_POST['partner_name']) : null;
    $card_type = isset($_POST['card_type']) && $_POST['card_type'] !== '' ? trim($_POST['card_type']) : null;
    $prepayment_percent = isset($_POST['prepayment_percent']) ? (int)$_POST['prepayment_percent'] : 0;
    $prepayment_amount = isset($_POST['prepayment_amount']) ? (float)$_POST['prepayment_amount'] : 0.00;
    $term_months = isset($_POST['term_months']) ? (int)$_POST['term_months'] : 3;
    $monthly_payment = isset($_POST['monthly_payment']) ? (float)$_POST['monthly_payment'] : 0.00;
    $total_payment = isset($_POST['total_payment']) ? (float)$_POST['total_payment'] : 0.00;
    $difference_amount = isset($_POST['difference_amount']) ? (float)$_POST['difference_amount'] : 0.00;
    $interest_rate = isset($_POST['interest_rate']) ? (float)$_POST['interest_rate'] : 0.00;
    $is_trade_in = isset($_POST['is_trade_in']) ? (int)$_POST['is_trade_in'] : 0;

    // Validate dữ liệu bắt buộc
    if (empty($fullname) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đủ họ tên và SĐT']);
        exit;
    }

    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Số điện thoại phải chứa chính xác 10 chữ số']);
        exit;
    }

    try {
        // Lưu yêu cầu trả góp đầy đủ vào CSDL
        $sql = "INSERT INTO installment_requests (
            product_id, user_id, fullname, phone, installment_term,
            payment_method, partner_name, card_type, prepayment_percent, prepayment_amount,
            term_months, monthly_payment, total_payment, difference_amount, interest_rate, is_trade_in,
            status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $product_id, $user_id, $fullname, $phone, $term,
            $payment_method, $partner_name, $card_type, $prepayment_percent, $prepayment_amount,
            $term_months, $monthly_payment, $total_payment, $difference_amount, $interest_rate, $is_trade_in
        ]);
        
        // Trả về kết quả thành công
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        // Trả về lỗi nếu không thể lưu vào CSDL
        echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL: ' . $e->getMessage()]);
    }
}
?>