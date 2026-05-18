<?php
$html = '<ul><li><strong>Công nghệ lọc:</strong> Hệ thống 5 bước lọc tiêu chuẩn, loại bỏ hoàn toàn kim loại nặng, vi khuẩn, thuốc trừ sâu</li><li><strong>Công suất lọc:</strong> Đạt 11.8 Lít/giờ (Đáp ứng thoải mái nhu cầu uống trực tiếp và nấu ăn cho gia đình 4-6 người)</li><li><strong>Hệ thống cảnh báo:</strong> Hệ thống điện tử EMS tích hợp: Cảnh báo thay lõi, Cảnh báo rò rỉ nước, Tự động sục rửa màng lọc</li></ul>';

function translate_text_only($text) {
    $text = trim($text);
    if (empty($text) || is_numeric($text)) return $text;
    
    // Call Google Translate API
    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=vi&tl=en&dt=t&q=" . urlencode($text);
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
            return $translated;
        }
    }
    return $text;
}

function translate_html_advanced($html) {
    // Split HTML by tags, capturing the tags themselves
    $parts = preg_split('/(<[^>]+>)/U', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $result = "";
    
    foreach ($parts as $part) {
        if (empty($part)) continue;
        
        // If it is a tag, keep it as is
        if (strpos($part, '<') === 0 && strpos($part, '>') === strlen($part) - 1) {
            $result .= $part;
        } else {
            // It is plain text, translate it
            $result .= translate_text_only($part);
        }
    }
    
    return $result;
}

echo "--- ADVANCED TRANSLATED OUTPUT ---\n";
echo translate_html_advanced($html) . "\n";
