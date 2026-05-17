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

$fullPrompt = "BỐI CẢNH: Bạn là Trợ lý bán hàng thông minh của Điện Máy PRO.
NGỮ CẢNH KHO HÀNG (Dữ liệu từ Database):
{$productKnowledge}

YÊU CẦU CỰC KỲ QUAN TRỌNG VỀ ĐỊNH DẠNG (KHÔNG ĐƯỢC VI PHẠM):
1. KHÔNG ĐƯỢC DÙNG MARKDOWN: Tuyệt đối không dùng dấu sao (**), dấu gạch đầu dòng (-), hay dấu thăng (#).
2. DÙNG THẺ HTML: 
   - Để in đậm, hãy dùng thẻ <b>...</b>.
   - Để xuống dòng, hãy dùng thẻ <br>.
3. QUY TẮC CHÈN LINK SẢN PHẨM: 
   - Bạn PHẢI chèn link sản phẩm bằng thẻ <a> ngay sau khi nhắc tên sản phẩm.
   - Định dạng: <a href=\"product_detail.php?id=ID_SAN_PHAM\" class=\"text-blue-600 font-bold hover:underline\">Xem chi tiết</a>
4. NGÔN NGỮ: Khách hỏi tiếng gì, đáp tiếng đó.
5. PHONG CÁCH: Thân thiện, ngắn gọn, đi thẳng vào vấn đề tư vấn.

{$contextInstruction}

CÂU HỎI CỦA KHÁCH: {$prompt}";

// Danh sách các model fallback (Thử lần lượt - chỉ giữ model còn hoạt động)
$models = [
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

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    // Thêm SSL verify false nếu server user bị lỗi chứng chỉ (rất phổ biến trên localhost/XAMPP)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response !== false && $httpCode === 200) {
        $resData = json_decode($response, true);
        if (isset($resData['candidates'][0]['content']['parts'][0]['text'])) {
            $aiText = $resData['candidates'][0]['content']['parts'][0]['text'];
            $success = true;
            break; // Thành công thì thoát vòng lặp
        }
    }

    // Lưu lại lỗi của model cuối cùng nếu thất bại
    $lastError = $curlError ? "CURL Error: $curlError" : "HTTP $httpCode";
}

if ($success) {
    echo json_encode(['success' => true, 'response' => $aiText]);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối AI (' . $lastError . ')']);
}
