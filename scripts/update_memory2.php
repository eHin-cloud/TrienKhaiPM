<?php
$c=file_get_contents('ai-memory.md');
$c=str_replace('- Chưa xử lý triệt để lỗi N+1 query ở file ProductController.php.', '- [x] **Tối ưu hóa hiệu năng (Performance):** Đã rà soát và xử lý vấn đề truy vấn chưa được tối ưu bằng cách thêm `LEFT JOIN brands b` và `LEFT JOIN categories c` vào các hàm `getRelatedProducts` và `getSameBrandProducts` trong `ProductRepository.php`. Qua đó triệt tiêu triệt để nguy cơ xuất hiện lỗi N+1 Query trong các vòng lặp ngoài View khi cần hiển thị tên thương hiệu hoặc tên danh mục.', $c);
file_put_contents('ai-memory.md',$c);
echo 'DONE';
