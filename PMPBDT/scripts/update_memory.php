<?php
$c=file_get_contents('ai-memory.md');
$c=str_replace('- [ ] Hoàn thiện đề xuất sản phẩm trang chủ: tách rõ Alternative/Cross-sell/Recently Viewed và dùng Service/Repository thay vì `ORDER BY RAND()` trong `index.php`.', '- [x] **Nâng cấp luồng Gợi ý (Recommendation) & Sản phẩm vừa xem:**
  - **Đồng bộ Server-side (Database):** Đã tạo bảng `user_recently_viewed` trong MySQL. Cập nhật `ProductRepository` và `ProductService` để lưu ID sản phẩm mỗi khi khách hàng đăng nhập và vào trang `product_detail.php`.
  - **Tách Component Product Card:** Chuyển đổi mã HTML Thẻ sản phẩm trong `index.php` thành partial dùng chung `views/partials/product_card.php`.
  - **Hoàn thiện Đề xuất trang chủ:** Ở trang `index.php` (khi đã login), hệ thống tự động dựa vào "Sản phẩm vừa xem gần nhất" để nội suy và hiển thị thành 3 block thông minh tách biệt: **Sản Phẩm Vừa Xem**, **Có Thể Bạn Sẽ Thích (Alternative)**, và **Gợi Ý Mua Kèm (Cross-sell)**.', $c);
file_put_contents('ai-memory.md',$c);
echo 'DONE';
