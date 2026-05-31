<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../core/database.php';

$stmt = $db->query("DESCRIBE reviews");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($columns);
