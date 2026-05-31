<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');

try {
    $db = \App\Database\DatabaseConnection::getInstance();
    
    // In cấu trúc bảng users
    $createTable = $db->query("SHOW CREATE TABLE users")->fetch();
    echo "CREATE TABLE USERS:\n";
    echo $createTable['Create Table'] . "\n\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
