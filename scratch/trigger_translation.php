<?php
session_start();
$_SESSION['lang'] = 'en';

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/lang.php';

$stmt = $db->prepare("SELECT id, specifications FROM products WHERE name LIKE '%AR75-U2%' LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Simulated Language: " . getCurrentLang() . "\n";
echo "Translating AR75-U2 specs...\n";

$translated = translate_html_content($row['specifications'], 'prod_specs_' . $row['id']);

echo "--- RESULTS ---\n";
echo $translated . "\n";
