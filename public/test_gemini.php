<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../core/config_api.php';

$apiKey = $GEMINI_API_KEY ?? '';
echo "<h2>Test Gemini 3.1 Pro Preview</h2>";

$models = ["gemini-3-flash-preview", "gemini-3.1-pro-preview", "gemini-3.1-flash-tts-preview", "gemini-2.0-flash", "gemini-2.0-flash-lite"];

$testPrompt = json_encode([
    "contents" => [["parts" => [["text" => "1+1=?"]]]]
]);

foreach ($models as $model) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . trim($apiKey);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $testPrompt);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $color = ($httpCode === 200) ? 'green' : 'red';
    $icon = ($httpCode === 200) ? '✅' : '❌';
    
    echo "<p><b>$model</b> → <span style='color:$color'><b>$icon HTTP $httpCode</b></span>";
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'N/A';
        echo " — " . htmlspecialchars(substr($text, 0, 50));
    } elseif ($httpCode === 429) {
        echo " — Hết quota";
    } elseif ($httpCode === 404) {
        echo " — Model không tồn tại";
    } else {
        $data = json_decode($response, true);
        echo " — " . htmlspecialchars(substr($data['error']['message'] ?? '', 0, 100));
    }
    echo "</p>";
}
