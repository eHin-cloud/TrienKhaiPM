<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');

try {
    $db = \App\Database\DatabaseConnection::getInstance();
    
    // Hash mật khẩu '12345678a'
    $hashedPassword = password_hash('12345678a', PASSWORD_DEFAULT);
    
    // Reset mật khẩu cho 'admin'
    $db->prepare("UPDATE users SET password = ? WHERE username = 'admin'")->execute([$hashedPassword]);
    echo "Reset mật khẩu cho 'admin' thành '12345678a' thành công!\n";
    
    // Reset mật khẩu cho 'nhanvien'
    $db->prepare("UPDATE users SET password = ? WHERE username = 'nhanvien'")->execute([$hashedPassword]);
    echo "Reset mật khẩu cho 'nhanvien' thành '12345678a' thành công!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
