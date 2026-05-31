<?php
require 'core/database.php';
$items = $db->query('SELECT id, image FROM products')->fetchAll();
foreach ($items as $item) {
    if (strpos($item['image'], '"') !== false || strpos($item['image'], "'") !== false || strpos($item['image'], 'more_images') !== false) {
        echo "Found suspicious image in product ID {$item['id']}: {$item['image']}\n";
    }
}
echo "Done checking images.\n";
