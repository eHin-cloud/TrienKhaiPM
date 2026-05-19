<?php
try {
    $dsn = "mysql:host=localhost;dbname=dienmay;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $db = new PDO($dsn, 'root', '', $options);
    $stmt = $db->query("SHOW COLUMNS FROM products");
    $columns = $stmt->fetchAll();
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
} catch (PDOException $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}
