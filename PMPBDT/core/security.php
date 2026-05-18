<?php
/**
 * ============================================================
 * SECURITY.PHP - CSRF PROTECTION HELPERS
 * ============================================================
 * 
 * Cung cấp các hàm bảo vệ chống giả mạo request (CSRF).
 * 
 * SỬ DỤNG:
 * 1. generate_csrf_token()    - Tạo/lấy token, lưu vào $_SESSION
 * 2. csrf_input_field()       - Sinh <input hidden> để nhúng vào form
 * 3. get_csrf_token_value()   - Lấy giá trị token hiện tại (dùng cho JS)
 * 4. verify_csrf_token()      - So sánh token gửi lên với session
 * 5. validate_csrf_request()  - Xác thực toàn bộ POST request (auto 403)
 */

/**
 * Generates a CSRF token and stores it in the session.
 * If a token already exists, it returns the existing one.
 * 
 * @return string The generated CSRF token.
 */
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Returns the current CSRF token value (for embedding in JS variables).
 * 
 * @return string The current CSRF token.
 */
function get_csrf_token_value() {
    return generate_csrf_token();
}

/**
 * Alias cho get_csrf_token_value()
 */
function get_csrf_token() {
    return get_csrf_token_value();
}

/**
 * Generates an HTML hidden input field containing the CSRF token.
 * Usage: <?= csrf_input_field() ?> inside any <form method="POST">.
 * 
 * @return string HTML hidden input element.
 */
function csrf_input_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Verifies the CSRF token from the request against the session token.
 * Uses hash_equals() to prevent timing attacks.
 * 
 * @param string $token The token to verify, usually from $_POST['csrf_token'].
 * @return bool True if the token is valid, false otherwise.
 */
function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validates the CSRF token for the current POST request.
 * Supports both standard form POST and JSON body (AJAX fetch).
 * If validation fails, it terminates the request with a 403 Forbidden response.
 */
function validate_csrf_request() {
    // 1. Tuyệt đối bỏ qua kiểm tra CSRF cho Mobile App và các luồng API
    if (
        (defined('SKIP_CSRF_CHECK') && SKIP_CSRF_CHECK === true) || 
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false)
    ) {
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Thử lấy token từ $_POST (form submit thông thường)
        $token = $_POST['csrf_token'] ?? '';

        // Nếu không có trong $_POST, thử lấy từ JSON body (AJAX fetch)
        if (empty($token)) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($contentType, 'application/json') !== false) {
                $inputBody = file_get_contents('php://input');
                $jsonData = json_decode($inputBody, true);
                $token = $jsonData['csrf_token'] ?? '';
                // Lưu lại dữ liệu JSON vào biến toàn cục để các script sau có thể dùng
                $GLOBALS['JSON_INPUT'] = $jsonData;
            }
        }

        // Nếu không có trong cả POST lẫn JSON, thử lấy từ header X-CSRF-Token
        if (empty($token)) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }

        if (!verify_csrf_token($token)) {
            http_response_code(403);
            // Trả response phù hợp kiểu content
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $acceptsJson = (stripos($contentType, 'application/json') !== false) 
                        || (isset($_POST['ajax'])) 
                        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
            
            if ($acceptsJson) {
                if (ob_get_length()) {
                    ob_clean();
                }
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Phiên làm việc không hợp lệ hoặc đã hết hạn. Vui lòng tải lại trang và thử lại.'
                ]);
            } else {
                echo '<div style="text-align:center;margin-top:50px;font-family:sans-serif;">';
                echo '<h1 style="color:#d70018;">403 - Forbidden</h1>';
                echo '<p>Yêu cầu bị từ chối: Token bảo mật không hợp lệ hoặc đã hết hạn.</p>';
                echo '<p><a href="javascript:history.back()" style="color:#0046ab;">← Quay lại trang trước</a></p>';
                echo '</div>';
            }
            exit;
        }
    }
}

/**
 * Dò tìm địa chỉ thực tế từ IP thông qua API công cộng tốc độ cao (có cache)
 * @param string $ip
 * @return string Tên thành phố, quốc gia
 */
function get_location_by_ip($ip) {
    if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0 || strpos($ip, '127.') === 0) {
        return 'Hồ Chí Minh, Việt Nam'; // Địa chỉ mặc định của nhà phát triển cho Localhost
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['ip_location_cache'][$ip])) {
        return $_SESSION['ip_location_cache'][$ip];
    }

    $url = "http://ip-api.com/json/" . urlencode($ip) . "?fields=status,country,city,regionName";
    
    $options = [
        "http" => [
            "timeout" => 1.5, // Giới hạn 1.5s để không làm chậm màn hình đăng nhập khi mạng lag
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['status']) && $data['status'] === 'success') {
            $city = $data['city'] ?? '';
            $region = $data['regionName'] ?? '';
            $country = $data['country'] ?? 'Việt Nam';

            if ($country === 'Vietnam') {
                $country = 'Việt Nam';
            }

            $loc = '';
            if (!empty($city)) {
                $loc .= $city;
            } elseif (!empty($region)) {
                $loc .= $region;
            }

            if (!empty($loc)) {
                $loc .= ', ' . $country;
            } else {
                $loc = $country;
            }

            $_SESSION['ip_location_cache'][$ip] = $loc;
            return $loc;
        }
    }

    return 'Việt Nam';
}

/**
 * Records a login attempt to the login_history table.
 * 
 * @param PDO $db The database connection.
 * @param int $userId The ID of the user (or 0 if unknown).
 * @param string $status The status of the login ('success' or 'failed').
 * @return bool True if successfully recorded, false otherwise.
 */
function record_login_history($db, $userId, $status) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Handle Cloudflare or Proxy IP
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }
        
        // Convert ::1 to 127.0.0.1
        if ($ip === '::1') {
            $ip = '127.0.0.1';
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Dò vị trí chính xác từ địa chỉ IP
        $location = get_location_by_ip($ip);
        
        $stmt = $db->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, status, location) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $ip, $ua, $status, $location]);
    } catch (Exception $e) {
        return false;
    }
}
