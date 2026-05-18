<?php
require 'core/database.php';
$stmt = $db->prepare("SELECT id, fullname, username FROM users WHERE id = ?");
$stmt->execute([1]);
$user = $stmt->fetch();
echo "ID: " . $user['id'] . "\n";
echo "Fullname: " . $user['fullname'] . "\n";
echo "Username: " . $user['username'] . "\n";
