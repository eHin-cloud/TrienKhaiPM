<?php
$item = [
    "id" => 94,
    "name" => "Sạc nhanh GaN C579",
    "more_images" => '["uploads\\/banphim2.jpg","uploads\\/banphim1.jpg"]'
];

$json1 = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
echo "JSON with HEX_QUOT:\n" . $json1 . "\n\n";

$json2 = json_encode($item, JSON_UNESCAPED_UNICODE);
echo "JSON without HEX_QUOT:\n" . $json2 . "\n";
