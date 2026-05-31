<?php
require_once __DIR__ . '/../vendor/autoload.php';
try {
    $db = \App\Database\DatabaseConnection::getInstance();
    $stmt = $db->query('SELECT name FROM categories');
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "CATEGORIES:\n";
    print_r($categories);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
