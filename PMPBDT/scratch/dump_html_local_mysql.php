<?php
// Mock session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Set env variables for DatabaseConnection
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_NAME'] = 'dienmay';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = '';
putenv('DB_HOST=localhost');
putenv('DB_NAME=dienmay');
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=');

$_SERVER['REQUEST_METHOD'] = 'GET';

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/logger.php';
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/lang.php';

$_GET['p'] = 'products';
ob_start();
try {
    include __DIR__ . '/../views/admin/admin.php';
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
$html = ob_get_clean();

// Save the captured HTML to a file
file_put_contents(__DIR__ . '/rendered_admin_products.html', $html);
echo "HTML rendered and saved to scratch/rendered_admin_products.html\n";

// Let's find line 1006
$lines = explode("\n", $html);
echo "Total lines rendered: " . count($lines) . "\n";
if (isset($lines[1005])) {
    echo "Line 1006 (0-indexed 1005): " . htmlspecialchars($lines[1005]) . "\n";
} else {
    echo "Line 1006 not found\n";
}
