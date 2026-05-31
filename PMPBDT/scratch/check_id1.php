<?php
require_once __DIR__ . '/../core/database.php';
$stmt = $db->query("SELECT id, name, image, more_images FROM products WHERE id = 1");
$product = $stmt->fetch(PDO::FETCH_ASSOC);
echo "ID: " . $product['id'] . "\n";
echo "Name: " . $product['name'] . "\n";
echo "Main Image: " . $product['image'] . "\n";
echo "More Images: " . $product['more_images'] . "\n";
$decoded = json_decode($product['more_images'], true);
echo "Decoded count: " . (is_array($decoded) ? count($decoded) : "not an array") . "\n";
