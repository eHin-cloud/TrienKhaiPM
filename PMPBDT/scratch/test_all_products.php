<?php
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_NAME'] = 'dienmay';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = '';
putenv('DB_HOST=localhost');
putenv('DB_NAME=dienmay');
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/database.php';

$items = $db->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();
$json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$js = "const productsData = " . $json . ";\nconsole.log('Successfully parsed productsData!');";
file_put_contents('scratch/test_all_products.js', $js);
