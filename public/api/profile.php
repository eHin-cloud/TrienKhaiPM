<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/lang.php';
require_once __DIR__ . '/../../core/api.php';
require_once __DIR__ . '/../../core/jwt.php';

use App\Service\UserService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authUser = api_authenticated_user();
$userId = (int)($authUser['user_id'] ?? 0);

if ($userId <= 0) {
    api_json_response(false, 'Chưa đăng nhập.', [], 401);
}

$userService = new UserService($db);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = api_request_data();

if ($method === 'GET') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(20, (int)($_GET['limit'] ?? 5)));
    api_json_response(true, 'Lấy thông tin hồ sơ thành công.', $userService->getUserProfileData($userId, $page, $limit));
}

$action = $data['action'] ?? '';
if ($action === 'update_profile') {
    $result = $userService->handleAccountAction($data, $userId);
    api_json_response((bool)$result['success'], (string)$result['message'], [], $result['success'] ? 200 : 422);
}

if ($action === 'change_password') {
    $result = $userService->handleAccountAction($data, $userId);
    api_json_response((bool)$result['success'], (string)$result['message'], [], $result['success'] ? 200 : 422);
}

if ($action === 'enable_2fa' || $action === 'verify_2fa_enable' || $action === 'disable_2fa') {
    $result = $userService->handleAccountAction($data, $userId);
    api_json_response((bool)$result['success'], (string)$result['message'], [], $result['success'] ? 200 : 422);
}

api_json_response(false, 'Action không hợp lệ.', [], 400);
