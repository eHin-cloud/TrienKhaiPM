<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/database.php';

$items = $db->query('SELECT id, name, description, specifications, more_images FROM products')->fetchAll();

foreach ($items as $item) {
    if (stripos($item['description'], '</script>') !== false || stripos($item['specifications'], '</script>') !== false) {
        echo "Found </script> in product ID: " . $item['id'] . "\n";
    }
    if (stripos($item['name'], '</script>') !== false) {
        echo "Found </script> in product name ID: " . $item['id'] . "\n";
    }
}
echo "Checked all products for script tags.\n";
