<?php

require_once __DIR__ . '/../../vendor/autoload.php';

// Nạp biến môi trường từ file .env
if (class_exists('\App\Support\Env')) {
    \App\Support\Env::load(__DIR__ . '/../../.env');
}

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

        // Kiểm tra xem tài khoản có bật 2FA không
        $has2FA = (int)($user['two_factor_enabled'] ?? 0) === 1;
        if ($has2FA) {
            $otp = (string) random_int(100000, 999999);
            
            require_once __DIR__ . '/../../core/mail_helper.php';
            require_once __DIR__ . '/../../core/otp_mail_templates.php';
            $subject = 'DienMayPro - Mã OTP xác minh đăng nhập di động';
            $body = buildOtpEmailTemplate(
                'Xác minh đăng nhập di động',
                'Mã OTP bảo mật 2 lớp',
                $otp,
                'Bạn vừa thực hiện đăng nhập vào tài khoản trên ứng dụng di động DienMayPro. Vui lòng dùng mã OTP bên dưới để hoàn tất đăng nhập.'
            );
            $sent = sendEmail($user['email'], $user['fullname'] ?: 'Khách hàng', $subject, $body);
            if (!$sent) {
                api_json_response(false, 'Không thể gửi mã OTP 2FA qua email. Vui lòng thử lại sau.', [], 500);
            }

            // Tạo pending_token ngắn hạn chứa thông tin OTP (10 phút)
            $pendingPayload = [
                'pending_2fa_user_id' => (int) $user['id'],
                'pending_2fa_otp' => $otp,
                'pending_2fa_email' => $user['email'],
                'pending_2fa_expires_at' => time() + 600,
            ];
            $pendingToken = jwt_encode($pendingPayload, 600);

            api_json_response(true, 'Tài khoản yêu cầu xác minh OTP 2 lớp.', [
                'requires_2fa' => true,
                'pending_token' => $pendingToken,
            ]);
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
        break;

    case 'register':
        $result = $userService->registerUser($data);
        if (!$result['success']) {
            api_json_response(false, (string)($result['message'] ?? 'Đăng ký thất bại.'), [], 422);
        }

        api_json_response(true, (string)($result['message'] ?? 'Đăng ký thành công.'), [
            'user_id' => $result['userId'] ?? null,
        ], 201);
        break;

    case 'logout':
        session_destroy();
        api_json_response(true, 'Đăng xuất thành công.');
        break;

    case 'forgot-password-send-otp':
        $result = $userService->requestPasswordResetOtp($data);
        api_json_response((bool)$result['success'], (string)$result['message'], [], $result['success'] ? 200 : 422);
        break;

    case 'forgot-password-reset':
        $result = $userService->resetPasswordWithOtp($data);
        api_json_response((bool)$result['success'], (string)$result['message'], [], $result['success'] ? 200 : 422);
        break;

    case 'two-factor-verify':
        $code = trim((string)($data['otp_code'] ?? $data['otp'] ?? ''));
        $pendingToken = trim((string)($data['pending_token'] ?? ''));

        if ($pendingToken === '') {
            api_json_response(false, 'Phiên xác minh không hợp lệ (thiếu token).', [], 422);
        }

        $pendingPayload = jwt_decode($pendingToken);
        if (!$pendingPayload) {
            api_json_response(false, 'Phiên xác minh đã hết hạn hoặc không hợp lệ. Vui lòng đăng nhập lại.', [], 401);
        }

        $userId = (int)($pendingPayload['pending_2fa_user_id'] ?? 0);
        $expectedOtp = (string)($pendingPayload['pending_2fa_otp'] ?? '');
        $expiresAt = (int)($pendingPayload['pending_2fa_expires_at'] ?? 0);

        if ($userId <= 0 || $expectedOtp === '') {
            api_json_response(false, 'Thông tin xác minh không hợp lệ.', [], 422);
        }

        if ($expiresAt < time()) {
            api_json_response(false, 'Mã OTP đã hết hạn. Vui lòng đăng nhập lại.', [], 401);
        }

        if ($code !== $expectedOtp) {
            api_json_response(false, 'Mã xác thực OTP không đúng. Vui lòng thử lại.', [], 401);
        }

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            api_json_response(false, 'Không tìm thấy tài khoản.', [], 404);
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

        api_json_response(true, 'Xác minh 2FA thành công.', [
            'user' => $payload,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 60 * 60 * 24 * 30,
        ]);
        break;

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
