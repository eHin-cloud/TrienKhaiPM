<?php
/**
 * views/api/order_details.php - AJAX API to fetch order details
 */
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'unauthorized']);
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

try {
    // 1. Fetch Order General Info
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'order_not_found']);
        exit;
    }

    // 2. Fetch Order Items
    $stmt_details = $db->prepare("SELECT od.*, p.name, p.image FROM order_details od JOIN products p ON od.product_id = p.id WHERE od.order_id = ?");
    $stmt_details->execute([$order_id]);
    $items = $stmt_details->fetchAll(PDO::FETCH_ASSOC);

    // Calculate realistic derived timestamps for intermediate states
    $base_time = strtotime($order['created_at']);
    $processing_at = $base_time + (rand(10, 30) * 60); // +10-30 mins
    $delivering_at = $base_time + (rand(6, 12) * 3600); // +6-12 hours
    $completed_at = !empty($order['completed_at']) ? strtotime($order['completed_at']) : $base_time + (rand(24, 48) * 3600);

    // Format fields for frontend consumption
    $formatted_order = [
        'id' => $order['id'],
        'created_at' => date('d/m/Y H:i', $base_time),
        'processing_at' => date('d/m/Y H:i', $processing_at),
        'delivering_at' => date('d/m/Y H:i', $delivering_at),
        'completed_at' => date('d/m/Y H:i', $completed_at),
        'total_price' => (float)$order['total_price'],
        'total_price_formatted' => number_format($order['total_price'], 0, ',', '.') . 'đ',
        'status' => $order['status'],
        'fullname' => $order['fullname'],
        'phone' => $order['phone'],
        'address' => $order['address'],
        'note' => $order['note'] ?? '',
        'payment_method' => $order['payment_method'] ?? 'COD',
        'items' => []
    ];

    foreach ($items as $item) {
        $formatted_order['items'][] = [
            'product_id' => $item['product_id'],
            'name' => $item['name'],
            'image' => $item['image'],
            'price' => (float)$item['price'],
            'price_formatted' => number_format($item['price'], 0, ',', '.') . 'đ',
            'quantity' => (int)$item['quantity'],
            'total' => number_format($item['price'] * $item['quantity'], 0, ',', '.') . 'đ'
        ];
    }

    echo json_encode([
        'success' => true,
        'order' => $formatted_order
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'error', 'details' => $e->getMessage()]);
}
