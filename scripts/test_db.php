<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = \App\Database\DatabaseConnection::getInstance();
$stmt = $db->query('DESCRIBE users');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
