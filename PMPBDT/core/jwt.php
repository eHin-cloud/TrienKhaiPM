<?php

/**
 * ============================================================
 * JWT HELPERS - HMAC SHA256 TOKEN
 * ============================================================
 */

if (!function_exists('base64url_encode')) {
    function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}

if (!defined('API_JWT_SECRET')) {
    define('API_JWT_SECRET', hash('sha256', (__DIR__ . '|' . ($_ENV['APP_KEY'] ?? ($_SERVER['APP_KEY'] ?? 'pmpbdt-secret')))));
}

if (!function_exists('jwt_encode')) {
    function jwt_encode(array $payload, int $ttlSeconds = 604800): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload['iat'] = time();
        $payload['exp'] = time() + $ttlSeconds;

        $segments = [
            base64url_encode(json_encode($header, JSON_UNESCAPED_UNICODE)),
            base64url_encode(json_encode($payload, JSON_UNESCAPED_UNICODE)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), API_JWT_SECRET, true);
        $segments[] = base64url_encode($signature);
        return implode('.', $segments);
    }
}

if (!function_exists('jwt_decode')) {
    function jwt_decode(string $jwt): array|false
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $signature = base64url_decode($encodedSignature);
        $expected = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, API_JWT_SECRET, true);
        if (!hash_equals($expected, $signature)) {
            return false;
        }

        $payload = json_decode(base64url_decode($encodedPayload), true);
        if (!is_array($payload)) {
            return false;
        }

        if (!empty($payload['exp']) && time() >= (int)$payload['exp']) {
            return false;
        }

        return $payload;
    }
}
