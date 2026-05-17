<?php

function google_oauth_client_id(): string
{
    return trim((string) (getenv('GOOGLE_CLIENT_ID') ?: ($_ENV['GOOGLE_CLIENT_ID'] ?? $_SERVER['GOOGLE_CLIENT_ID'] ?? '')));
}

function google_oauth_client_secret(): string
{
    return trim((string) (getenv('GOOGLE_CLIENT_SECRET') ?: ($_ENV['GOOGLE_CLIENT_SECRET'] ?? $_SERVER['GOOGLE_CLIENT_SECRET'] ?? '')));
}

function google_oauth_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $scheme = $https ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if ($host === '') {
        throw new RuntimeException('Không xác định được host hiện tại.');
    }

    return $scheme . '://' . $host;
}

function google_oauth_detect_public_prefix(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = trim(str_replace('\\', '/', dirname($scriptName)), '/');

    if ($scriptDir === '.' || $scriptDir === '') {
        return '';
    }

    if (str_ends_with($scriptDir, '/public')) {
        return '/' . $scriptDir;
    }

    return '/' . $scriptDir . '/public';
}

function google_oauth_redirect_uri(): string
{
    // Auto-detect: xây dựng redirect URI dựa trên host hiện tại
    // để hoạt động trên cả localhost và hosting mà không cần đổi .env
    $base = google_oauth_base_url();
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    // Nếu SCRIPT_NAME đã chứa /public/index.php thì dùng luôn
    if (str_contains($scriptName, '/index.php')) {
        return $base . $scriptDir . '/index.php?route=google_callback.php';
    }

    return $base . $scriptDir . '/public/index.php?route=google_callback.php';
}

function google_oauth_authorization_url(string $state): string
{
    $clientId = google_oauth_client_id();
    if ($clientId === '') {
        throw new RuntimeException('GOOGLE_CLIENT_ID chưa được cấu hình.');
    }

    $params = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => google_oauth_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'prompt' => 'select_account',
        'access_type' => 'online',
        'include_granted_scopes' => 'true',
    ]);

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
}

function google_oauth_post_json(string $url, array $payload): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Không thể khởi tạo kết nối OAuth.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Kết nối OAuth thất bại: ' . $curlError);
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Phản hồi OAuth không hợp lệ.');
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        $message = $data['error_description'] ?? ($data['error'] ?? 'OAuth request failed');
        throw new RuntimeException($message);
    }

    return $data;
}

function google_oauth_get_json(string $url, string $bearerToken): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Không thể khởi tạo kết nối OAuth.');
    }

    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $bearerToken],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Kết nối OAuth thất bại: ' . $curlError);
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Phản hồi OAuth không hợp lệ.');
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        $message = $data['error_description'] ?? ($data['error'] ?? 'OAuth request failed');
        throw new RuntimeException($message);
    }

    return $data;
}
