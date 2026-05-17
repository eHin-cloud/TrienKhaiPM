<?php
require 'core/database.php';
$res = $db->query("SHOW TABLES LIKE 'notifications'");
echo $res->rowCount() > 0 ? 'exists' : 'not exists';
echo "\n";
$res2 = $db->query("SHOW TABLES LIKE 'addresses'");
echo $res2->rowCount() > 0 ? 'addresses exists' : 'addresses not exists';
