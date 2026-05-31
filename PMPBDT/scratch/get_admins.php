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

$stmt = $db->query("SELECT id, name, email, role FROM users WHERE role = 'admin'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . " - Name: " . $row['name'] . " - Email: " . $row['email'] . "\n";
}
