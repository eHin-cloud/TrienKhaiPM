# Bộ nhớ dự án (AI Context)

## 1. Thông tin chung
- Tên dự án: Hệ thống quản lý bán hàng (Laravel 10, PHP 8.2)
- Quy tắc database: Không dùng soft-delete, ID luôn là UUID.

## 2. Các module đã hoàn thành
- [x] Đăng nhập / Đăng ký (Dùng Laravel Sanctum hoặc PHP thuần tùy module).
- [x] Phân quyền cơ bản (Admin/User).
- [x] Kết nối và sửa lỗi trang Profile (Hồ sơ người dùng).
- [x] Triển khai phân trang cho danh sách đơn hàng trong Profile (5 đơn/trang).
- [x] Triển khai lên hosting InfinityFree (Cấu hình .htaccess và DB).
- [x] Nâng cấp bộ lọc sản phẩm nâng cao (Lọc khoảng giá, Danh mục, Thương hiệu) với Sidebar chuyên nghiệp.
- [x] Cập nhật ProductRepository và ProductService để hỗ trợ lọc giá.
- [x] Triển khai hồ sơ người dùng (Profile) và lịch sử đơn hàng.
- [x] Tối ưu hóa Bộ lọc Sản phẩm: Chuyển sang dạng Thanh ngang (Top Bar) hiện đại, hỗ trợ Chips và lọc giá linh hoạt.

## 3. Đang làm dở (TODO)
- [ ] Tích hợp API thanh toán.

## 4. Ghi chú lỗi (Bugs)
- [x] Fix lỗi "Kết nối không riêng tư" trên localhost (do .htaccess ép HTTPS).
- [x] Fix lỗi "404 Not Found" ở trang chủ localhost (do sai lệch tham số route).
- [x] Fix lỗi hiển thị tổng tiền đơn hàng trong Profile (sai tên cột Database).
- [x] Sửa lỗi link "Xem chi tiết" đơn hàng: Tự động lọc đúng đơn hàng khi chuyển từ Profile sang Track Order.
- Chưa xử lý triệt để lỗi N+1 query ở file ProductController.php.