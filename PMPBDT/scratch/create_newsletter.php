<?php
require 'core/database.php';
try {
    $db->exec("CREATE TABLE IF NOT EXISTS newsletters (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        user_id INT DEFAULT NULL, 
        email VARCHAR(255) NOT NULL, 
        status ENUM('pending', 'approved') DEFAULT 'pending', 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo 'Table newsletters created successfully.';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
