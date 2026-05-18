<?php
require 'core/database.php';
$stmt = $db->query("DESCRIBE products");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
