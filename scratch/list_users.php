<?php
require 'core/database.php';
$stmt = $db->query("SELECT id, fullname, username FROM users");
$users = $stmt->fetchAll();
foreach ($users as $u) {
    echo "ID: " . $u['id'] . " | Name: " . $u['fullname'] . " | Username: " . $u['username'] . "\n";
}
