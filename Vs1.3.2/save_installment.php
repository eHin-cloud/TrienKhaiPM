<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)$_POST['product_id'];
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $term = trim($_POST['term']);
    $user_id = $_SESSION['user_id'] ?? null;

    if (empty($fullname) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đủ họ tên và SĐT']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO installment_requests (product_id, user_id, fullname, phone, installment_term) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$product_id, $user_id, $fullname, $phone, $term]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL']);
    }
}
?>