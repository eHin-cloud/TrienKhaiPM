<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../core/google_oauth.php';
require_once __DIR__ . '/../../core/database.php';

$state = (string) ($_GET['state'] ?? '');
$code = (string) ($_GET['code'] ?? '');
$error = (string) ($_GET['error'] ?? '');

function google_oauth_debug_response(string $message, ?Throwable $e = null): void
{
    http_response_code(500);
    echo '<pre style="white-space:pre-wrap;font-family:ui-monospace,Menlo,Monaco,Consolas,monospace;padding:16px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;">';
    echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    if ($e) {
        echo "\n\nException: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo "\nFile: " . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        echo "\nLine: " . htmlspecialchars((string) $e->getLine(), ENT_QUOTES, 'UTF-8');
    }
    echo '</pre>';
    exit;
}

if ($error !== '') {
    unset($_SESSION['google_oauth_state']);
    google_oauth_debug_response('Google OAuth returned error=' . $error . (!empty($_GET['error_description']) ? (' | description=' . (string) $_GET['error_description']) : ''));
}

if ($state === '' || $code === '' || !hash_equals($_SESSION['google_oauth_state'] ?? '', $state)) {
    unset($_SESSION['google_oauth_state']);
    google_oauth_debug_response('Phiên đăng nhập Google không hợp lệ. state=' . $state . ' code=' . ($code !== '' ? 'present' : 'missing') . ' expected_state=' . (string) ($_SESSION['google_oauth_state'] ?? ''));
}

try {
    $schemaStmt = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME IN ('google_id', 'auth_provider', 'email', 'phone')");
    $schemaStmt->execute();
    $existingColumns = $schemaStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach (['email', 'google_id', 'auth_provider'] as $requiredColumn) {
        if (!in_array($requiredColumn, $existingColumns, true)) {
            google_oauth_debug_response('Cơ sở dữ liệu hiện tại chưa đúng schema cho Google login. Bảng `users` cần có cột `' . $requiredColumn . '`. Vui lòng chạy migration `sql/add_google_id_to_users.sql` trước khi dùng đăng nhập Google.');
        }
    }

    $token = google_oauth_post_json('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => google_oauth_client_id(),
        'client_secret' => google_oauth_client_secret(),
        'redirect_uri' => google_oauth_redirect_uri(),
        'grant_type' => 'authorization_code',
    ]);

    if (empty($token['access_token'])) {
        throw new RuntimeException('Không nhận được access token. Token response: ' . json_encode($token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $googleUser = google_oauth_get_json('https://openidconnect.googleapis.com/v1/userinfo', $token['access_token']);
    $googleId = (string) ($googleUser['sub'] ?? '');
    $email = (string) ($googleUser['email'] ?? '');
    $emailVerified = filter_var($googleUser['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $fullname = trim((string) ($googleUser['name'] ?? ''));

    if ($googleId === '' || $email === '') {
        throw new RuntimeException('Không lấy được thông tin tài khoản Google.');
    }

    if (!$emailVerified) {
        throw new RuntimeException('Email Google chưa được xác minh.');
    }

    $stmt = $db->prepare('SELECT * FROM users WHERE auth_provider = ? AND google_id = ? LIMIT 1');
    $stmt->execute(['google', $googleId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        $usernameBase = strstr($email, '@', true) ?: 'google';
        $username = $usernameBase;
        $i = 1;

        $checkUsername = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        while (true) {
            $checkUsername->execute([$username]);
            if ((int) $checkUsername->fetchColumn() === 0) {
                break;
            }
            $username = $usernameBase . $i;
            $i++;
        }

        $phone = null;

        $insert = $db->prepare("INSERT INTO users (fullname, phone, username, email, google_id, auth_provider, role, password) VALUES (?, ?, ?, ?, ?, 'google', 'customer', '')");
        $insert->execute([$fullname !== '' ? $fullname : $username, $phone, $username, $email, $googleId]);

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([(int) $db->lastInsertId()]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        if (($user['auth_provider'] ?? 'local') !== 'google') {
            $updateProvider = $db->prepare('UPDATE users SET auth_provider = ?, google_id = ? WHERE id = ?');
            $updateProvider->execute(['google', $googleId, $user['id']]);
            $user['auth_provider'] = 'google';
            $user['google_id'] = $googleId;
        }

        if (empty($user['google_id'])) {
            $update = $db->prepare('UPDATE users SET google_id = ? WHERE id = ?');
            $update->execute([$googleId, $user['id']]);
            $user['google_id'] = $googleId;
        }

        // Cho phép trường phone nhận giá trị NULL đối với tài khoản Google, không tự động sinh số điện thoại giả dạng google-
    }

    if (!$user) {
        throw new RuntimeException('Không tạo được tài khoản người dùng.');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['auth_provider'] = $user['auth_provider'] ?? 'local';
    unset($_SESSION['google_oauth_state']);

    // Ghi lại lịch sử đăng nhập thành công qua Google
    require_once __DIR__ . '/../../core/security.php';
    record_login_history($db, $user['id'], 'success');

    header('Location: index.php');
    exit;
} catch (Throwable $e) {
    unset($_SESSION['google_oauth_state']);
    google_oauth_debug_response('Đăng nhập Google thất bại.', $e);
}
