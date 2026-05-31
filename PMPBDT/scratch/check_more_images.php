<?php
// Set env variables for DatabaseConnection
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_NAME'] = 'dienmay';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = '';
putenv('DB_HOST=localhost');
putenv('DB_NAME=dienmay');
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/database.php';

$stmt = $db->query("SELECT id, name, more_images FROM products");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . " - " . $row['name'] . "\n";
    echo "Raw: " . var_export($row['more_images'], true) . "\n";
    $decoded = json_decode($row['more_images'], true);
    echo "Decoded type: " . gettype($decoded) . "\n";
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON Error: " . json_last_error_msg() . "\n";
    }
    echo "---------------------------------\n";
}
