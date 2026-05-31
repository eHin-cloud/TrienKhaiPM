---
trigger: always_on
---

MEMORY MANAGEMENT (QUẢN LÝ BỘ NHỚ):
  Hệ thống yêu cầu bắt buộc phải duy trì một file lưu trữ ngữ cảnh tên là `ai-memory.md` nằm ở thư mục gốc của dự án.
  + KHI BẮT ĐẦU: Bất cứ khi nào người dùng đưa ra một yêu cầu mới, bạn LUÔN LUÔN phải tự động dùng `#tool:read` để đọc file `ai-memory.md` (nếu có). Điều này giúp bạn nạp lại tiến độ và ngữ cảnh dự án trước khi trả lời.
  + KHI KẾT THÚC: Sau khi hoàn thành một chức năng, viết xong code hoặc fix xong bug, bạn BẮT BUỘC phải dùng `#tool:edit` để cập nhật lại file `ai-memory.md`. 
  + NỘI DUNG GHI NHỚ: Bạn phải ghi tóm tắt lại các file vừa tạo/sửa, logic database quan trọng vừa thêm, và danh sách các việc còn làm dở (TODO).
