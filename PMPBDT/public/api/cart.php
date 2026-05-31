<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/lang.php';
require_once __DIR__ . '/../../core/api.php';

use App\Repository\CartRepository;
use App\Service\CartService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authUser = api_authenticated_user();
$userId = (int)($authUser['user_id'] ?? 0);
if ($userId <= 0) {
    api_json_response(false, 'Chưa đăng nhập.', [], 401);
}

$cartService = new CartService(new CartRepository($db));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = api_request_data();
$action = $_GET['action'] ?? ($data['action'] ?? 'view');

switch ($action) {
    case 'view':
        if ($method !== 'GET') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        api_json_response(true, 'Lấy giỏ hàng thành công.', [
            'items' => $cartService->getUserCartItems($userId),
            'cart_count' => $cartService->getCartCount($userId),
        ]);
        break;

    case 'add':
        if ($method !== 'POST') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $productId = (int) ($data['product_id'] ?? $data['id'] ?? 0);
        $quantity = max(1, (int) ($data['quantity'] ?? 1));

        if ($productId <= 0) {
            api_json_response(false, 'Thiếu product_id.', [], 422);
        }

        $cartService->addProductToCart($userId, $productId, $quantity);
        api_json_response(true, 'Đã thêm sản phẩm vào giỏ hàng.', [
            'cart_count' => $cartService->getCartCount($userId),
        ]);
        break;

    case 'update':
        if ($method !== 'POST' && $method !== 'PUT') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $cartId = (int) ($data['cart_id'] ?? $data['id'] ?? 0);
        $quantity = (int) ($data['quantity'] ?? 1);

        if ($cartId <= 0 || $quantity <= 0) {
            api_json_response(false, 'Dữ liệu cập nhật không hợp lệ.', [], 422);
        }

        $stmt = $db->prepare('UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND user_id = ?');
        $stmt->execute([$quantity, $cartId, $userId]);

        api_json_response(true, 'Đã cập nhật số lượng sản phẩm.', [
            'cart_count' => $cartService->getCartCount($userId),
        ]);
        break;

    case 'delete':
        if ($method !== 'POST' && $method !== 'DELETE') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $cartId = (int) ($data['cart_id'] ?? $data['id'] ?? 0);
        if ($cartId <= 0) {
            api_json_response(false, 'Thiếu cart_id.', [], 422);
        }

        $cartService->changeItemQuantityOrRemove($cartId, $userId, 'delete');
        api_json_response(true, 'Đã xóa sản phẩm khỏi giỏ hàng.', [
            'cart_count' => $cartService->getCartCount($userId),
        ]);
        break;

    case 'increase':
    case 'decrease':
        if ($method !== 'POST') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $cartId = (int) ($data['cart_id'] ?? $data['id'] ?? 0);
        if ($cartId <= 0) {
            api_json_response(false, 'Thiếu cart_id.', [], 422);
        }

        $cartService->changeItemQuantityOrRemove($cartId, $userId, $action);
        api_json_response(true, 'Đã cập nhật giỏ hàng.', [
            'cart_count' => $cartService->getCartCount($userId),
        ]);
        break;

    case 'count':
        if ($method !== 'GET') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        api_json_response(true, 'Lấy số lượng giỏ hàng thành công.', [
            'cart_count' => $cartService->getCartCount($userId),
        ]);
        break;

    case 'clear':
        if ($method !== 'POST') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $cartRepo = new CartRepository($db);
        $cartRepo->clearCart($userId);
        api_json_response(true, 'Đã xóa toàn bộ giỏ hàng.', [
            'cart_count' => 0,
        ]);
        break;

    default:
        api_json_response(false, 'Action không hợp lệ.', [], 400);
}
