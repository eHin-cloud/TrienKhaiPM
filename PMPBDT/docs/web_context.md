# Bối cảnh & Tổng quan Kiến trúc Web (Web Context)
**Dự án:** Hệ thống Website Thương mại điện tử (DIENMAYPRO)
**Thư mục gốc:** `d:\Sever\htdocs\PMHotrokh`

## 1. Công nghệ sử dụng
- **Backend:** PHP thuần (Native PHP) thao tác với CSDL qua PDO.
- **Cơ sở dữ liệu:** MySQL (Database: `dienmay`).
- **Frontend:** HTML5, CSS3, JavaScript. Sử dụng thư viện **Tailwind CSS** cho giao diện responsive và thiết kế linh hoạt, kết hợp **FontAwesome** cho UI Icons.
- **Kiến trúc:** Mô hình Client-Server cơ bản kết hợp xử lý không đồng bộ (AJAX) cho trải nghiệm người dùng mượt mà hơn (Thêm vào giỏ hàng, áp dụng Voucher, Chat AI).

## 2. Các thực thể Database chính (`database.php`)
- `users`: Quản lý tài khoản (phân quyền: admin, manager, customer).
- `products`: Thông tin sản phẩm.
- `categories`: Danh mục sản phẩm (TV, Tủ lạnh, Máy giặt,...).
- `brands`: Thương hiệu sản phẩm (Samsung, Sony, LG,...).
- `cart_items`: Quản lý giỏ hàng tạm thời cho từng user.
- `orders` & `order_details`: Quản lý đơn đặt hàng và chi tiết sản phẩm.
- `reviews`: Đánh giá và xếp hạng sao cho sản phẩm.
- `installment_requests`: Yêu cầu trả góp.
- `warranties` & `returns`: Yêu cầu bảo hành và đổi trả/hoàn tiền.
- `site_settings`: Cài đặt cấu hình trang chủ động (Banner, Text) từ trang Admin.

## 3. Các tính năng cốt lõi (Core Features)

### 👉 Phía Người Dùng (Customer / Client-side)
1. **Trang chủ & Sản phẩm (`index.php`, `product_detail.php`):** 
   - Hiển thị sản phẩm theo phân trang.
   - Lọc sản phẩm theo danh mục, thương hiệu, tìm kiếm từ khóa.
   - Giao diện dạng lưới, có các tag "Trả góp 0%", "Sale", cùng hiệu ứng hiển thị bắt mắt.
2. **Giỏ hàng & Thanh toán (`cart.php`, `checkout.php`, `payment.php`, `add_to_cart.php`):**
   - Thêm sản phẩm nhanh vào giỏ hàng bằng AJAX (`addToCartAjax`).
   - Hỗ trợ mã giảm giá (Voucher) (`ajax_voucher.php`).
   - Xử lý đặt hàng và thanh toán.
3. **Bảo hành & Đổi trả:**
   - Hỗ trợ người dùng gửi yêu cầu bảo hành hoặc báo lỗi trả hàng trên hệ thống.
4. **Trả góp:**
   - Khách có thể gửi hồ sơ yêu cầu mua trả góp (`save_installment.php`).
5. **Chat AI / Tư vấn thông minh:**
   - Tích hợp tính năng Chat AI để hỗ trợ khách hàng và tư vấn trực tiếp cho từng sản phẩm.
6. **Đánh giá Sản phẩm:**
   - Xem trung bình số tiền, biểu đồ số sao và viết review trực tiếp.

### 👉 Phía Quản Trị (Admin-side - `admin.php`)
- Phân quyền rõ ràng (Admin/Manager).
- Quản lý **Sản phẩm, Danh mục, Thương hiệu**.
- Quán lý **Đơn hàng** (duyệt, hủy, xem chi tiết).
- Quản lý **Yêu cầu Bảo hành & Đổi trả**.
- Thiết lập **Giao diện trang chủ** (Settings - cập nhật Banner động).
- Thiết lập các mã giảm giá/Vouchers.

## 4. Đặc điểm nổi bật trong mã nguồn
- Hệ thống code PHP được tổ chức file riêng biệt với mức độ module hóa tốt (vd: `header.php`, `footer.php`, `database.php` dùng chung).
- Tích hợp cơ chế tự động bảo vệ/lọc tham số tránh lỗi khi thao tác DB qua đối tượng PDO PreparedStatement (`bindValue`, `execute([])`).
- Bắt lỗi Try Catch và cấu hình hiển thị lỗi thân thiện phục vụ rà soát phát triển.
- Bình luận (Comments) và document code được viết rất chi tiết theo chuẩn mực chuyên nghiệp, mỗi hàm đều có Header mô tả thuật toán, parameter và giá trị trả về.
