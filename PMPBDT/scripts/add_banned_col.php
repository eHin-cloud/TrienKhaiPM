<?php
require 'vendor/autoload.php';
$db = \App\Database\DatabaseConnection::getInstance();
try {
    $db->exec('ALTER TABLE users ADD COLUMN is_banned TINYINT(1) DEFAULT 0');
    echo "Success";
} catch(Exception $e) {
    echo $e->getMessage();
}
