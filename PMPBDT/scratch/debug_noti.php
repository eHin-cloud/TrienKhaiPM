<?php
require 'core/database.php';
$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$stmt->execute([1]);
echo "Notifications for User 1: " . $stmt->fetchColumn() . "\n";

$stmt = $db->query("SELECT * FROM notifications LIMIT 5");
$items = $stmt->fetchAll();
print_r($items);
