<?php
/**
 * ============================================================
 * LANG.PHP - HỆ THỐNG ĐA NGÔN NGỮ (i18n)
 * ============================================================
 * 
 * Hỗ trợ chuyển đổi giữa Tiếng Việt (vi) và English (en).
 * Ngôn ngữ được lưu trong $_SESSION['lang'] và cookie 'lang'.
 * 
 * CÁCH DÙNG:
 *   echo __('home');        // In ra "Trang chủ" hoặc "Home"
 *   echo __('cart');        // In ra "Giỏ hàng" hoặc "Cart"
 */

// Xử lý chuyển ngôn ngữ khi user click nút
if (isset($_GET['lang']) && in_array($_GET['lang'], ['vi', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('lang', $_GET['lang'], time() + 86400 * 365, '/'); // Cookie 1 năm
}

// Xác định ngôn ngữ hiện tại: Session > Cookie > Mặc định (vi)
$currentLang = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? 'vi';
$_SESSION['lang'] = $currentLang;

// Nạp file ngôn ngữ tương ứng
$langFile = __DIR__ . '/lang/' . $currentLang . '.php';
if (file_exists($langFile)) {
    $GLOBALS['_LANG'] = require $langFile;
} else {
    // Fallback về tiếng Việt
    $GLOBALS['_LANG'] = require __DIR__ . '/lang/vi.php';
    $currentLang = 'vi';
}

/**
 * Hàm dịch - Lấy chuỗi dịch theo key
 * @param string $key Key cần dịch (VD: 'home', 'cart', 'login')
 * @param string|null $default Giá trị mặc định nếu key không tồn tại
 * @return string Chuỗi đã dịch
 */
function __($key, $default = null) {
    return $GLOBALS['_LANG'][$key] ?? $default ?? $key;
}

/**
 * Hàm dịch tên danh mục từ database.
 * Tra cứu trong mảng 'categories_map' của file ngôn ngữ.
 * Nếu không có bản dịch, trả về tên gốc.
 * @param string $name Tên danh mục gốc (tiếng Việt từ DB)
 * @return string Tên đã dịch
 */
function __cat($name) {
    $map = $GLOBALS['_LANG']['categories_map'] ?? [];
    return $map[$name] ?? $name;
}

/**
 * Lấy ngôn ngữ hiện tại
 * @return string 'vi' hoặc 'en'
 */
function getCurrentLang() {
    return $_SESSION['lang'] ?? 'vi';
}
