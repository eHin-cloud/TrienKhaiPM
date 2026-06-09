<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../core/google_oauth.php';

try {
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;

    header('Location: ' . google_oauth_authorization_url($state));
    exit;
} catch (Throwable $e) {
    unset($_SESSION['google_oauth_state']);
    http_response_code(500);
    echo 'Google login chưa được cấu hình đúng.<br>';
    echo '<small style="color:#666;">' . htmlspecialchars($e->getMessage()) . '</small>';
    exit;
}
