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

if ($method !== 'POST') {
    api_json_response(false, 'Method not allowed.', [], 405);
}

$fcmToken = trim($data['fcm_token'] ?? '');

if (empty($fcmToken)) {
    api_json_response(false, 'FCM Token không được để trống.', [], 400);
}

try {
    $stmt = $db->prepare('UPDATE users SET fcm_token = ? WHERE id = ?');
    $stmt->execute([$fcmToken, $userId]);
    
    api_json_response(true, 'Cập nhật FCM Token thành công.', [
        'fcm_token' => $fcmToken
    ]);
} catch (Exception $e) {
    api_json_response(false, 'Lỗi cơ sở dữ liệu: ' . $e->getMessage(), [], 500);
}
