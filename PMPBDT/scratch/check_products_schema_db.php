<?php
require_once __DIR__ . '/../vendor/autoload.php';
try {
    $db = \App\Database\DatabaseConnection::getInstance();
    echo "CONNECTED SUCCESSFULY TO DATABASE\n\n";
    
    // Describe products table
    $stmt = $db->query("DESCRIBE products");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "--- PRODUCTS SCHEMA ---\n";
    foreach ($columns as $col) {
        echo "Field: {$col['Field']} | Type: {$col['Type']} | Null: {$col['Null']} | Key: {$col['Key']} | Default: {$col['Default']} | Extra: {$col['Extra']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
