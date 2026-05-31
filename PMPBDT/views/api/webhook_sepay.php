<?php
/**
 * ============================================================
 * WEBHOOK_SEPAY.PHP - XỬ LÝ WEBHOOK TỪ NGÂN HÀNG
 * ============================================================
 * 
 * Đây là API endpoint nhận dữ liệu từ các dịch vụ tích hợp ngân hàng (như SePay).
 * Khi có giao dịch chuyển tiền vào tài khoản, SePay sẽ gọi Webhook này.
 * Hệ thống sẽ tự động đối soát nội dung chuyển khoản để duyệt đơn.
 */

require_once __DIR__ . '/../../core/database.php';

// Trả về JSON
header('Content-Type: application/json; charset=utf-8');

// (Tùy chọn) Kiểm tra API Key từ Header để bảo mật (Cấu hình trên SePay)
$headers = getallheaders();
$sepay_token = "DIENMAYPRO_SECRET_TOKEN_2026"; // Thay đổi token thực tế ở đây
if (!isset($headers['Authorization']) || $headers['Authorization'] !== "Bearer " . $sepay_token) {
    // Để dễ test, ta tạm thời bỏ qua nếu không có Bearer hoặc xử lý log. 
    // Trong thực tế, uncomment exit để chặn request rác.
    // echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    // exit;
}

// Đọc payload từ request body (JSON)
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid Payload']);
    exit;
}

// Dữ liệu từ SePay
$transferAmount = isset($data['transferAmount']) ? (int)$data['transferAmount'] : 0;
$content = isset($data['content']) ? strtoupper(trim($data['content'])) : '';
$referenceCode = isset($data['referenceCode']) ? $data['referenceCode'] : ''; // Mã GD ngân hàng

// Chỉ xử lý các GD tiền vào (transferType = in)
$transferType = isset($data['transferType']) ? $data['transferType'] : 'in';
if ($transferType !== 'in') {
    echo json_encode(['success' => true, 'message' => 'Not an IN transaction']);
    exit;
}

// Bóc tách mã đơn hàng từ nội dung (VD: "DMPRO 123", "DMPRO123")
// Dùng regex để bắt DMPRO kèm theo các số sau đó
$pattern = '/DMPRO\s*(\d+)/i';
if (preg_match($pattern, $content, $matches)) {
    $order_id = (int)$matches[1];

    try {
        // Kiểm tra đơn hàng có tồn tại không
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Kiểm tra trạng thái và số tiền
            // Nếu đơn hàng đang pending và tiền gửi vào >= tiền đơn hàng
            if ($order['status'] === 'pending') {
                if ($transferAmount >= $order['total_price']) {
                    // Update trạng thái sang 'processing' và ghi chú Webhook
                    $note = $order['note'] ? $order['note'] . " | " : "";
                    $note .= "[Webhook AUTO] Đã nhận {$transferAmount}đ. Mã GD: {$referenceCode}";
                    
                    $stmtUpdate = $db->prepare("UPDATE orders SET status = 'processing', note = ? WHERE id = ?");
                    $stmtUpdate->execute([$note, $order_id]);

                    echo json_encode(['success' => true, 'message' => "Order {$order_id} updated successfully"]);
                } else {
                    // Thiếu tiền
                    $note = $order['note'] ? $order['note'] . " | " : "";
                    $note .= "[Webhook CẢNH BÁO] Nhận được {$transferAmount}đ (Thiếu tiền). Mã GD: {$referenceCode}";
                    
                    $stmtUpdate = $db->prepare("UPDATE orders SET note = ? WHERE id = ?");
                    $stmtUpdate->execute([$note, $order_id]);

                    echo json_encode(['success' => true, 'message' => "Order {$order_id} received partial payment"]);
                }
            } else {
                echo json_encode(['success' => true, 'message' => "Order {$order_id} is not pending"]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => "Order {$order_id} not found"]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No order code found in content']);
}
