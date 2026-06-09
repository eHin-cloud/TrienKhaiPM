<?php
/**
 * ============================================================
 * SAVE_INSTALLMENT.PHP - LƯU YÊU CẦU ĐĂNG KÝ TRẢ GÓP
 * ============================================================
 * 
 * File API nhận yêu cầu đăng ký trả góp từ form modal trên 
 * trang chi tiết sản phẩm (product_detail.php).
 * 
 * LUỒNG HOẠT ĐỘNG:
 * 1. Khách hàng bấm nút "Mua trả chậm 0%" trên product_detail.php
 * 2. Modal trả góp hiện ra -> khách nhập Họ tên, SĐT, Kỳ hạn
 * 3. Form submit qua AJAX (hàm submitInstallment() trong footer.php)
 * 4. File này lưu thông tin vào bảng installment_requests
 * 5. Trả về JSON thành công/thất bại
 * 
 * DỮ LIỆU NHẬN VÀO (POST):
 * - product_id : ID sản phẩm muốn trả góp
 * - fullname   : Họ tên khách hàng
 * - phone      : Số điện thoại liên hệ
 * - term       : Kỳ hạn trả góp (VD: "Gói 3 tháng (Lãi suất 0%)")
 * 
 * @see product_detail.php -> Modal trả góp (#installmentModal)
 * @see footer.php -> Hàm submitInstallment()
 */

// session_start() removed by Router
// database.php is auto-loaded by Router

// Thiết lập header trả về JSON
header('Content-Type: application/json');

// Chỉ xử lý khi phương thức là POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Thu thập dữ liệu từ form
    $product_id = (int)$_POST['product_id'];              // ID sản phẩm (ép kiểu int bảo mật)
    $fullname = trim($_POST['fullname']);                   // Họ tên khách hàng
    $phone = trim($_POST['phone']);                         // Số điện thoại
    $term = trim($_POST['term']);                           // Kỳ hạn trả góp đã chọn
    $user_id = $_SESSION['user_id'] ?? null;                // ID user nếu đã đăng nhập (có thể null)

    // Validate dữ liệu bắt buộc
    if (empty($fullname) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đủ họ tên và SĐT']);
        exit;
    }

    try {
        // Lưu yêu cầu trả góp vào CSDL
        $stmt = $db->prepare("INSERT INTO installment_requests (product_id, user_id, fullname, phone, installment_term) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$product_id, $user_id, $fullname, $phone, $term]);
        
        // Trả về kết quả thành công
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        // Trả về lỗi nếu không thể lưu vào CSDL
        echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL']);
    }
}
?>