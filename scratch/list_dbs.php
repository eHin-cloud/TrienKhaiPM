<?php
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $stmt = $pdo->query("SHOW DATABASES");
    while ($row = $stmt->fetchColumn()) {
        echo $row . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
