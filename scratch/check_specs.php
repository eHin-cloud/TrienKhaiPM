<?php
require_once __DIR__ . '/../core/database.php';
$stmt = $db->query("SELECT id, name, specifications FROM products LIMIT 10");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($products as $p) {
    echo "ID: " . $p['id'] . " | Name: " . $p['name'] . "\n";
    echo "Specs: " . $p['specifications'] . "\n";
    $decoded = json_decode($p['specifications'], true);
    echo "Is JSON: " . ($decoded ? "YES" : "NO") . "\n";
    echo "-----------------------------------\n";
}
