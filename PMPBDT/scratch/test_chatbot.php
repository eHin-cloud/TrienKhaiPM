<?php
$_POST = [];
// Giả lập nhận dữ liệu JSON
$prompt = 'tivi xin';
$context = '';

// Thiết lập môi trường giả lập php://input bằng cách ghi file tạm hoặc mock
// Để đơn giản hơn, ta có thể require_once chatbot.php nhưng thay đổi luồng đầu vào.
// Hãy gọi curl trực tiếp tới http://localhost/PMPBDT/public/api/chatbot.php nếu server đang chạy,
// hoặc chạy trực tiếp bằng cách bọc code chatbot.php để test.

// Chúng ta hãy gọi cURL đến API chatbot.php trên localhost để xem phản hồi thực tế!
$url = 'http://localhost/PMPBDT/public/api/chatbot.php';
// Trong trường hợp localhost khác, hãy thử cả 127.0.0.1
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'prompt' => $prompt,
    'context' => $context
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP CODE: $httpCode\n";
echo "CURL ERROR: $error\n";
echo "RESPONSE:\n$response\n";
