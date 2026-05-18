<?php
/**
 * ============================================================
 * WEBHOOK_PAYOS.PHP - XỬ LÝ WEBHOOK TỪ PAYOS
 * ============================================================
 * 
 * API endpoint để cấu hình Webhook URL trên PayOS.
 * Payload JSON từ PayOS -> Kiểm tra chữ ký (Signature) -> Cập nhật trạng thái đơn hàng.
 */

require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/payos_config.php';

header('Content-Type: application/json; charset=utf-8');

// Nhận payload POST từ PayOS
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['data']) || !isset($data['signature'])) {
    echo json_encode(['error' => 1, 'message' => 'Invalid Payload', 'success' => false]);
    exit;
}

$payosData = $data['data'];
$reqSignature = $data['signature'];

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
    echo json_encode(['error' => 1, 'message' => 'Invalid Signature', 'success' => false]);
    exit;
}

// Lấy thông tin từ payload hợp lệ
$orderCode = isset($payosData['orderCode']) ? (int)$payosData['orderCode'] : 0;
$amount = isset($payosData['amount']) ? (int)$payosData['amount'] : 0;
$desc = isset($payosData['description']) ? $payosData['description'] : '';

// Webhook từ PayOS chỉ gọi khi giao dịch THÀNH CÔNG, tiền vào tài khoản
if ($data['code'] === '00' && $data['success'] === true) {
    try {
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderCode]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            if ($order['status'] === 'pending') {
                if ($amount >= $order['total_price']) {
                    // Thanh toán đủ
                    $note = $order['note'] ? $order['note'] . " | " : "";
                    $note .= "[PayOS Webhook] Đã nhận {$amount}đ. ND: {$desc}";
                    
                    $stmtUpdate = $db->prepare("UPDATE orders SET status = 'processing', note = ? WHERE id = ?");
                    $stmtUpdate->execute([$note, $orderCode]);
                    
                    echo json_encode(['error' => 0, 'message' => 'Success', 'success' => true]);
                } else {
                    // Thanh toán thiếu
                    $note = $order['note'] ? $order['note'] . " | " : "";
                    $note .= "[PayOS CẢNH BÁO] Chỉ nhận được {$amount}đ (Thiếu). ND: {$desc}";
                    
                    $stmtUpdate = $db->prepare("UPDATE orders SET note = ? WHERE id = ?");
                    $stmtUpdate->execute([$note, $orderCode]);
                    
                    echo json_encode(['error' => 0, 'message' => 'Partial Payment Received', 'success' => true]);
                }
            } else {
                echo json_encode(['error' => 0, 'message' => 'Order already processed', 'success' => true]);
            }
        } else {
            echo json_encode(['error' => 1, 'message' => 'Order not found', 'success' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 1, 'message' => 'DB Error', 'success' => false]);
    }
} else {
    echo json_encode(['error' => 0, 'message' => 'Not a successful transaction', 'success' => true]);
}
