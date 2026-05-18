<?php
ob_start();
error_reporting(0);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/api.php';
require_once __DIR__ . '/../../core/lang.php';

$authUser = api_authenticated_user();
$userId = (int) ($authUser['user_id'] ?? 0);

if ($userId <= 0) {
    api_json_response(false, __('wishlist_login_required'), [], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = api_request_data();
$action = $_GET['action'] ?? ($data['action'] ?? 'list');

switch ($action) {
    case 'list':
        $stmt = $db->prepare('
            SELECT p.*, c.name as category_name, b.name as brand_name 
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC
        ');
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format data
        foreach ($items as &$item) {
            $item['id'] = (int) $item['id'];
            $item['price'] = (float) $item['price'];
            $item['old_price'] = (float) $item['old_price'];
            $item['image'] = $item['image'] ? (strpos($item['image'], 'http') === 0 ? $item['image'] : '/public/assets/images/products/' . $item['image']) : null;
        }

        api_json_response(true, 'Lấy danh sách yêu thích thành công.', $items);

    case 'toggle':
        if ($method !== 'POST') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $productId = (int) ($data['product_id'] ?? 0);
        if ($productId <= 0) {
            api_json_response(false, 'Thiếu product_id.', [], 422);
        }

        // Kiểm tra xem đã có trong wishlist chưa
        $stmt = $db->prepare('SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$userId, $productId]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Nếu đã có thì xóa
            $stmt = $db->prepare('DELETE FROM wishlist WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$userId, $productId]);
            api_json_response(true, __('wishlist_removed'), ['in_wishlist' => false]);
        } else {
            // Nếu chưa có thì thêm
            $stmt = $db->prepare('INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)');
            $stmt->execute([$userId, $productId]);
            api_json_response(true, __('wishlist_added'), ['in_wishlist' => true]);
        }

    case 'check':
        $productId = (int) ($_GET['product_id'] ?? 0);
        $stmt = $db->prepare('SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$userId, $productId]);
        $inWishlist = (bool) $stmt->fetch();
        
        api_json_response(true, 'Kiểm tra trạng thái yêu thích.', ['in_wishlist' => $inWishlist]);

    case 'clear':
        if ($method !== 'POST') {
            api_json_response(false, __('invalid_method'), [], 405);
        }
        try {
            $stmt = $db->prepare('DELETE FROM wishlist WHERE user_id = ?');
            $stmt->execute([$userId]);
            api_json_response(true, __('wishlist_cleared_success'));
        } catch (Exception $e) {
            api_json_response(false, __('error') . ': ' . $e->getMessage());
        }

    default:
        api_json_response(false, 'Hành động không hợp lệ.', [], 400);
}
