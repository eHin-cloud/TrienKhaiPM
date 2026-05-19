<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'trienkhai_pm';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "CONNECTED SUCCESSFULY TO DATABASE: $dbname\n\n";
    
    // Thực thi thay đổi cấu trúc bảng products
    echo "Altering products table to set default values for rate_star and total_reviews...\n";
    
    $db->exec("ALTER TABLE products MODIFY COLUMN rate_star DECIMAL(3,2) NOT NULL DEFAULT 0.00");
    echo "- Set default 0.00 for rate_star successfully.\n";
    
    $db->exec("ALTER TABLE products MODIFY COLUMN total_reviews INT NOT NULL DEFAULT 0");
    echo "- Set default 0 for total_reviews successfully.\n";
    
    echo "\nAll database alterations completed successfully!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
