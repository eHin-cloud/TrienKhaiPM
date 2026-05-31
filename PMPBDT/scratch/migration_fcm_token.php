<?php
require_once __DIR__ . '/../core/database.php';

try {
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('fcm_token', $columns)) {
        $db->exec("ALTER TABLE users ADD COLUMN fcm_token VARCHAR(255) DEFAULT NULL");
        echo "SUCCESS: Column 'fcm_token' added to 'users' table.\n";
    } else {
        echo "INFO: Column 'fcm_token' already exists in 'users' table.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
