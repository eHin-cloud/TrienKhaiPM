<?php
require_once __DIR__ . '/../core/database.php';

try {
    // Kiểm tra xem cột more_images đã tồn tại chưa
    $stmt = $db->query("SHOW COLUMNS FROM products LIKE 'more_images'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE products ADD COLUMN more_images TEXT NULL AFTER image");
        echo "Successfully added more_images column.\n";
    } else {
        echo "Column more_images already exists.\n";
    }

    // Cập nhật dữ liệu mẫu cho một vài sản phẩm để test
    // Tôi sẽ lấy một vài URL ảnh từ Unsplash
    $sample_images = [
        'https://images.unsplash.com/photo-1593305841991-05c297ba4575?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1550009158-9ebf69173e03?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1546054454-aa26e2b734c7?auto=format&fit=crop&w=800&q=80'
    ];
    $more_images_json = json_encode($sample_images);

    $stmt = $db->prepare("UPDATE products SET more_images = ? WHERE id = ?");
    
    // Giả sử có SP ID 1, 2, 3
    $stmt->execute([$more_images_json, 1]);
    $stmt->execute([$more_images_json, 2]);
    $stmt->execute([$more_images_json, 3]);

    echo "Sample data updated for products 1, 2, 3.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
