<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');

try {
    $db = \App\Database\DatabaseConnection::getInstance();
    echo "CONNECTED SUCCESSFULLY!\n\n";
    
    $stmt = $db->query("SELECT id, username, fullname, role, phone, is_banned FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "LIST OF USERS:\n";
    echo str_pad("ID", 5) . " | " . str_pad("USERNAME", 15) . " | " . str_pad("FULLNAME", 25) . " | " . str_pad("ROLE", 10) . " | " . str_pad("PHONE", 15) . " | BANNED\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($users as $u) {
        echo str_pad($u['id'], 5) . " | " . str_pad($u['username'], 15) . " | " . str_pad($u['fullname'], 25) . " | " . str_pad($u['role'], 10) . " | " . str_pad($u['phone'], 15) . " | " . $u['is_banned'] . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
