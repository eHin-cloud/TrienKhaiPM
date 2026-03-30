<?php
session_start();
require_once 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json'); 
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'not_logged_in']);
        exit;
    }
    
    $product_id = (int)$_POST['id'];
    addToCart($db, $_SESSION['user_id'], $product_id); 
    $new_count = getCartCount($db, $_SESSION['user_id']);
    
    echo json_encode(['success' => true, 'cart_count' => $new_count]);
    exit; 
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?login_required=1");
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    addToCart($db, $_SESSION['user_id'], (int)$_GET['id']);
}

header("Location: cart.php");
exit;
?>