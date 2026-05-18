<?php
require_once __DIR__ . '/../core/database.php';
try {
    $stmt = $db->query('DESCRIBE products');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "Field: {$col['Field']} | Type: {$col['Type']} | Null: {$col['Null']} | Key: {$col['Key']} | Default: " . json_encode($col['Default']) . " | Extra: {$col['Extra']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
