<?php
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

// Nhận dữ liệu POST từ JS
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$prompt = trim($data['prompt'] ?? '');
$currentProductContext = trim($data['context'] ?? '');

if (!$prompt) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập câu hỏi.']);
    exit;
}

// Lấy API KEY (Nếu trong code có config_api.php định nghĩa $GEMINI_API_KEY)
$apiKey = defined('GEMINI_API_KEY_CONSTANT') ? GEMINI_API_KEY_CONSTANT : (isset($GEMINI_API_KEY) ? $GEMINI_API_KEY : '');
if (!$apiKey) {
    // Nếu chưa có, cố gắng đọc từ biến môi trường hoặc fallback. (Cần đảm bảo user đã cấu hình).
    // Ở đây ta giả sử đã có $GEMINI_API_KEY từ config
}

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
// BƯỚC 2: CHUẨN BỊ PROMPT & GỌI GEMINI API
// ----------------------------------------------------
$replyLangInstruction = "5. QUAN TRỌNG: Khách hàng hỏi bằng ngôn ngữ nào, bạn PHẢI trả lời hoàn toàn bằng ngôn ngữ đó (Ví dụ: Hỏi Tiếng Việt -> Trả lời Tiếng Việt, Hỏi Tiếng Anh -> Trả lời Tiếng Anh).";

$contextInstruction = "Khách hàng đang ở Trang chủ hoặc xem danh mục chung. Hãy tư vấn tổng quan.";
if ($currentProductContext) {
    $contextInstruction = "ĐẶC BIỆT LƯU Ý: Khách hàng ĐANG XEM SẢN PHẨM NÀY:\n{$currentProductContext}\n-> Ưu tiên dùng thông tin sản phẩm này để trả lời.";
}

$fullPrompt = "BỐI CẢNH: Bạn là Trợ lý bán hàng thông minh của Điện Máy PRO - hệ thống bán lẻ công nghệ chuyên nghiệp.
NGỮ CẢNH KHO HÀNG (Dữ liệu từ Database):
{$productKnowledge}

QUY TẮC PHẢN HỒI (CỰC KỲ QUAN TRỌNG):
1. PHẢI TRẢ LỜI CHI TIẾT, GIÀU THÔNG TIN VÀ CÓ PHÂN TÍCH SÂU:
   - Hãy tư vấn một cách chi tiết và nhiệt tình cho khách hàng. Nếu khách hàng hỏi chung chung về laptop sinh viên, phân tích kỹ các phân loại học tập văn phòng nhẹ nhàng (Acer, Asus, Lenovo, HP) và các dòng kỹ thuật/gaming/đồ họa nặng (Dell, MacBook, laptop gaming). Gợi ý cụ thể các phân khúc để khách hàng dễ chọn lựa.

2. QUY TẮC CHÈN LINK (KHÔNG ĐƯỢC ĐỂ HỎNG LINK):
   - Để chèn link xem chi tiết cho THƯƠNG HIỆU, DANH MỤC hoặc PHÂN LOẠI SẢN PHẨM: bạn PHẢI sử dụng link tìm kiếm tích hợp của hệ thống: <a href=\"index.php?search=từ_khóa\" class=\"text-blue-600 font-bold hover:underline\">tên_thương_hiệu/tên_danh_mục</a> hoặc <a href=\"index.php?search=từ_khóa\" class=\"text-blue-600 font-bold hover:underline\">Xem chi tiết</a> (Ví dụ: index.php?search=Asus, index.php?search=Dell, index.php?search=MacBook, index.php?search=laptop+gaming, index.php?search=laptop+van+phong).
   - Đối với sản phẩm CỤ THỂ có trong NGỮ CẢNH KHO HÀNG ở trên: sử dụng đúng định dạng: <a href=\"product_detail.php?id=ID\" class=\"text-blue-600 font-bold hover:underline\">Tên sản phẩm</a>.
   - TUYỆT ĐỐI KHÔNG tự bịa các định dạng link khác. Tất cả các thương hiệu, danh mục bắt buộc phải qua link index.php?search=từ_khóa.

3. TUYỆT ĐỐI KHÔNG DÙNG MARKDOWN: Không dùng các ký tự **, -, #, * hay bất kỳ cú pháp Markdown nào trong câu trả lời.
4. DÙNG THẺ HTML ĐỂ ĐỊNH DẠNG:
   - In đậm tiêu đề hoặc từ khóa quan trọng: <b>nội dung</b>
   - Xuống dòng: <br>
5. QUY TẮC ĐỊNH DẠNG CÂU TRẢ LỜI (ĐỂ KHÔNG DÍNH CỤC VÀ KHÔNG XUỐNG DÒNG TRẮNG QUÁ NHIỀU):
   - Giữa các đoạn ý chính, dùng ĐÚNG 1 thẻ <br> để cách dòng. KHÔNG dùng nhiều thẻ <br> liên tiếp làm thừa khoảng trắng.
   - Hãy sắp xếp các phân loại sản phẩm bằng các emoji ở đầu dòng (ví dụ: 👉, ✅, 📱, 💻) để tạo bố cục thoáng đãng, chuyên nghiệp, đẹp mắt và dễ đọc.
   - Tránh viết một khối văn bản dài dính cục, chia nhỏ thành 3-4 đoạn ngắn, dễ theo dõi.
6. NGÔN NGỮ: Khách hỏi tiếng gì, đáp tiếng đó.
7. PHONG CÁCH: Thân thiện, chu đáo, nhiệt tình tư vấn như một nhân viên bán hàng chuyên nghiệp.

{$contextInstruction}

CÂU HỎI CỦA KHÁCH: {$prompt}";

// Danh sách các model fallback (Thử lần lượt)
$models = [
    "gemini-3.1-flash-lite",
    "gemini-3.5-flash",
    "gemini-3-flash-preview",
    "gemini-2.5-flash",
    "gemini-2.5-flash-lite"
];

$aiText = '';
$lastError = '';
$success = false;

foreach ($models as $model) {
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . trim($apiKey);
    $postData = json_encode([
        "contents" => [
            ["parts" => [["text" => $fullPrompt]]]
        ]
    ]);

    $response = null;
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
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
    } else {
        // Fallback using stream context for servers without cURL
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $postData,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        $headerVar = 'http_response_header';
        $response = @file_get_contents($apiUrl, false, $context);
        
        if (isset($$headerVar) && is_array($$headerVar)) {
            foreach ($$headerVar as $header) {
                if (preg_match('/HTTP\/\d+\.\d+\s+(\d+)/i', $header, $matches)) {
                    $httpCode = intval($matches[1]);
                    break;
                }
            }
        }
        if ($response === false) {
            $curlError = "file_get_contents call failed";
        }
    }

    if ($response !== false && $httpCode === 200) {
        $resData = json_decode($response, true);
        if (isset($resData['candidates'][0]['content']['parts'][0]['text'])) {
            $aiText = $resData['candidates'][0]['content']['parts'][0]['text'];
            $success = true;
            break;
        }
    }

    $lastError = $curlError ? "CURL/Stream Error: $curlError" : "HTTP $httpCode";
}

if ($success) {
    echo json_encode(['success' => true, 'response' => $aiText]);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối AI (' . $lastError . ')']);
}
