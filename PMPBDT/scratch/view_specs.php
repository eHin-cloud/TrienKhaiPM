<?php
require_once __DIR__ . '/../core/database.php';
$stmt = $db->prepare("SELECT specifications FROM products WHERE name LIKE '%AR75-U2%' LIMIT 1");
$stmt->execute();
$specs = $stmt->fetchColumn();
echo "--- ORIGINAL VIETNAMESE SPECS ---\n";
echo $specs . "\n";
