<?php
// Bypass CSRF for local CLI testing
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['email'] = 'guest_test_' . time() . '@gmail.com';
$_POST['csrf_token'] = 'dummy';

// Tạm thời vô hiệu hóa session/CSRF cho script CLI này bằng cách require trực tiếp API thay vì qua http
require_once __DIR__ . '/../views/api/subscribe.php';
