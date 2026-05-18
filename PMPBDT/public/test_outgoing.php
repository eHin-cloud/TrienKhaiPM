<?php
/**
 * TEST: Kiểm tra hosting có cho phép kết nối ra ngoài không
 * Upload file này lên hosting, truy cập: https://dienmaypro.wuaze.com/test_outgoing.php
 * Nếu hosting chặn outgoing connections → Chatbot sẽ KHÔNG BAO GIỜ hoạt động
 */
header('Content-Type: text/html; charset=utf-8');
echo "<h2>Test kết nối ra ngoài (Outgoing Connection)</h2>";

// Test 1: cURL
echo "<h3>1. Test cURL</h3>";
if (function_exists('curl_init')) {
    $ch = curl_init('https://httpbin.org/get');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($result && $httpCode === 200) {
        echo "<p style='color:green'>✅ cURL hoạt động! HTTP $httpCode</p>";
    } else {
        echo "<p style='color:red'>❌ cURL THẤT BẠI! HTTP $httpCode - Error: $error</p>";
    }
} else {
    echo "<p style='color:red'>❌ cURL không có sẵn!</p>";
}

// Test 2: file_get_contents
echo "<h3>2. Test file_get_contents</h3>";
$ctx = stream_context_create(['http' => ['timeout' => 10], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$result2 = @file_get_contents('https://httpbin.org/get', false, $ctx);
if ($result2) {
    echo "<p style='color:green'>✅ file_get_contents hoạt động!</p>";
} else {
    echo "<p style='color:red'>❌ file_get_contents THẤT BẠI! Hosting chặn kết nối ra ngoài.</p>";
}

// Test 3: Kết nối đến Google API (Gemini endpoint)
echo "<h3>3. Test kết nối đến Google Generative AI</h3>";
if (function_exists('curl_init')) {
    $ch = curl_init('https://generativelanguage.googleapis.com/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_NOBODY, true); // Chỉ lấy header, không body
    $result3 = curl_exec($ch);
    $httpCode3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error3 = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode3 > 0) {
        echo "<p style='color:green'>✅ Kết nối đến Google API OK! HTTP $httpCode3</p>";
    } else {
        echo "<p style='color:red'>❌ KHÔNG thể kết nối đến Google API! Error: $error3</p>";
        echo "<p style='color:red'><b>=> CHATBOT SẼ KHÔNG HOẠT ĐỘNG TRÊN HOSTING NÀY!</b></p>";
    }
} else {
    echo "<p style='color:orange'>⚠️ Dùng file_get_contents để test Google...</p>";
    $result3 = @file_get_contents('https://generativelanguage.googleapis.com/', false, $ctx);
    if ($result3 !== false) {
        echo "<p style='color:green'>✅ Kết nối Google OK</p>";
    } else {
        echo "<p style='color:red'>❌ KHÔNG thể kết nối Google API!</p>";
    }
}

// Test 4: Kiểm tra allow_url_fopen
echo "<h3>4. Kiểm tra cấu hình PHP</h3>";
echo "<p>allow_url_fopen: <b>" . (ini_get('allow_url_fopen') ? 'ON ✅' : 'OFF ❌') . "</b></p>";
echo "<p>cURL extension: <b>" . (extension_loaded('curl') ? 'Có ✅' : 'Không ❌') . "</b></p>";
echo "<p>PHP version: <b>" . phpversion() . "</b></p>";
