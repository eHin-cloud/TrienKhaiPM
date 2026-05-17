<?php
require 'core/database.php';
echo "--- notifications ---\n";
$res = $db->query("DESCRIBE notifications");
foreach($res->fetchAll(PDO::FETCH_ASSOC) as $row) { echo $row['Field'] . ' (' . $row['Type'] . ")\n"; }
echo "--- addresses ---\n";
$res2 = $db->query("DESCRIBE addresses");
foreach($res2->fetchAll(PDO::FETCH_ASSOC) as $row) { echo $row['Field'] . ' (' . $row['Type'] . ")\n"; }
