<?php
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../core/database.php';

try {
    $db->exec("ALTER TABLE reviews ADD COLUMN parent_id INT DEFAULT NULL AFTER id");
    echo "Added parent_id column to reviews table successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
