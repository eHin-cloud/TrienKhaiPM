<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../core/database.php';

$stmt = $db->query('SHOW TABLES');
while($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo $row[0] . PHP_EOL;
}
