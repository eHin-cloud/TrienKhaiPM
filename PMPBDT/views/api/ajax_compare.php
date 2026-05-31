<?php
/**
 * API XỬ LÝ SO SÁNH SẢN PHẨM
 * Quản lý danh sách sản phẩm trong Session để chuẩn bị cho việc so sánh.
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Khởi tạo danh sách nếu chưa có
if (!isset($_SESSION['compare_list'])) {
    $_SESSION['compare_list'] = [];
}

$action = $_POST['action'] ?? 'get';
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

$response = [
    'success' => false,
    'message' => '',
    'count' => count($_SESSION['compare_list']),
    'list' => $_SESSION['compare_list']
];

switch ($action) {
    case 'add':
        if ($product_id <= 0) {
            $response['message'] = 'ID sản phẩm không hợp lệ.';
            break;
        }

        if (in_array($product_id, $_SESSION['compare_list'])) {
            $response['message'] = 'Sản phẩm này đã có trong danh sách so sánh.';
            $response['success'] = true; // Vẫn coi là thành công
            break;
        }

        if (count($_SESSION['compare_list']) >= 3) {
            $response['message'] = 'Bạn chỉ có thể so sánh tối đa 3 sản phẩm cùng lúc.';
            break;
        }

        // Kiểm tra cùng loại (Cần database)
        require_once __DIR__ . '/../../core/database.php';
        $stmt = $db->prepare("SELECT category_id FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $p = $stmt->fetch();

        if (!$p) {
            $response['message'] = 'Sản phẩm không tồn tại.';
            break;
        }

        // Nếu danh sách đã có sản phẩm, kiểm tra xem có cùng category không
        if (!empty($_SESSION['compare_list'])) {
            $first_id = $_SESSION['compare_list'][0];
            $stmt2 = $db->prepare("SELECT category_id FROM products WHERE id = ?");
            $stmt2->execute([$first_id]);
            $p1 = $stmt2->fetch();

            if ($p1 && $p1['category_id'] != $p['category_id']) {
                $response['message'] = 'Bạn chỉ nên so sánh các sản phẩm cùng loại.';
                // Ở đây ta có thể chọn chặn lại hoặc cảnh báo. Ở đây tôi sẽ chặn để UX tốt hơn.
                break;
            }
        }

        $_SESSION['compare_list'][] = $product_id;
        $response['success'] = true;
        $response['message'] = 'Đã thêm vào danh sách so sánh.';
        break;

    case 'remove':
        if (($key = array_search($product_id, $_SESSION['compare_list'])) !== false) {
            unset($_SESSION['compare_list'][$key]);
            $_SESSION['compare_list'] = array_values($_SESSION['compare_list']);
            $response['success'] = true;
        }
        break;

    case 'force_add':
        $_SESSION['compare_list'] = [$product_id];
        $response['success'] = true;
        $response['message'] = 'Đã xóa danh sách cũ và bắt đầu so sánh loại sản phẩm mới.';
        break;

    case 'clear':
        $_SESSION['compare_list'] = [];
        $response['success'] = true;
        break;

    case 'get':
    default:
        $response['success'] = true;
        break;
}

// Luôn lấy lại danh sách chi tiết sau mỗi hành động để frontend render
if (!empty($_SESSION['compare_list'])) {
    require_once __DIR__ . '/../../core/database.php';
    $ids = implode(',', $_SESSION['compare_list']);
    $stmt = $db->query("SELECT id, name, image, category_id FROM products WHERE id IN ($ids) ORDER BY FIELD(id, $ids)");
    $response['full_list'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $response['full_list'] = [];
}

$response['count'] = count($_SESSION['compare_list']);
echo json_encode($response);
