<?php
require_once __DIR__ . '/../core/database.php';
$stmt = $db->query("SELECT id, name, specifications FROM products WHERE name LIKE '%LG%'");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($products as $p) {
    echo "ID: " . $p['id'] . " | Name: " . $p['name'] . "\n";
    echo "Specs Raw: [" . $p['specifications'] . "]\n";
    echo "-----------------------------------\n";
}
