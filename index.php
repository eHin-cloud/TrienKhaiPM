<?php
/**
 * ============================================================
 * DienMayPro - Fallback Entry Point
 * ============================================================
 * File này đóng vai trò làm cầu nối (proxy) trong trường hợp 
 * Hosting/Web Server (1Panel) cấu hình thư mục chạy chính (Document Root)
 * trỏ vào thư mục gốc của dự án thay vì thư mục /public.
 * 
 * Nó giúp chuyển tiếp mọi yêu cầu một cách an toàn vào public/index.php.
 */

// Chuyển tiếp luồng xử lý chính vào Front Controller trong thư mục public
require_once __DIR__ . '/public/index.php';
