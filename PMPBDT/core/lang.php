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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    if (empty($name)) return '';
    if (getCurrentLang() === 'vi') return $name;

    $map = $GLOBALS['_LANG']['categories_map'] ?? [];
    if (isset($map[$name])) {
        return $map[$name];
    }
    // So khớp không phân biệt hoa thường để tránh lỗi viết hoa chữ cái đầu/cuối
    $lowerName = mb_strtolower(trim($name), 'UTF-8');
    foreach ($map as $key => $val) {
        if (mb_strtolower(trim($key), 'UTF-8') === $lowerName) {
            return $val;
        }
    }
    
    // Tự động dịch động sang tiếng Anh nếu danh mục mới thêm chưa có sẵn trong bản dịch tĩnh
    return translate_text($name, 'cat_name_' . md5($name));
}

/**
 * Lấy ngôn ngữ hiện tại
 * @return string 'vi' hoặc 'en'
 */
function getCurrentLang() {
    return $_SESSION['lang'] ?? 'vi';
}

/**
 * DỊCH TỪ TIẾNG VIỆT SANG TIẾNG ANH & CACHE FILE TĨNH (Không phụ thuộc vào ngôn ngữ hiện tại của session)
 */
function force_translate_to_english($content, $cacheKey, $isHtml = false) {
    if (empty($content)) return $content;
    
    $cacheDir = __DIR__ . '/../storage/cache/translation/';
    if (!file_exists($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    
    $ext = $isHtml ? '.html' : '.txt';
    $cacheFile = $cacheDir . md5($cacheKey . '_' . md5($content)) . $ext;
    
    if (file_exists($cacheFile)) {
        return file_get_contents($cacheFile);
    }
    
    if (!$isHtml) {
        // Dịch plain text
        try {
            $trimmed = trim($content);
            if (empty($trimmed) || is_numeric($trimmed)) {
                return $content;
            }
            $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=vi&tl=en&dt=t&q=" . urlencode($trimmed);
            $options = [
                "http" => [
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.36\r\n"
                ]
            ];
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            if ($response !== false) {
                $resData = json_decode($response, true);
                if (isset($resData[0])) {
                    $translated = "";
                    foreach ($resData[0] as $segment) {
                        $translated .= $segment[0] ?? "";
                    }
                    if (!empty($translated)) {
                        @file_put_contents($cacheFile, $translated);
                        return $translated;
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback
        }
    } else {
        // Dịch HTML (Bảo toàn thẻ HTML)
        try {
            $parts = preg_split('/(<[^>]+>)/U', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
            $translatedHtml = "";
            
            foreach ($parts as $part) {
                if (empty($part)) continue;
                
                // Nếu là thẻ HTML thì giữ nguyên
                if (strpos($part, '<') === 0 && strpos($part, '>') === strlen($part) - 1) {
                    $translatedHtml .= $part;
                } else {
                    $trimmed = trim($part);
                    if (empty($trimmed) || is_numeric($trimmed) || (strlen($trimmed) <= 1 && !preg_match('/\p{L}/u', $trimmed))) {
                        $translatedHtml .= $part;
                    } else {
                        $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=vi&tl=en&dt=t&q=" . urlencode($trimmed);
                        $options = [
                            "http" => [
                                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.36\r\n"
                            ]
                        ];
                        $context = stream_context_create($options);
                        $response = @file_get_contents($url, false, $context);
                        if ($response !== false) {
                            $resData = json_decode($response, true);
                            if (isset($resData[0])) {
                                $segmentTrans = "";
                                foreach ($resData[0] as $segment) {
                                    $segmentTrans .= $segment[0] ?? "";
                                }
                                if (!empty($segmentTrans)) {
                                    $left_space = strlen($part) - strlen(ltrim($part));
                                    $right_space = strlen($part) - strlen(rtrim($part));
                                    $translatedHtml .= str_repeat(" ", $left_space) . $segmentTrans . str_repeat(" ", $right_space);
                                    continue;
                                }
                            }
                        }
                        $translatedHtml .= $part;
                    }
                }
            }
            
            if (!empty($translatedHtml)) {
                @file_put_contents($cacheFile, $translatedHtml);
                return $translatedHtml;
            }
        } catch (\Exception $e) {
            // Fallback
        }
    }
    
    return $content;
}

/**
 * TỰ ĐỘNG DỊCH NỘI DUNG HTML (MÔ TẢ/THÔNG SỐ) SANG TIẾNG ANH & CACHE FILE TĨNH
 */
function translate_html_content($html, $cacheKey) {
    if (empty($html)) return $html;
    if (getCurrentLang() === 'vi') return $html;
    return force_translate_to_english($html, $cacheKey, true);
}

/**
 * TỰ ĐỘNG DỊCH TEXT SANG TIẾNG ANH & CACHE FILE TĨNH
 */
function translate_text($text, $cacheKey) {
    if (empty($text)) return $text;
    if (getCurrentLang() === 'vi') return $text;
    
    // Mảng dịch tĩnh ngoại tuyến cho các sản phẩm mẫu và từ khóa phổ biến để tối ưu hiệu năng
    // và tránh lỗi 429 Too Many Requests của Google Translate API khi load danh sách sản phẩm lớn
    $product_map = [
        'Smart TV Samsung 55 inch QLED' => 'Samsung 55-inch QLED Smart TV',
        'Tủ lạnh LG Inverter 260L' => 'LG Inverter Refrigerator 260L',
        'Máy giặt Samsung Inverter 9kg' => 'Samsung Inverter Washing Machine 9kg',
        'Laptop Apple MacBook Air M2 13 inch' => 'Apple MacBook Air M2 13-inch Laptop',
        'Điện thoại Apple iPhone 14 128GB' => 'Apple iPhone 14 128GB Phone',
        'Laptop Asus Vivobook 14' => 'Asus Vivobook 14 Laptop',
        'Điện thoại Xiaomi Redmi Note 12' => 'Xiaomi Redmi Note 12 Phone',
        'Smart TV Sony 43 inch 4K' => 'Sony 43-inch 4K Smart TV',
        'Tủ lạnh Panasonic 180L' => 'Panasonic Refrigerator 180L',
        'Máy giặt Panasonic 8kg' => 'Panasonic Washing Machine 8kg',
        'Điện thoại Samsung Galaxy A54' => 'Samsung Galaxy A54 Phone',
        'Laptop Dell Inspiron 15' => 'Dell Inspiron 15 Laptop',
        'Tivi LG OLED evo G4 65 inch 4K Smart TV' => 'LG OLED evo G4 65-inch 4K Smart TV',
        'Xiaomi 14 Pro 5G - Ống Kính Leica Thế Hệ Mới' => 'Xiaomi 14 Pro 5G - Leica Next-Gen Lens',
        'Samsung Galaxy Watch Ultra' => 'Samsung Galaxy Watch Ultra',
        'Apple Watch Hermès Series 9' => 'Apple Watch Hermès Series 9',
        'Máy Lọc Không Khí Khử Khuẩn Xiaomi Smart Air Purifier 4 Ultra' => 'Xiaomi Smart Air Purifier 4 Ultra Disinfecting Air Purifier',
        'Đồng hồ thông minh' => 'Smartwatch',
        'Tivi LG' => 'LG TV',
        'Tivi LG OLED' => 'LG OLED TV',
        'Máy hút bụi' => 'Vacuum Cleaner',
        'Nồi chiên không dầu' => 'Air Fryer',
        'Nồi cơm điện' => 'Electric Rice Cooker',
        'Quạt đứng' => 'Stand Fan',
        'Cửa trước' => 'Front Load',
        'Cửa trên' => 'Top Load',
        'Inverter' => 'Inverter',
        'Chính hãng' => 'Genuine',
        'Giá rẻ' => 'Cheap',
    ];
    
    $trimmedText = trim($text);
    if (isset($product_map[$trimmedText])) {
        return $product_map[$trimmedText];
    }
    
    // So khớp không phân biệt hoa thường và khoảng trắng để tăng tính linh hoạt
    $lowerText = mb_strtolower($trimmedText, 'UTF-8');
    foreach ($product_map as $key => $val) {
        if (mb_strtolower(trim($key), 'UTF-8') === $lowerText) {
            return $val;
        }
    }

    return force_translate_to_english($text, $cacheKey, false);
}

