<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/api.php';

// Log function for debugging
function debug_log($msg) {
    file_put_contents(__DIR__ . '/../../scratch/api_debug.log', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

$authUser = api_authenticated_user();
$userId = (int) ($authUser['user_id'] ?? 0);

if ($userId <= 0) {
    debug_log("Unauthorized access attempt.");
    api_json_response(false, 'Vui lòng đăng nhập.', [], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = api_request_data();
$action = $_GET['action'] ?? ($data['action'] ?? 'list');

switch ($action) {
    case 'list':
        try {
            $stmt = $db->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC');
            $stmt->execute([$userId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as &$item) {
                $item['id'] = (int) $item['id'];
                $item['is_default'] = (bool) $item['is_default'];
            }
            
            api_json_response(true, 'Lấy danh sách địa chỉ thành công.', $items);
        } catch (Exception $e) {
            api_json_response(false, 'Lỗi database: ' . $e->getMessage());
        }
        break;

    case 'add':
        if ($method !== 'POST') api_json_response(false, 'Method not allowed.', [], 405);
        
        $fullname = trim($data['fullname'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $address = trim($data['address'] ?? '');
        $isDefault = (int) ($data['is_default'] ?? 0);

        if (empty($fullname) || empty($phone) || empty($address)) {
            api_json_response(false, 'Vui lòng nhập đầy đủ thông tin.', [], 422);
        }

        try {
            if ($isDefault === 1) {
                $db->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$userId]);
            }

            $stmt = $db->prepare('INSERT INTO addresses (user_id, fullname, phone, address, is_default) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $fullname, $phone, $address, $isDefault]);
            
            api_json_response(true, 'Thêm địa chỉ thành công.', ['id' => $db->lastInsertId()]);
        } catch (Exception $e) {
            api_json_response(false, 'Lỗi lưu địa chỉ: ' . $e->getMessage());
        }
        break;

    case 'update':
        if ($method !== 'POST') api_json_response(false, 'Method not allowed.', [], 405);
        
        $id = (int) ($data['id'] ?? 0);
        $fullname = trim($data['fullname'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $address = trim($data['address'] ?? '');
        $isDefault = (int) ($data['is_default'] ?? 0);

        if ($id <= 0) api_json_response(false, 'Thiếu ID địa chỉ.', [], 422);

        try {
            if ($isDefault === 1) {
                $db->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$userId]);
            }

            $stmt = $db->prepare('UPDATE addresses SET fullname = ?, phone = ?, address = ?, is_default = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$fullname, $phone, $address, $isDefault, $id, $userId]);
            
            api_json_response(true, 'Cập nhật địa chỉ thành công.');
        } catch (Exception $e) {
            api_json_response(false, 'Lỗi cập nhật: ' . $e->getMessage());
        }
        break;

    case 'delete':
        if ($method !== 'POST') api_json_response(false, 'Method not allowed.', [], 405);
        $id = (int) ($data['id'] ?? 0);
        
        try {
            $stmt = $db->prepare('DELETE FROM addresses WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
            api_json_response(true, 'Xóa địa chỉ thành công.');
        } catch (Exception $e) {
            api_json_response(false, 'Lỗi xóa: ' . $e->getMessage());
        }
        break;

    case 'set_default':
        if ($method !== 'POST') api_json_response(false, 'Method not allowed.', [], 405);
        $id = (int) ($data['id'] ?? 0);
        
        try {
            $db->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$userId]);
            $stmt = $db->prepare('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
            api_json_response(true, 'Đã đặt làm địa chỉ mặc định.');
        } catch (Exception $e) {
            api_json_response(false, 'Lỗi đặt mặc định: ' . $e->getMessage());
        }
        break;

    default:
        api_json_response(false, 'Action không hợp lệ.', [], 400);
}
