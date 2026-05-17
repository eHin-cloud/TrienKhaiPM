<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/lang.php';
require_once __DIR__ . '/../../core/api.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authUser = api_authenticated_user();
$userId = (int)($authUser['user_id'] ?? 0);
if ($userId <= 0) {
    api_json_response(false, 'Chưa đăng nhập.', [], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        if ($method !== 'GET') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $stmt = $db->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        api_json_response(true, 'Lấy danh sách đơn hàng thành công.', [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);

    case 'detail':
        if ($method !== 'GET') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            api_json_response(false, 'Thiếu id.', [], 422);
        }

        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            api_json_response(false, 'Không tìm thấy đơn hàng.', [], 404);
        }

        $stmt2 = $db->prepare('SELECT od.*, p.name, p.image FROM order_details od JOIN products p ON p.id = od.product_id WHERE od.order_id = ?');
        $stmt2->execute([$id]);

        api_json_response(true, 'Lấy chi tiết đơn hàng thành công.', [
            'order' => $order,
            'items' => $stmt2->fetchAll(PDO::FETCH_ASSOC),
        ]);

    case 'status':
        if ($method !== 'GET') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            api_json_response(false, 'Thiếu id.', [], 422);
        }

        $stmt = $db->prepare('SELECT status FROM orders WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $status = $stmt->fetchColumn();
        if ($status === false) {
            api_json_response(false, 'Không tìm thấy đơn hàng.', [], 404);
        }

        api_json_response(true, 'Lấy trạng thái thành công.', [
            'status' => $status,
        ]);

    default:
        api_json_response(false, 'Action không hợp lệ.', [], 400);
}
