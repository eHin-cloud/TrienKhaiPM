<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$testData = [
    'prompt' => 'Chính sách trả góp và bảo hành thế nào?',
    'context' => ''
];

$code = file_get_contents('views/api/chatbot.php');
$code = str_replace(
    "\$json = file_get_contents('php://input');",
    "\$json = '" . addslashes(json_encode($testData)) . "';",
    $code
);

eval('?>' . $code);
