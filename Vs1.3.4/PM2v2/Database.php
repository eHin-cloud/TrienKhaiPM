<?php
// Bật hiển thị lỗi để dễ dàng sửa lỗi khi up lên hosting 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==========================================
// KẾT NỐI CƠ SỞ DỮ LIỆU MYSQL (INFINITYFREE)
// ==========================================
$host = 'localhost';
$dbname = 'dienmay'; 
$username = 'root';  
$password = '';            

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
}

// ==========================================
// CÁC HÀM XỬ LÝ DỮ LIỆU DÙNG CHUNG
// ==========================================

function getAllCategories($db) {
    $stmt = $db->query("SELECT * FROM categories ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllBrands($db) {
    $stmt = $db->query("SELECT * FROM brands ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProductById($db, $id) {
    $stmt = $db->prepare("
        SELECT p.*, c.name AS category_name, b.name AS brand_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN brands b ON p.brand_id = b.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getRelatedProducts($db, $category_id, $exclude_id, $limit = 5) {
    $stmt = $db->prepare("
        SELECT * FROM products 
        WHERE category_id = ? AND id != ? 
        ORDER BY id DESC LIMIT ?
    ");
    $stmt->bindValue(1, $category_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $exclude_id, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSameBrandProducts($db, $brand_id, $exclude_id, $limit = 5) {
    $stmt = $db->prepare("
        SELECT * FROM products 
        WHERE brand_id = ? AND id != ? 
        ORDER BY id DESC LIMIT ?
    ");
    $stmt->bindValue(1, $brand_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $exclude_id, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProductReviews($db, $product_id) {
    try {
        $stmt = $db->prepare("
            SELECT r.*, u.fullname 
            FROM reviews r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.product_id = ? 
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return []; 
    }
}

function getReviewStats($reviews) {
    $stats = ['total' => count($reviews), 'avg' => 0, 'dist' => [1=>0, 2=>0, 3=>0, 4=>0, 5=>0]];
    if ($stats['total'] === 0) return $stats;
    $sum = 0;
    foreach ($reviews as $r) {
        $sum += $r['rating'];
        $stats['dist'][$r['rating']]++;
    }
    $stats['avg'] = round($sum / $stats['total'], 1);
    return $stats;
}

// ==========================================
// CÁC HÀM XỬ LÝ GIỎ HÀNG & ĐƠN HÀNG
// ==========================================
function addToCart($db, $user_id, $product_id, $quantity = 1) {
    $stmt = $db->prepare("SELECT cart_id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $item = $stmt->fetch();
    
    if ($item) {
        $new_qty = $item['quantity'] + $quantity;
        $db->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ?")->execute([$new_qty, $item['cart_id']]);
    } else {
        $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)")->execute([$user_id, $product_id, $quantity]);
    }
}

function getCartCount($db, $user_id) {
    $stmt = $db->prepare("SELECT SUM(quantity) FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

function getCartItems($db, $user_id) {
    $stmt = $db->prepare("
        SELECT c.cart_id, c.quantity, p.id as product_id, p.name, p.price, p.image 
        FROM cart_items c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateCartItem($db, $cart_id, $user_id, $action) {
    if ($action === 'delete') {
        $db->prepare("DELETE FROM cart_items WHERE cart_id = ? AND user_id = ?")->execute([$cart_id, $user_id]);
    } elseif ($action === 'increase') {
        $db->prepare("UPDATE cart_items SET quantity = quantity + 1 WHERE cart_id = ? AND user_id = ?")->execute([$cart_id, $user_id]);
    } elseif ($action === 'decrease') {
        $db->prepare("UPDATE cart_items SET quantity = quantity - 1 WHERE cart_id = ? AND user_id = ? AND quantity > 1")->execute([$cart_id, $user_id]);
    }
}
?>