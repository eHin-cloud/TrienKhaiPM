<?php
require_once __DIR__ . '/../core/database.php';
$stmt = $db->query('DESCRIBE wishlist');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
