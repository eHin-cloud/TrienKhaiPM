<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/api.php';

$raw = $GLOBALS['RAW_PHP_INPUT'] ?? file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

if (!is_array($payload)) {
    api_json_response(false, 'Invalid payload', [], 400);
}

$orderId = (int)($payload['order_id'] ?? $payload['data']['order_id'] ?? 0);
if ($orderId <= 0) {
    api_json_response(false, 'Missing order_id', [], 422);
}

$stmt = $db->prepare("UPDATE orders SET status = 'processing', note = CONCAT(IFNULL(note, ''), ' [SePay Webhook] Thanh toán xác nhận') WHERE id = ?");
$stmt->execute([$orderId]);

api_json_response(true, 'OK');
