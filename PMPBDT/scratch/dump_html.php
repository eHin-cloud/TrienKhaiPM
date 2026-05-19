<?php
// Mock environment
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../core/logger.php';
require_once __DIR__ . '/../vendor/autoload.php';
\App\Support\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/lang.php';

$_GET['p'] = 'products';

// Let's capture the output
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

// Let's print line 1006
$lines = explode("\n", $html);
echo "Total lines rendered: " . count($lines) . "\n";
if (isset($lines[1005])) { // 0-indexed, so 1005 is line 1006
    echo "Line 1006 (0-indexed 1005): " . htmlspecialchars($lines[1005]) . "\n";
    echo "Length of line 1006: " . strlen($lines[1005]) . "\n";
} else {
    echo "Line 1006 not found\n";
}
