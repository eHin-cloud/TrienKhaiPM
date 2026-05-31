<?php
require_once __DIR__ . '/../core/database.php';
try {
    $stmt = $db->query("SELECT COUNT(*) FROM categories");
    echo "Categories count: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $db->query("SELECT * FROM categories");
    $cats = $stmt->fetchAll();
    echo "Data: " . json_encode($cats, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
