<?php
ob_start();
error_reporting(0);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/api.php';

$authUser = api_authenticated_user();
$userId = (int) ($authUser['user_id'] ?? 0);

if ($userId <= 0) {
    api_json_response(false, 'Vui lòng đăng nhập.', [], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = api_request_data();
$action = $_GET['action'] ?? ($data['action'] ?? 'list');

switch ($action) {
    case 'list':
        $stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as &$item) {
            $item['id'] = (int) $item['id'];
            $item['is_read'] = (bool) $item['is_read'];
        }
        
        // Đếm số thông báo chưa đọc
        $unreadStmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $unreadStmt->execute([$userId]);
        $unreadCount = (int)$unreadStmt->fetchColumn();

        api_json_response(true, 'Lấy danh sách thông báo thành công.', [
            'items' => $items,
            'unread_count' => $unreadCount
        ]);

    case 'read':
        if ($method !== 'POST') api_json_response(false, 'Method not allowed.', [], 405);
        $id = (int) ($data['id'] ?? 0);
        
        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        
        api_json_response(true, 'Đã đánh dấu đã đọc.');

    case 'read_all':
        if ($method !== 'POST') api_json_response(false, 'Method not allowed.', [], 405);
        
        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$userId]);
        
        api_json_response(true, 'Đã đánh dấu tất cả là đã đọc.');

    default:
        api_json_response(false, 'Action không hợp lệ.', [], 400);
}
