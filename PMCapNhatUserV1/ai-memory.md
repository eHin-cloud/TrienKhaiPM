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
- [x] Sửa nút "Xem thêm" gợi ý sản phẩm: AJAX load thêm không reload trang, ẩn nút khi hết SP. URL API dùng PHP echo rtrim(dirname(dirname($_SERVER['PHP_SELF']))) để luôn đúng path.
- [x] Fix lặp danh mục khi phân trang AJAX: tách div#ajax-inner-content riêng, PHP ob_start() bên trong div đó, JS chỉ inject vào innerContent.innerHTML thay vì toàn section.
- [x] Fix duplicate event listener phân trang: dùng flag _paginationListenerActive + delegate trên document thay vì gắn lại listener mỗi lần load.
- [x] Fix $categories undefined khi ajax=1 (header.php bị skip): thêm fallback getAllCategories($db) nếu chưa có.
- [x] Sửa phân trang trang sản phẩm nổi bật: AJAX pagination không lặp header/footer (dời $is_ajax check trước include header.php).
- [x] Tăng giới hạn sản phẩm/trang từ 10 lên 12.
- [x] Fix lỗi lặp thanh bộ lọc (DANH MỤC, Xem thêm bộ lọc) và lỗi undefined $categories khi phân trang AJAX: bọc toàn bộ banner, bộ lọc, và thẻ container `<section>` bằng `if (!$is_ajax)` trong `index.php`.
- [x] Fix lỗi nút "Xem thêm sản phẩm" (Gợi ý) không hoạt động: Đăng ký route `get_more_suggested.php` vào `$routesMap` trong `public/index.php` và sửa đổi biến `apiUrl` dùng URL tương đối. Đồng thời, cấu hình nút chỉ cho phép nhấn tải thêm 1 lần duy nhất rồi ẩn đi.
- [x] Nâng cấp Carousel Banner trang chủ: 
  - Hiển thị Banner tĩnh gốc ở Slide 1.
  - Tự động query chọn ra 4 sản phẩm sale thay đổi ngẫu nhiên theo mỗi ngày để hiển thị ở các Slide tiếp theo (Tổng cộng 5 slides).
  - Implement kỹ thuật Infinite Loop cho JS: thay vì giật lùi về slide đầu tiên khi chạy hết vòng, carousel sẽ tiếp tục lướt tới vô tận một cách mượt mà.
- [x] Fix lỗi redirect khi Đăng nhập/Đăng ký:
  - Sửa lỗi hệ thống tự động xóa mất mã ID sản phẩm trên thanh URL khi người dùng thực hiện Đăng nhập hoặc Đăng ký tại trang Chi tiết sản phẩm.
- [x] Nâng cấp Footer giao diện hoành tráng:
  - Thiết kế lại Footer với Tailwind CSS Grid chia 4 cột hiển thị: Thông tin thương hiệu, Liên hệ (Hotline/Email), Hỗ trợ khách hàng, Đăng ký nhận tin & Thanh toán.
  - Áp dụng tone màu tối (slate-900) sang trọng kết hợp gradient background và hiệu ứng hover động tinh tế.

## 3. Đang làm dở (TODO)
- [ ] Tích hợp API thanh toán.

## 4. Ghi chú lỗi (Bugs)
- [x] Fix lỗi "Kết nối không riêng tư" trên localhost (do .htaccess ép HTTPS).
- [x] Fix lỗi "404 Not Found" ở trang chủ localhost (do sai lệch tham số route).
- [x] Fix lỗi hiển thị tổng tiền đơn hàng trong Profile (sai tên cột Database).
- [x] Sửa lỗi link "Xem chi tiết" đơn hàng: Tự động lọc đúng đơn hàng khi chuyển từ Profile sang Track Order.
- Chưa xử lý triệt để lỗi N+1 query ở file ProductController.php.