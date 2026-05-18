<?php
require_once __DIR__ . '/../core/database.php';
try {
    $db->exec("ALTER TABLE installment_requests ADD COLUMN admin_note TEXT NULL AFTER status");
    echo "Successfully added 'admin_note' column to installment_requests table!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
