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
    
    // 1. Describe products table
    $stmt = $db->query("DESCRIBE products");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "--- PRODUCTS SCHEMA ---\n";
    foreach ($columns as $col) {
        echo "Field: {$col['Field']} | Type: {$col['Type']} | Null: {$col['Null']} | Key: {$col['Key']} | Default: {$col['Default']} | Extra: {$col['Extra']}\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
