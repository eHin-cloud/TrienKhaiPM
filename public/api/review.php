<?php
ob_start();
error_reporting(0);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/api.php';

use App\Repository\ReviewRepository;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = api_request_data();
$action = $_GET['action'] ?? ($data['action'] ?? 'list');

$reviewRepo = new ReviewRepository($db);

switch ($action) {
    case 'list':
        $productId = (int)($_GET['product_id'] ?? 0);
        if ($productId <= 0) api_json_response(false, 'Thiếu product_id.', [], 422);

        $reviews = $reviewRepo->getProductReviews($productId);
        $stats = $reviewRepo->getReviewStats($reviews);

        api_json_response(true, 'Lấy danh sách đánh giá thành công.', [
            'reviews' => $reviews,
            'stats' => $stats
        ]);

    case 'add':
        if ($method !== 'POST') api_json_response(false, 'Method not allowed.', [], 405);
        
        $authUser = api_authenticated_user();
        $userId = (int) ($authUser['user_id'] ?? 0);
        if ($userId <= 0) api_json_response(false, 'Vui lòng đăng nhập để đánh giá.', [], 401);

        $productId = (int)($data['product_id'] ?? 0);
        $rating = (int)($data['rating'] ?? 5);
        $comment = trim($data['comment'] ?? '');

        if ($productId <= 0 || empty($comment)) {
            api_json_response(false, 'Vui lòng nhập đầy đủ thông tin.', [], 422);
        }

        // Kiểm tra xem đã đánh giá chưa (optional, tùy logic app)
        $stmt = $db->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
        $stmt->execute([$productId, $userId]);
        if ($stmt->fetch()) {
            api_json_response(false, 'Bạn đã đánh giá sản phẩm này rồi.', [], 400);
        }

        $stmt = $db->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$productId, $userId, $rating, $comment]);

        // Cập nhật trung bình sao trong bảng products
        $avgStmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE product_id = ?");
        $avgStmt->execute([$productId]);
        $avgData = $avgStmt->fetch(PDO::FETCH_ASSOC);
        
        $db->prepare("UPDATE products SET rate_star = ?, total_reviews = ? WHERE id = ?")
           ->execute([round($avgData['avg_rating'], 1), $avgData['total'], $productId]);

        api_json_response(true, 'Đã gửi đánh giá của bạn. Cảm ơn bạn!');

    default:
        api_json_response(false, 'Action không hợp lệ.', [], 400);
}
