<?php
require 'core/database.php';
$items = $db->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();
$json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
file_put_contents('scratch/test.js', 'const productsData = ' . $json . ';');
