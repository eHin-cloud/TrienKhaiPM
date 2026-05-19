<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../core/database.php';

$hashed = password_hash('123456', PASSWORD_DEFAULT);

// Update mật khẩu của admin (username = 'admin') và khachhang (username = 'khachhang')
$stmt = $db->prepare("UPDATE users SET password = ? WHERE username IN ('admin', 'khachhang')");
$stmt->execute([$hashed]);

echo "SUCCESS: Updated passwords of 'admin' and 'khachhang' to '123456'\n";
