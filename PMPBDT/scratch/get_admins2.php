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

$stmt = $db->query("SELECT id, username, fullname, email, password, role FROM users WHERE role = 'admin'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . " - Username: " . $row['username'] . " - Fullname: " . $row['fullname'] . " - Email: " . $row['email'] . " - Hash: " . $row['password'] . "\n";
}
