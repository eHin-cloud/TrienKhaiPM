<?php

function totp_base32_encode(string $secret): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    $encoded = '';
    $bytes = array_values(unpack('C*', $secret));

    foreach ($bytes as $byte) {
        $binary .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
    }

    $chunks = str_split($binary, 5);
    foreach ($chunks as $chunk) {
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $encoded .= $alphabet[bindec($chunk)];
    }

    $padding = (8 - (strlen($encoded) % 8)) % 8;
    return $encoded . str_repeat('=', $padding);
}

function totp_random_base32_secret(int $length = 20): string
{
    return rtrim(totp_base32_encode(random_bytes($length)), '=');
}

function totp_generate_code(string $secret, ?int $timeSlice = null, int $digits = 6): string
{
    $timeSlice ??= (int) floor(time() / 30);
    $counter = pack('N*', 0) . pack('N*', $timeSlice);
    $secretKey = totp_base32_decode($secret);
    $hash = hash_hmac('sha1', $counter, $secretKey, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $binary = ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF);
    $otp = $binary % (10 ** $digits);
    return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
}

function totp_base32_decode(string $secret): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
    $binary = '';

    foreach (str_split($secret) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) {
            continue;
        }
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    $bytes = [];
    foreach (str_split($binary, 8) as $byte) {
        if (strlen($byte) === 8) {
            $bytes[] = chr(bindec($byte));
        }
    }

    return implode('', $bytes);
}

function totp_verify_code(string $secret, string $code, int $window = 1, int $digits = 6): bool
{
    $code = preg_replace('/\D/', '', $code);
    $currentSlice = (int) floor(time() / 30);

    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_generate_code($secret, $currentSlice + $i, $digits), $code)) {
            return true;
        }
    }

    return false;
}

function totp_provisioning_uri(string $email, string $secret, string $issuer = 'DIENMAYPRO'): string
{
    $label = rawurlencode($issuer . ':' . $email);
    $issuer = rawurlencode($issuer);
    return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
}
