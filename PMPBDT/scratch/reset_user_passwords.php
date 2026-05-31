<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../core/database.php';

$hashed = password_hash('12345678', PASSWORD_DEFAULT);

// Update mật khẩu của admin (username = 'admin'), manager (username = 'manager') và khachhang (username = 'khachhang')
$stmt = $db->prepare("UPDATE users SET password = ? WHERE username IN ('admin', 'manager', 'khachhang')");
$stmt->execute([$hashed]);

echo "SUCCESS: Updated passwords of 'admin', 'manager', and 'khachhang' to '12345678'\n";
