<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/api.php';
require_once __DIR__ . '/../../core/payos_config.php';

$raw = $GLOBALS['RAW_PHP_INPUT'] ?? file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

if (!is_array($payload) || !isset($payload['data']) || !isset($payload['signature'])) {
    api_json_response(false, 'Invalid payload structure', [], 400);
}

$payosData = $payload['data'];
$reqSignature = $payload['signature'];

// Tạo mảng để tính chữ ký xác thực Webhook
ksort($payosData);
$signData = [];
foreach ($payosData as $key => $value) {
    if ($value === '' || $value === null || is_array($value)) continue;
    $signData[] = $key . '=' . $value;
}
$signString = implode('&', $signData);
$computedSignature = hash_hmac('sha256', $signString, PAYOS_CHECKSUM_KEY);

if ($computedSignature !== $reqSignature) {
    api_json_response(false, 'Invalid signature', [], 401);
}

$orderId = (int)($payosData['orderCode'] ?? 0);
$amount = (int)($payosData['amount'] ?? 0);
$desc = (string)($payosData['description'] ?? '');

if ($orderId <= 0) {
    api_json_response(false, 'Missing orderCode', [], 422);
}

// Lấy thông tin đơn hàng hiện tại
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    api_json_response(false, 'Order not found', [], 404);
}

if ($order['status'] !== 'pending') {
    api_json_response(true, 'Order already processed', [
        'status' => $order['status']
    ]);
}

if ($amount >= $order['total_price']) {
    // Thanh toán đủ
    $note = $order['note'] ? $order['note'] . " | " : "";
    $note .= "[PayOS API Webhook] Đã nhận {$amount}đ. ND: {$desc}";
    
    $stmtUpdate = $db->prepare("UPDATE orders SET status = 'processing', note = ? WHERE id = ?");
    $stmtUpdate->execute([$note, $orderId]);
    
    api_json_response(true, 'Payment confirmed successfully', [
        'order_id' => $orderId,
        'status' => 'processing'
    ]);
} else {
    // Thanh toán thiếu
    $note = $order['note'] ? $order['note'] . " | " : "";
    $note .= "[PayOS API CẢNH BÁO] Nhận được {$amount}đ (Thiếu). ND: {$desc}";
    
    $stmtUpdate = $db->prepare("UPDATE orders SET note = ? WHERE id = ?");
    $stmtUpdate->execute([$note, $orderId]);
    
    api_json_response(true, 'Partial payment received', [
        'order_id' => $orderId,
        'status' => 'pending'
    ]);
}
