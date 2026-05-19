<?php
require_once __DIR__ . '/../vendor/autoload.php';
try {
    $db = \App\Database\DatabaseConnection::getInstance();
    
    // Check foreign keys or table status
    $stmt = $db->query("
        SELECT 
            TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE
            REFERENCED_TABLE_SCHEMA = '" . getenv('DB_NAME') . "' AND TABLE_NAME = 'products'
    ");
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "--- FOREIGN KEYS ---\n";
    foreach ($fks as $fk) {
        print_r($fk);
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
