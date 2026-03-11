<?php
// ==========================================
// KẾT NỐI CƠ SỞ DỮ LIỆU MYSQL (phpMyAdmin)
// ==========================================
$host = 'localhost';
$dbname = 'dienmay'; // Tên database bạn vừa tạo trong phpMyAdmin
$username = 'root';  // Tài khoản mặc định của XAMPP
$password = '';      // Mật khẩu mặc định của XAMPP thường để trống

try {
    // Kết nối bằng PDO, set charset utf8mb4 để không lỗi font tiếng Việt
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Thiết lập chế độ báo lỗi
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $e) {
    // Báo lỗi nếu kết nối thất bại (sai tên DB, sai pass...)
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage() . " Vui lòng kiểm tra lại cấu hình XAMPP.");
}
?>  