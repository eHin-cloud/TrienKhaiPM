<?php
require 'core/database.php';
$stmt = $db->query("SHOW CREATE TABLE addresses");
echo $stmt->fetchColumn(1);
