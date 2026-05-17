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

// Chống lỗi 403: API cho Mobile App không sử dụng CSRF token
define('SKIP_CSRF_CHECK', true);

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$userService = new UserService($db);
$data = api_request_data();

switch ($action) {
    case 'login':
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($username === '' || $password === '') {
            api_json_response(false, 'Vui lòng nhập đầy đủ thông tin đăng nhập.', [], 422);
        }

        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, (string)($user['password'] ?? ''))) {
            // Ghi nhận thất bại
            $failUserId = ($user && !empty($user['id'])) ? (int)$user['id'] : 0;
            record_login_history($db, $failUserId, 'failed');
            api_json_response(false, 'Tên đăng nhập hoặc mật khẩu không đúng.', [], 401);
        }

        if (!empty($user['is_banned'])) {
            api_json_response(false, 'Tài khoản của bạn đang bị khóa.', [], 403);
        }

        $payload = [
            'user_id' => (int) $user['id'],
            'fullname' => (string) $user['fullname'],
            'username' => (string) $user['username'],
            'role' => (string) $user['role'],
            'email' => (string) ($user['email'] ?? ''),
        ];

        $token = jwt_encode($payload, 60 * 60 * 24 * 30);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'];

        record_login_history($db, $user['id'], 'success');

        api_json_response(true, 'Đăng nhập thành công.', [
            'user' => $payload,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 60 * 60 * 24 * 30,
        ]);

    case 'register':
        $result = $userService->registerUser($data);
        if (!$result['success']) {
            api_json_response(false, (string)($result['message'] ?? 'Đăng ký thất bại.'), [], 422);
        }

        api_json_response(true, (string)($result['message'] ?? 'Đăng ký thành công.'), [
            'user_id' => $result['userId'] ?? null,
        ], 201);

    case 'logout':
        session_destroy();
        api_json_response(true, 'Đăng xuất thành công.');

    case 'forgot-password-send-otp':
        $result = $userService->requestPasswordResetOtp($data);
        api_json_response((bool)$result['success'], (string)$result['message'], [], $result['success'] ? 200 : 422);

    case 'forgot-password-reset':
        $result = $userService->resetPasswordWithOtp($data);
        api_json_response((bool)$result['success'], (string)$result['message'], [], $result['success'] ? 200 : 422);

    case 'two-factor-verify':
        $code = trim((string)($data['otp_code'] ?? $data['otp'] ?? ''));
        $userId = (int)($_SESSION['pending_2fa_user_id'] ?? 0);

        if ($userId <= 0) {
            api_json_response(false, 'Phiên xác minh không hợp lệ.', [], 422);
        }

        $result = $userService->verifyTwoFactorEnrollment($userId, $code);
        if (!$result['success']) {
            api_json_response(false, (string)$result['message'], [], 422);
        }

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            api_json_response(false, 'Không tìm thấy tài khoản.', [], 404);
        }

        $payload = [
            'user_id' => (int) $user['id'],
            'fullname' => (string) $user['fullname'],
            'username' => (string) $user['username'],
            'role' => (string) $user['role'],
            'email' => (string) ($user['email'] ?? ''),
        ];
        $token = jwt_encode($payload, 60 * 60 * 24 * 30);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'];
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_username'], $_SESSION['pending_2fa_name'], $_SESSION['pending_2fa_role'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_otp'], $_SESSION['pending_2fa_expires_at'], $_SESSION['pending_2fa_attempts'], $_SESSION['pending_2fa_started_at']);

        api_json_response(true, 'Xác minh 2FA thành công.', [
            'user' => $payload,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 60 * 60 * 24 * 30,
        ]);

    case 'me':
        $token = api_bearer_token();
        $payload = $token ? jwt_decode($token) : false;
        if (!$payload) {
            api_json_response(false, 'Token không hợp lệ hoặc đã hết hạn.', [], 401);
        }

        api_json_response(true, 'Lấy thông tin phiên đăng nhập thành công.', [
            'user' => $payload,
        ]);

    default:
        api_json_response(false, 'Action không hợp lệ.', [], 400);
}
