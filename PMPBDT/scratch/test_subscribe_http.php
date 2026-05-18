<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['email'] = 'test_guest@gmail.com';

// Bỏ qua filter_input bằng cách set trực tiếp vào _POST, 
// nhưng subscribe.php dùng filter_input, nên ta phải giả lập POST đúng chuẩn.
// Thay vì giả lập, ta include file subscribe.php và xem nó in ra gì.
// Ta phải override $_POST for filter_input ? Không, filter_input đọc từ SAPI.
// Vậy ta dùng file_get_contents để gửi HTTP request thật.
$url = 'http://localhost/PMPBDT/PMPBDT/public/subscribe.php';
$data = ['email' => 'test_guest_123@gmail.com', 'csrf_token' => 'dummy_bypassed'];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data),
    ],
];
$context  = stream_context_create($options);

// Bỏ qua lỗi để đọc nội dung trả về
$result = @file_get_contents($url, false, $context);
echo "RESPONSE:\n";
var_dump($result);
if (isset($http_response_header)) {
    echo "\nHEADERS:\n";
    print_r($http_response_header);
}
