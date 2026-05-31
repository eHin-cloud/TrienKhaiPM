<?php
/**
 * ============================================================
 * CORE HELPERS
 * ============================================================
 * Các hàm tiện ích dùng chung cho toàn bộ hệ thống.
 */

/**
 * XSS Protection: Escape chuỗi HTML để hiển thị an toàn.
 * Rút gọn của htmlspecialchars.
 * 
 * @param string|null $string
 * @return string
 */
if (!function_exists('e')) {
    function e(?string $string): string {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Lấy đường dẫn tuyệt đối cho tài nguyên (ảnh, CSS, JS)
 * 
 * @param string $path Đường dẫn tương đối (vd: 'uploads/abc.jpg')
 * @return string Đường dẫn tuyệt đối (vd: '/PMPBDT/public/uploads/abc.jpg')
 */
if (!function_exists('asset')) {
    function asset(?string $path): string {
        // Tự động nhận diện base path (ví dụ: '/PMPBDT/public' ở local hoặc '' ở hosting)
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        
        if (!$path || trim($path) === '') {
            return $basePath . '/assets/img/no-image.png'; // Trả về ảnh mặc định nếu rỗng
        }
        // Nếu là URL tuyệt đối (bắt đầu bằng http:// hoặc https://) thì trả về luôn
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        // Xóa dấu / ở đầu nếu có để tránh trùng lặp
        $path = ltrim($path, '/');
        // Trả về đường dẫn tuyệt đối từ root web
        return $basePath . '/' . $path;
    }
}



/**
 * Điều hướng trang (Redirect)
 * 
 * @param string $url
 */
if (!function_exists('redirect')) {
    function redirect(string $url): void {
        header("Location: $url");
        exit;
    }
}

/**
 * Lấy giá trị từ $_POST kèm giá trị mặc định
 * 
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
if (!function_exists('post')) {
    function post(string $key, $default = null) {
        return $_POST[$key] ?? $default;
    }
}

/**
 * Lấy giá trị từ $_GET kèm giá trị mặc định
 * 
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
if (!function_exists('get')) {
    function get(string $key, $default = null) {
        return $_GET[$key] ?? $default;
    }
}

/**
 * Hiển thị ảnh kèm Lazy Loading và xử lý lỗi ảnh
 * 
 * @param string|null $src
 * @param string $alt
 * @param string $class
 */
if (!function_exists('img_lazy')) {
    function img_lazy(?string $src, string $alt = '', string $class = ''): string {
        $placeholder = 'https://placehold.co/600x400?text=No+Image';
        $actualSrc = ($src && trim($src) !== '') ? $src : $placeholder;
        
        return sprintf(
            '<img src="%s" alt="%s" class="%s" loading="lazy" onerror="this.src=\'%s\'">',
            e($actualSrc),
            e($alt),
            e($class),
            $placeholder
        );
    }
}

/**
 * Kiểm tra quyền hạn của người dùng hiện tại (RBAC)
 * 
 * @param string $permission Tên quyền (vd: 'manage_users', 'edit_product')
 * @return bool
 */
if (!function_exists('can')) {
    function can(string $permission): bool {
        if (!isset($_SESSION['role'])) return false;
        
        $role = $_SESSION['role'];
        
        // Định nghĩa bảng quyền hạn
        $permissions = [
            'admin' => [
                'dashboard', 'manage_orders', 'manage_products', 'manage_users', 
                'manage_categories', 'manage_brands', 'manage_vouchers', 'manage_settings'
            ],
            'manager' => [
                'dashboard', 'manage_orders', 'manage_products'
            ],
            'staff' => [
                'manage_orders', 'manage_products'
            ],
            'customer' => []
        ];
        
        $userPerms = $permissions[$role] ?? [];
        
        // Admin mặc định có tất cả quyền
        if ($role === 'admin') return true;
        
        return in_array($permission, $userPerms);
    }
}
