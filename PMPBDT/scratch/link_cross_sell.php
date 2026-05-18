<?php
require_once __DIR__ . '/../core/database.php';
// Gán phụ kiện Giá treo (ID 6) cho các Tivi (ID 1 và 2)
$db->exec("INSERT IGNORE INTO product_cross_sell (product_id, accessory_product_id) VALUES (1, 6), (2, 6)");
echo "Linked accessories to TVs.\n";
