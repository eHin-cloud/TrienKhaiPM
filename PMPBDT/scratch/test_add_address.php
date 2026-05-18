<?php
$_SESSION['user_id'] = 1; // Giả lập user id 1
require 'core/database.php';
require 'core/api.php';

$data = [
    'fullname' => 'Test User',
    'phone' => '0123456789',
    'address' => '123 Test St',
    'is_default' => 1
];

try {
    $userId = 1;
    $fullname = $data['fullname'];
    $phone = $data['phone'];
    $address = $data['address'];
    $isDefault = $data['is_default'];

    if ($isDefault === 1) {
        $db->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$userId]);
    }

    $stmt = $db->prepare('INSERT INTO addresses (user_id, fullname, phone, address, is_default) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $fullname, $phone, $address, $isDefault]);
    
    echo "SUCCESS: Added address ID " . $db->lastInsertId() . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
