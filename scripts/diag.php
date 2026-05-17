<?php
echo "<h1>Diagnostic Script</h1>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "PHP VERSION: " . PHP_VERSION . "<br>";

require_once __DIR__ . '/vendor/autoload.php';

try {
    echo "Attempting DB connection...<br>";
    $db = \App\Database\DatabaseConnection::getInstance();
    echo "DB Connection SUCCESS!<br>";
    
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    echo "Users count: " . $stmt->fetchColumn() . "<br>";
} catch (Exception $e) {
    echo "DB Connection FAILED: " . $e->getMessage() . "<br>";
}

echo "<h2>Checking .htaccess effect</h2>";
echo "ROUTE parameter: " . ($_GET['route'] ?? 'NOT SET') . "<br>";
