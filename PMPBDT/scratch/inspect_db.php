<?php
require_once __DIR__ . '/../core/database.php';
echo "--- REVIEWS COLUMNS ---\n";
$stmt = $db->query("DESCRIBE reviews");
while($row = $stmt->fetch()) {
    echo $row['Field'] . "\n";
}
