<?php
require_once __DIR__ . '/../vendor/autoload.php';
$db = \App\Database\DatabaseConnection::getInstance();
$stmt = $db->query("DESCRIBE reviews");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
