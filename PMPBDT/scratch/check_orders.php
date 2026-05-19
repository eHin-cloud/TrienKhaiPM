<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../core/database.php';

$stmt = $db->query("SELECT id, user_id, status FROM orders ORDER BY id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "OrderID: " . $r['id'] . " | UserID: " . $r['user_id'] . " | Status: " . $r['status'] . PHP_EOL;
}
