<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['fullname'] = 'Test User';
$_SESSION['role'] = 'customer';

$_GET['action'] = 'read_all';
$_SERVER['REQUEST_METHOD'] = 'POST';

require_once __DIR__ . '/../public/api/notification.php';
