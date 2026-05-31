<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../core/database.php';

try {
    // Kiểm tra xem cột redirect_url đã tồn tại chưa
    $stmt = $db->query("SHOW COLUMNS FROM notifications LIKE 'redirect_url'");
    $column = $stmt->fetch();
    
    if (!$column) {
        $db->exec("ALTER TABLE notifications ADD COLUMN redirect_url VARCHAR(255) DEFAULT NULL");
        echo "SUCCESS: Added 'redirect_url' column to 'notifications' table." . PHP_EOL;
    } else {
        echo "INFO: 'redirect_url' column already exists in 'notifications' table." . PHP_EOL;
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
