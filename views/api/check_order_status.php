<?php
/**
 * Lấy trạng thái đơn hàng (Dùng cho AJAX Polling)
 */
require_once __DIR__ . '/../../core/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        echo json_encode(['status' => $order['status']]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error']);
}
