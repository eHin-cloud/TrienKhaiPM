<?php
// ==========================================
// KẾT NỐI CƠ SỞ DỮ LIỆU MYSQL
// ==========================================
$host = 'localhost';
$dbname = 'dienmay'; 
$username = 'root';  
$password = '';      

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
}

// ==========================================
// CÁC HÀM XỬ LÝ DỮ LIỆU DÙNG CHUNG
// ==========================================

// Hàm lấy tất cả danh mục
function getAllCategories($db) {
    $stmt = $db->query("SELECT * FROM categories ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hàm lấy tất cả thương hiệu
function getAllBrands($db) {
    $stmt = $db->query("SELECT * FROM brands ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hàm lấy chi tiết 1 sản phẩm kèm tên danh mục & thương hiệu
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

// Hàm lấy các sản phẩm cùng danh mục
function getRelatedProducts($db, $category_id, $exclude_id, $limit = 5) {
    $stmt = $db->prepare("
        SELECT * FROM products 
        WHERE category_id = ? AND id != ? 
        ORDER BY id DESC LIMIT ?
    ");
    // Ràng buộc kiểu dữ liệu số nguyên cho Limit
    $stmt->bindValue(1, $category_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $exclude_id, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hàm lấy các sản phẩm cùng thương hiệu
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

// Hàm lấy đánh giá của sản phẩm
function getProductReviews($db, $product_id) {
    // Kiểm tra bảng reviews có tồn tại không trước khi truy vấn (Tránh lỗi nếu bạn chưa chạy SQL)
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
        return []; // Trả về mảng rỗng nếu chưa có bảng reviews
    }
}
// ==========================================
// CÁC HÀM XỬ LÝ GIỎ HÀNG (CART)
// ==========================================

function addToCart($db, $user_id, $product_id) {
    // Kiểm tra sản phẩm tồn tại
    $stmt = $db->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    if (!$stmt->fetch()) return false;

    // Kiểm tra đã có trong giỏ chưa
    $checkCart = $db->prepare("SELECT id FROM cart_items WHERE user_id = ? AND product_id = ?");
    $checkCart->execute([$user_id, $product_id]);
    $item = $checkCart->fetch();

    if ($item) {
        $db->prepare("UPDATE cart_items SET quantity = quantity + 1 WHERE id = ?")->execute([$item['id']]);
    } else {
        $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, 1)")->execute([$user_id, $product_id]);
    }
    return true;
}

function getCartItems($db, $user_id) {
    $stmt = $db->prepare("
        SELECT c.id as cart_id, c.quantity, p.id as product_id, p.name, p.price, p.image 
        FROM cart_items c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateCartItem($db, $cart_id, $user_id, $action) {
    if ($action === 'delete') {
        $db->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?")->execute([$cart_id, $user_id]);
        return;
    }
    
    $stmt = $db->prepare("SELECT quantity FROM cart_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$cart_id, $user_id]);
    $item = $stmt->fetch();
    
    if ($item) {
        $new_qty = $item['quantity'];
        if ($action === 'increase') $new_qty++;
        elseif ($action === 'decrease') $new_qty--;

        if ($new_qty > 0) {
            $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?")->execute([$new_qty, $cart_id]);
        } else {
            $db->prepare("DELETE FROM cart_items WHERE id = ?")->execute([$cart_id]);
        }
    }
}

function getCartCount($db, $user_id) {
    $stmt = $db->prepare("SELECT SUM(quantity) FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 0;
}
?>