<?php
/**
 * ============================================================
 * SITEMAP GENERATOR
 * ============================================================
 * Tự động tạo sitemap.xml cho công cụ tìm kiếm (Google, Bing).
 */

header("Content-Type: application/xml; charset=utf-8");

// Lấy domain gốc của website
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
$domain = rtrim($domain, '/');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

// 1. Các trang tĩnh chính
$staticPages = [
    '',
    '/index.php',
    '/compare.php',
];

foreach ($staticPages as $page) {
    echo '<url>';
    echo '<loc>' . $domain . $page . '</loc>';
    echo '<changefreq>daily</changefreq>';
    echo '<priority>1.0</priority>';
    echo '</url>';
}

// 2. Danh mục sản phẩm
$cats = $db->query("SELECT id FROM categories")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cats as $cat) {
    echo '<url>';
    echo '<loc>' . $domain . '/index.php?cat_id=' . $cat['id'] . '</loc>';
    echo '<changefreq>weekly</changefreq>';
    echo '<priority>0.8</priority>';
    echo '</url>';
}

// 3. Chi tiết sản phẩm
$products = $db->query("SELECT id FROM products")->fetchAll(PDO::FETCH_ASSOC);
foreach ($products as $p) {
    echo '<url>';
    echo '<loc>' . $domain . '/product_detail.php?id=' . $p['id'] . '</loc>';
    echo '<changefreq>monthly</changefreq>';
    echo '<priority>0.6</priority>';
    echo '</url>';
}

echo '</urlset>';
