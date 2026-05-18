<?php
$GLOBALS['JSON_INPUT'] = ['prompt' => 'Tivi'];
$_SERVER['REQUEST_METHOD'] = 'POST';
ob_start();
include 'd:/Sever/htdocs/PMVSCuoi/views/api/chatbot.php';
$output = ob_get_clean();
echo "OUTPUT: \n" . $output;
