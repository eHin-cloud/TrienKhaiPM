<?php

/**
 * ============================================================
 * API HELPERS - JSON RESPONSE & REQUEST BODY
 * ============================================================
 */

if (!function_exists('api_json_response')) {
    function api_json_response(bool $success, string $message = '', array $data = [], int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        if (ob_get_length()) {
            ob_clean();
        }

        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('api_request_data')) {
    function api_request_data(): array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');

        if ($method === 'GET') {
            return $_GET;
        }

        if (stripos($contentType, 'application/json') !== false) {
            $raw = $GLOBALS['RAW_PHP_INPUT'] ?? file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }
}

if (!function_exists('api_bearer_token')) {
    function api_bearer_token(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return trim((string)($_GET['token'] ?? ''));
    }
}

if (!function_exists('api_authenticated_user')) {
    function api_authenticated_user(): array|false
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = null;
        $fullname = '';
        $role = 'customer';
        $email = '';
        $isToken = false;

        $token = api_bearer_token();
        if (!empty($token)) {
            require_once __DIR__ . '/jwt.php';
            $payload = jwt_decode($token);
            if (is_array($payload) && !empty($payload['user_id'])) {
                $userId = (int) $payload['user_id'];
                $fullname = (string) ($payload['fullname'] ?? '');
                $role = (string) ($payload['role'] ?? 'customer');
                $email = (string) ($payload['email'] ?? '');
                $isToken = true;
            }
        }

        if ($userId === null && !empty($_SESSION['user_id'])) {
            $userId = (int) $_SESSION['user_id'];
            $fullname = (string) ($_SESSION['fullname'] ?? '');
            $role = (string) ($_SESSION['role'] ?? 'customer');
            $email = (string) ($_SESSION['email'] ?? '');
        }

        if ($userId !== null) {
            global $db;
            if (isset($db)) {
                try {
                    $stmt = $db->prepare('SELECT is_banned FROM users WHERE id = ? LIMIT 1');
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user && (int)($user['is_banned'] ?? 0) === 1) {
                        if (!$isToken) {
                            $_SESSION = [];
                            if (ini_get("session.use_cookies")) {
                                $params = session_get_cookie_params();
                                setcookie(session_name(), '', time() - 42000,
                                    $params["path"], $params["domain"],
                                    $params["secure"], $params["httponly"]
                                );
                            }
                            session_destroy();
                        }
                        return false;
                    }
                } catch (\Exception $e) {
                    // Fail silently
                }
            }
            return [
                'user_id' => $userId,
                'fullname' => $fullname,
                'role' => $role,
                'email' => $email,
            ];
        }

        return false;
    }
}

if (!function_exists('api_authenticated_user_strict')) {
    function api_authenticated_user_strict(): array|false
    {
        $token = api_bearer_token();
        if (empty($token)) {
            return false;
        }

        require_once __DIR__ . '/jwt.php';
        $payload = jwt_decode($token);
        if (is_array($payload) && !empty($payload['user_id'])) {
            $userId = (int) $payload['user_id'];
            global $db;
            if (isset($db)) {
                try {
                    $stmt = $db->prepare('SELECT is_banned FROM users WHERE id = ? LIMIT 1');
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user && (int)($user['is_banned'] ?? 0) === 1) {
                        return false;
                    }
                } catch (\Exception $e) {
                    // Fail silently
                }
            }
            return $payload;
        }

        return false;
    }
}
