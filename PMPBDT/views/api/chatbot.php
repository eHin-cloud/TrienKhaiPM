<?php
ini_set('display_errors', '0');
error_reporting(0);

/**
 * API CHATBOT RAG (Retrieval-Augmented Generation)
 * Thay vì tải toàn bộ DB, PHP sẽ:
 * 1. Nhận câu hỏi
 * 2. Tìm kiếm sản phẩm liên quan trong DB bằng FullText hoặc LIKE
 * 3. Gửi câu hỏi + Ngữ cảnh (sản phẩm tìm được) lên Gemini API
 * 4. Trả về cho Frontend
 */

require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/config_api.php'; // File chứa $GEMINI_API_KEY (nếu có)

header('Content-Type: application/json; charset=utf-8');

// Nhận dữ liệu POST từ JS (hỗ trợ đọc lại raw input đã lưu ở index.php)
$json = !empty($GLOBALS['RAW_PHP_INPUT']) ? $GLOBALS['RAW_PHP_INPUT'] : file_get_contents('php://input');
$data = json_decode($json, true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = trim($data['action'] ?? $_GET['action'] ?? '');
if ($action === 'get_history') {
    $history = $_SESSION['chatbot_history'] ?? [];
    echo json_encode(['success' => true, 'history' => $history]);
    exit;
}
if ($action === 'clear_history') {
    unset($_SESSION['chatbot_history'], $_SESSION['chatbot_last_active']);
    echo json_encode(['success' => true, 'message' => 'Lịch sử chat đã được xóa.']);
    exit;
}

$prompt = trim($data['prompt'] ?? '');
$currentProductContext = trim($data['context'] ?? '');

if (!$prompt) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập câu hỏi.']);
    exit;
}

// Lấy API KEY (Nếu trong code có config_api.php định nghĩa $GEMINI_API_KEY)
$apiKey = defined('GEMINI_API_KEY_CONSTANT') ? GEMINI_API_KEY_CONSTANT : (isset($GEMINI_API_KEY) ? $GEMINI_API_KEY : '');

// ----------------------------------------------------
// BƯỚC 1: RAG - TRÍCH XUẤT TỪ KHÓA & TÌM KIẾM CSDL
// ----------------------------------------------------
$keywords = explode(' ', mb_strtolower($prompt, 'UTF-8'));
// Lọc các từ vô nghĩa (stop words)
$stopwords = ['là', 'gì', 'cho', 'tôi', 'hỏi', 'có', 'không', 'giá', 'bao', 'nhiêu', 'tư', 'vấn', 'cái', 'này', 'xin', 'chào', 'mua', 'bán'];
$searchTerms = [];

foreach ($keywords as $word) {
    $word = trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $word));
    if (mb_strlen($word) > 2 && !in_array($word, $stopwords)) {
        $searchTerms[] = $word;
    }
}

// Truy vấn MySQL để lấy tối đa 10 sản phẩm gần giống nhất
$productKnowledge = "";
if (!empty($searchTerms)) {
    try {
        $sql = "SELECT id, name, price FROM products WHERE ";
        $conditions = [];
        $params = [];
        foreach ($searchTerms as $term) {
            $conditions[] = "name LIKE ?";
            $params[] = "%$term%";
        }
        $sql .= implode(" OR ", $conditions) . " LIMIT 10";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $foundProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($foundProducts)) {
            $productKnowledge .= "KẾT QUẢ TÌM KIẾM TRONG KHO HÀNG LIÊN QUAN ĐẾN CÂU HỎI:\n";
            foreach ($foundProducts as $p) {
                $pPrice = number_format($p['price'], 0, ',', '.');
                $productKnowledge .= "- " . $p['name'] . ": Giá {$pPrice}đ (Link: product_detail.php?id={$p['id']})\n";
            }
        } else {
            $productKnowledge .= "Hệ thống không tìm thấy sản phẩm nào khớp chính xác với từ khóa.\n";
        }
    } catch (Exception $e) {
        $productKnowledge = "Lỗi truy vấn kho hàng.";
    }
} else {
    $productKnowledge = "Khách hàng đang hỏi câu hỏi chung chung, không chứa từ khóa sản phẩm rõ ràng.";
}

// ----------------------------------------------------
// BƯỚC 2: QUẢN LÝ LỊCH SỬ CHAT (HẾT HẠN SAU 30 PHÚT) & CHUẨN BỊ PROMPT HỘI THOẠI NHIỀU LƯỢT (MULTI-TURN)
// ----------------------------------------------------
$now = time();
$sessionTimeout = 30 * 60; // 30 phút = 1800 giây

// Nếu không hoạt động quá 30 phút, reset lịch sử
if (isset($_SESSION['chatbot_last_active']) && ($now - $_SESSION['chatbot_last_active'] > $sessionTimeout)) {
    unset($_SESSION['chatbot_history']);
}
$_SESSION['chatbot_last_active'] = $now;

if (!isset($_SESSION['chatbot_history'])) {
    $_SESSION['chatbot_history'] = [];
}

// Xây dựng mảng nội dung hội thoại gửi đi (Multi-turn contents)
$contents = [];
foreach ($_SESSION['chatbot_history'] as $chat) {
    $contents[] = [
        "role" => $chat['role'] === 'user' ? 'user' : 'model',
        "parts" => [["text" => $chat['text']]]
    ];
}

// Lượt chat hiện tại kèm bối cảnh
$currentUserTurnText = "CÂU HỎI HIỆN TẠI CỦA KHÁCH HÀNG: {$prompt}\n\n";
if (!empty($productKnowledge)) {
    $currentUserTurnText .= "DỮ LIỆU KHO HÀNG ĐỂ TƯ VẤN:\n{$productKnowledge}\n\n";
}
$contextInstruction = "Khách hàng đang ở Trang chủ hoặc xem danh mục chung. Hãy tư vấn tổng quan.";
if ($currentProductContext) {
    $contextInstruction = "ĐẶC BIỆT LƯU Ý: Khách hàng ĐANG XEM SẢN PHẨM NÀY:\n{$currentProductContext}\n-> Ưu tiên dùng thông tin sản phẩm này để trả lời.";
}
$currentUserTurnText .= "NGỮ CẢNH TRANG WEB ĐANG XEM:\n{$contextInstruction}";

$contents[] = [
    "role" => "user",
    "parts" => [["text" => $currentUserTurnText]]
];

$systemInstructionText = "Bạn là Trợ lý bán hàng thông minh của Điện Máy PRO.
NGƯỜI DÙNG ĐANG XEM GIAO DIỆN HỘI THOẠI BẰNG TIẾNG VIỆT HOẶC TIẾNG ANH.

YÊU CẦU CỰC KỲ QUAN TRỌNG VỀ ĐỊNH DẠNG (KHÔNG ĐƯỢC VI PHẠM):
1. KHÔNG ĐƯỢC DÙNG MARKDOWN: Tuyệt đối không dùng dấu sao (**), dấu gạch đầu dòng (-), hay dấu thăng (#).
2. DÙNG THẺ HTML: 
   - Để in đậm, hãy dùng thẻ <b>...</b>.
   - Để xuống dòng, hãy dùng thẻ <br>.
3. QUY TẮC CHÈN LINK SẢN PHẨM: 
   - Bạn PHẢI chèn link sản phẩm bằng thẻ <a> ngay sau khi nhắc tên sản phẩm.
   - Định dạng: <a href=\"product_detail.php?id=ID_SAN_PHAM\" class=\"text-blue-600 font-bold hover:underline\">Xem chi tiết</a>
4. NGÔN NGỮ: Khách hỏi tiếng gì, đáp tiếng đó.
5. PHONG CÁCH: Thân thiện, ngắn gọn, đi thẳng vào vấn đề tư vấn.";

// Danh sách các model fallback (Thử lần lượt - bắt đầu bằng gemini-1.5-flash cực kỳ ổn định)
$models = [
    "gemini-1.5-flash",
    "gemini-2.5-flash",
    "gemini-2.5-flash-lite"
];

$aiText = '';
$lastError = '';
$success = false;

foreach ($models as $model) {
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . trim($apiKey);
    $postData = json_encode([
        "systemInstruction" => [
            "parts" => [
                ["text" => $systemInstructionText]
            ]
        ],
        "contents" => $contents
    ]);

    $response = false;
    $httpCode = 0;
    $curlError = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($curlError) {
            $curlError = "CURL Error: " . $curlError;
        }
    } else {
        // Fallback: Sử dụng stream context để gọi API mà không cần thư viện cURL (phổ biến trên XAMPP)
        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $postData,
                'ignore_errors' => true,
                'timeout' => 15
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];
        $context = stream_context_create($opts);
        $response = @file_get_contents($apiUrl, false, $context);
        
        // Hỗ trợ PHP 8.4+ tránh cảnh báo deprecation đối với biến $http_response_header
        $headers = [];
        if (function_exists('http_get_last_response_headers')) {
            $headers = http_get_last_response_headers() ?: [];
        } else {
            $varName = 'http_response_header';
            $headers = isset($$varName) ? $$varName : [];
        }
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                $httpCode = intval($matches[1]);
                break;
            }
        }
        if ($response === false) {
            $curlError = "file_get_contents failed to fetch Gemini API";
        }
    }

    if ($response !== false && $httpCode === 200) {
        $resData = json_decode($response, true);
        if (isset($resData['candidates'][0]['content']['parts'][0]['text'])) {
            $aiText = $resData['candidates'][0]['content']['parts'][0]['text'];
            $success = true;
            break; // Thành công thì thoát vòng lặp
        }
    }

    // Lưu lại lỗi của model cuối cùng nếu thất bại
    $lastError = $curlError ? $curlError : "HTTP $httpCode";
}

if ($success) {
    // Chỉ lưu prompt gốc của khách và câu trả lời sạch của AI để hội thoại tiếp theo nhẹ nhàng và tự nhiên nhất
    $_SESSION['chatbot_history'][] = ["role" => "user", "text" => $prompt];
    $_SESSION['chatbot_history'][] = ["role" => "model", "text" => $aiText];

    // Giới hạn lưu tối đa 10 lượt hội thoại gần nhất (20 tin nhắn) để tránh quá tải token
    if (count($_SESSION['chatbot_history']) > 20) {
        $_SESSION['chatbot_history'] = array_slice($_SESSION['chatbot_history'], -20);
    }

    echo json_encode(['success' => true, 'response' => $aiText]);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối AI (' . $lastError . ')']);
}
