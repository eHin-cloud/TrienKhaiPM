<?php
ob_start();
error_reporting(0);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/lang.php';
require_once __DIR__ . '/../../core/api.php';
require_once __DIR__ . '/../../core/payos_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authUser = api_authenticated_user();
$userId = (int) ($authUser['user_id'] ?? 0);
if ($userId <= 0) {
    api_json_response(false, 'Chưa đăng nhập.', [], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = api_request_data();
$action = $_GET['action'] ?? ($data['action'] ?? 'details');

switch ($action) {
    case 'details':
        if ($method !== 'GET') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $orderId = (int) ($_GET['order_id'] ?? 0);
        if ($orderId <= 0) {
            api_json_response(false, 'Thiếu order_id.', [], 422);
        }

        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            api_json_response(false, 'Không tìm thấy đơn hàng.', [], 404);
        }

        $bankId = 'MB';
        $accountNo = '31220066649668';
        $accountName = 'NGUYEN ANH QUY';
        $amount = (float) $order['total_price'];
        $addInfo = 'DMPRO' . $orderId;
        $qrUrl = "https://img.vietqr.io/image/{$bankId}-{$accountNo}-compact2.png?amount={$amount}&addInfo=" . urlencode($addInfo) . '&accountName=' . urlencode($accountName);

        api_json_response(true, 'Lấy thông tin thanh toán thành công.', [
            'order' => $order,
            'bank' => [
                'bank_id' => $bankId,
                'account_no' => $accountNo,
                'account_name' => $accountName,
            ],
            'amount' => $amount,
            'transfer_content' => $addInfo,
            'qr_url' => $qrUrl,
            'payos_enabled' => (defined('PAYOS_CLIENT_ID') && PAYOS_CLIENT_ID !== 'YOUR_CLIENT_ID_HERE'),
        ]);

    case 'confirm_manual':
        if ($method !== 'POST') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $orderId = (int) ($data['order_id'] ?? 0);
        if ($orderId <= 0) {
            api_json_response(false, 'Thiếu order_id.', [], 422);
        }

        $stmt = $db->prepare('UPDATE orders SET status = "processing", note = CONCAT(IFNULL(note, ""), " [Hệ thống ghi nhận khách bấm xác nhận thủ công]") WHERE id = ? AND user_id = ?');
        $stmt->execute([$orderId, $userId]);

        api_json_response(true, 'Đã xác nhận thanh toán thủ công.', [
            'order_id' => $orderId,
        ]);

    case 'status':
        if ($method !== 'GET') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $orderId = (int) ($_GET['order_id'] ?? 0);
        if ($orderId <= 0) {
            api_json_response(false, 'Thiếu order_id.', [], 422);
        }

        $stmt = $db->prepare('SELECT status FROM orders WHERE id = ? AND user_id = ?');
        $stmt->execute([$orderId, $userId]);
        $status = $stmt->fetchColumn();
        if ($status === false) {
            api_json_response(false, 'Không tìm thấy đơn hàng.', [], 404);
        }

        api_json_response(true, 'Lấy trạng thái đơn hàng thành công.', [
            'status' => $status,
        ]);

    case 'payos_create':
        if ($method !== 'POST') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        if (PAYOS_CLIENT_ID === 'YOUR_CLIENT_ID_HERE') {
            api_json_response(false, 'PayOS chưa được cấu hình.', [], 422);
        }

        $orderId = (int) ($data['order_id'] ?? 0);
        if ($orderId <= 0) {
            api_json_response(false, 'Thiếu order_id.', [], 422);
        }

        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            api_json_response(false, 'Không tìm thấy đơn hàng.', [], 404);
        }

        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        $returnUrl = $baseUrl . '/payment.php?action=details&order_id=' . $orderId . '&payos_success=1';
        $cancelUrl = $baseUrl . '/payment.php?action=details&order_id=' . $orderId . '&cancel=1';
        $amount = (int) $order['total_price'];
        $payload = [
            'orderCode' => $orderId,
            'amount' => $amount,
            'description' => substr('DMPRO' . $orderId, 0, 25),
            'returnUrl' => $returnUrl,
            'cancelUrl' => $cancelUrl,
        ];

        ksort($payload);
        $signParts = [];
        foreach ($payload as $key => $value) {
            if ($value === '' || $value === null || is_array($value)) {
                continue;
            }
            $signParts[] = $key . '=' . $value;
        }
        $payload['signature'] = hash_hmac('sha256', implode('&', $signParts), PAYOS_CHECKSUM_KEY);

        $response = null;
        if (function_exists('curl_init')) {
            $ch = curl_init('https://api-merchant.payos.vn/v2/payment-requests');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-client-id: ' . PAYOS_CLIENT_ID,
                'x-api-key: ' . PAYOS_API_KEY,
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $response = curl_exec($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", [
                        'Content-Type: application/json',
                        'x-client-id: ' . PAYOS_CLIENT_ID,
                        'x-api-key: ' . PAYOS_API_KEY,
                    ]),
                    'content' => json_encode($payload),
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $response = @file_get_contents('https://api-merchant.payos.vn/v2/payment-requests', false, $context);
        }

        // Log phản hồi để debug nếu cần
        // file_put_contents(__DIR__ . '/payos_debug.log', "[" . date('Y-m-d H:i:s') . "] Response: " . (string)$response . PHP_EOL, FILE_APPEND);

        $decoded = json_decode((string) $response, true);
        
        if (!is_array($decoded)) {
            api_json_response(false, 'Server PayOS không phản hồi hoặc phản hồi không hợp lệ.', ['raw' => (string)$response], 500);
        }

        if (($decoded['code'] ?? '') !== '00' || empty($decoded['data']['checkoutUrl'])) {
            api_json_response(false, $decoded['desc'] ?? 'Không tạo được link thanh toán PayOS.', $decoded, 500);
        }

        api_json_response(true, 'Tạo link thanh toán PayOS thành công.', [
            'checkout_url' => $decoded['data']['checkoutUrl'],
        ]);

    default:
        api_json_response(false, 'Action không hợp lệ.', [], 400);
}
