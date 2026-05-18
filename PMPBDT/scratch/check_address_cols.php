<?php
require 'core/database.php';
try {
    $stmt = $db->query("DESCRIBE addresses");
    echo "Columns in addresses table:\n";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
