<?php
// Mock session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Initialize in-memory SQLite
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Create mock tables
$db->exec("CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, icon TEXT)");
$db->exec("CREATE TABLE brands (id INTEGER PRIMARY KEY, name TEXT)");
$db->exec("CREATE TABLE products (
    id INTEGER PRIMARY KEY,
    name TEXT,
    category_id INTEGER,
    brand_id INTEGER,
    price REAL,
    old_price REAL,
    image TEXT,
    more_images TEXT,
    gift_text TEXT,
    tags TEXT,
    description TEXT,
    specifications TEXT,
    rate_star REAL DEFAULT 0.0,
    total_reviews INTEGER DEFAULT 0
)");

// Insert mock data
$db->exec("INSERT INTO categories (id, name, icon) VALUES (1, 'Điện thoại', 'fa-phone')");
$db->exec("INSERT INTO brands (id, name) VALUES (1, 'Samsung')");
$db->exec("INSERT INTO products (id, name, category_id, brand_id, price, old_price, image, more_images, gift_text, tags, description, specifications) 
VALUES (1, 'Samsung Galaxy S24', 1, 1, 20000000, 24000000, 'uploads/s24.jpg', '[\"uploads/s24_1.jpg\"]', 'Tặng ốp lưng', 'hot,new', 'Mô tả', 'Cấu hình')");

// Mock repository functions if they are loaded from core/database.php
// Let's mock CategoryRepository, BrandRepository, ProductRepository, etc. by writing dummy classes
// since the real classes in src/Repository/ will try to run SQL.
// Wait! Let's check how CategoryRepository works. Does it work with SQLite?
// Yes, standard SELECT * FROM categories works in SQLite!
// Let's include vendor autoload so namespace App\Repository works.
require_once __DIR__ . '/../vendor/autoload.php';

// Mock can function
function can($permission) {
    return true;
}

// Mock CSRF
function csrf_input_field() {
    return '<input type="hidden" name="csrf_token" value="mock_token">';
}

// Let's capture the output
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
