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

/**
 * TỰ ĐỘNG DỊCH NỘI DUNG HTML (MÔ TẢ/THÔNG SỐ) SANG TIẾNG ANH & CACHE FILE TĨNH
 */
function translate_html_content($html, $cacheKey) {
    if (empty($html)) return $html;
    if (getCurrentLang() === 'vi') return $html;
    
    $cacheDir = __DIR__ . '/../storage/cache/translation/';
    if (!file_exists($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    
    $cacheFile = $cacheDir . md5($cacheKey . '_' . md5($html)) . '.html';
    if (file_exists($cacheFile)) {
        return file_get_contents($cacheFile);
    }
    
    // Advanced HTML tag-preserving translation
    try {
        $parts = preg_split('/(<[^>]+>)/U', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $translatedHtml = "";
        
        foreach ($parts as $part) {
            if (empty($part)) continue;
            
            // If it is a HTML tag, keep it exactly as is
            if (strpos($part, '<') === 0 && strpos($part, '>') === strlen($part) - 1) {
                $translatedHtml .= $part;
            } else {
                // Translate text node, ignoring raw spacing or pure numbers
                $trimmed = trim($part);
                if (empty($trimmed) || is_numeric($trimmed) || (strlen($trimmed) <= 1 && !preg_match('/\p{L}/u', $trimmed))) {
                    $translatedHtml .= $part;
                } else {
                    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=vi&tl=en&dt=t&q=" . urlencode($trimmed);
                    $options = [
                        "http" => [
                            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.36\r\n",
                            "timeout" => 1.5
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
        // Fallback to original html
    }
    
    // Nếu thất bại hoặc có lỗi, ghi tạm thời chuỗi gốc vào cache để không tiếp tục gửi request làm chậm trang
    @file_put_contents($cacheFile, $html);
    return $html;
}

/**
 * TỰ ĐỘNG DỊCH VĂN BẢN THUẦN SANG TIẾNG ANH & CACHE FILE TĨNH
 * @param string $text Văn bản gốc (tiếng Việt từ DB)
 * @param string $cacheKey Key định danh để lưu cache
 * @return string Văn bản đã dịch
 */
function translate_text($text, $cacheKey) {
    if (empty($text)) return $text;
    if (getCurrentLang() === 'vi') return $text;
    
    $cacheDir = __DIR__ . '/../storage/cache/translation/';
    if (!file_exists($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    
    $cacheFile = $cacheDir . md5($cacheKey . '_' . md5($text)) . '.txt';
    if (file_exists($cacheFile)) {
        return file_get_contents($cacheFile);
    }
    
    try {
        $trimmed = trim($text);
        if (empty($trimmed) || is_numeric($trimmed) || (strlen($trimmed) <= 1 && !preg_match('/\p{L}/u', $trimmed))) {
            return $text;
        }
        
        $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=vi&tl=en&dt=t&q=" . urlencode($trimmed);
        $options = [
            "http" => [
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.36\r\n",
                "timeout" => 1.5
            ]
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response !== false) {
            $resData = json_decode($response, true);
            if (isset($resData[0])) {
                $translatedText = "";
                foreach ($resData[0] as $segment) {
                    $translatedText .= $segment[0] ?? "";
                }
                if (!empty($translatedText)) {
                    $left_space = strlen($text) - strlen(ltrim($text));
                    $right_space = strlen($text) - strlen(rtrim($text));
                    $finalResult = str_repeat(" ", $left_space) . $translatedText . str_repeat(" ", $right_space);
                    @file_put_contents($cacheFile, $finalResult);
                    return $finalResult;
                }
            }
        }
    } catch (\Exception $e) {
        // Fallback to original text
    }
    
    // Nếu thất bại hoặc có lỗi, ghi tạm thời chuỗi gốc vào cache để không tiếp tục gửi request làm chậm trang
    @file_put_contents($cacheFile, $text);
    return $text;
}
?>

