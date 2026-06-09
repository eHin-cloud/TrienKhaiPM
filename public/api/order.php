<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/lang.php';
require_once __DIR__ . '/../../core/api.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authUser = api_authenticated_user();
$userId = (int)($authUser['user_id'] ?? 0);
if ($userId <= 0) {
    api_json_response(false, 'Chưa đăng nhập.', [], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = api_request_data();
$action = $_GET['action'] ?? ($data['action'] ?? 'list');

switch ($action) {
    case 'list':
        if ($method !== 'GET') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $stmt = $db->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        api_json_response(true, 'Lấy danh sách đơn hàng thành công.', [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);

    case 'detail':
        if ($method !== 'GET') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            api_json_response(false, 'Thiếu id.', [], 422);
        }

        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            api_json_response(false, 'Không tìm thấy đơn hàng.', [], 404);
        }

        $stmt2 = $db->prepare('SELECT od.*, p.name, p.image FROM order_details od JOIN products p ON p.id = od.product_id WHERE od.order_id = ?');
        $stmt2->execute([$id]);

        // Lấy thông tin đổi trả nếu có
        $stmt_return = $db->prepare('SELECT * FROM returns WHERE order_id = ?');
        $stmt_return->execute([$id]);
        $return_request = $stmt_return->fetch(PDO::FETCH_ASSOC);

        // Lấy thông tin bảo hành nếu có
        $stmt_warranty = $db->prepare('SELECT w.*, p.name as product_name, p.image as product_image FROM warranties w JOIN products p ON w.product_id = p.id WHERE w.order_id = ?');
        $stmt_warranty->execute([$id]);
        $warranty_requests = $stmt_warranty->fetchAll(PDO::FETCH_ASSOC);

        api_json_response(true, 'Lấy chi tiết đơn hàng thành công.', [
            'order' => $order,
            'items' => $stmt2->fetchAll(PDO::FETCH_ASSOC),
            'return_request' => $return_request ?: null,
            'warranty_requests' => $warranty_requests ?: [],
        ]);

    case 'cancel':
        if ($method !== 'POST') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $data = api_request_data();
        $id = (int)($data['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            api_json_response(false, 'Thiếu id đơn hàng.', [], 422);
        }

        $stmt_check = $db->prepare('SELECT status FROM orders WHERE id = ? AND user_id = ?');
        $stmt_check->execute([$id, $userId]);
        $orderStatus = $stmt_check->fetchColumn();

        if ($orderStatus === false) {
            api_json_response(false, 'Không tìm thấy đơn hàng.', [], 404);
        }

        if ($orderStatus !== 'pending') {
            api_json_response(false, 'Chỉ có thể hủy đơn hàng ở trạng thái chờ duyệt.', [], 400);
        }

        $stmt_cancel = $db->prepare("UPDATE orders SET status = 'cancelled', note = CONCAT(IFNULL(note, ''), ' [Khách tự hủy trên App di động]') WHERE id = ? AND user_id = ?");
        $stmt_cancel->execute([$id, $userId]);

        api_json_response(true, 'Hủy đơn hàng thành công.', [
            'id' => $id,
            'status' => 'cancelled',
        ]);

    case 'status':
        if ($method !== 'GET') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            api_json_response(false, 'Thiếu id.', [], 422);
        }

        $stmt = $db->prepare('SELECT status FROM orders WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $status = $stmt->fetchColumn();
        if ($status === false) {
            api_json_response(false, 'Không tìm thấy đơn hàng.', [], 404);
        }

        api_json_response(true, 'Lấy trạng thái thành công.', [
            'status' => $status,
        ]);

    case 'request_warranty':
        if ($method !== 'POST') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $order_id = (int)($_POST['order_id'] ?? 0);
        $product_id = (int)($_POST['product_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if ($order_id <= 0 || $product_id <= 0 || empty($reason)) {
            api_json_response(false, 'Vui lòng nhập lý do và thông tin sản phẩm.', [], 422);
        }

        // Kiểm tra quyền sở hữu đơn hàng và trạng thái đã giao thành công (completed)
        $stmt_check = $db->prepare('SELECT status FROM orders WHERE id = ? AND user_id = ?');
        $stmt_check->execute([$order_id, $userId]);
        $order_status = $stmt_check->fetchColumn();

        if ($order_status === false) {
            api_json_response(false, 'Không tìm thấy đơn hàng.', [], 404);
        }

        if ($order_status !== 'completed') {
            api_json_response(false, 'Chỉ có thể yêu cầu bảo hành đối với đơn hàng giao thành công.', [], 400);
        }

        // Chặn nếu đơn hàng đã có yêu cầu trả hàng trong bảng returns
        $stmt_return_check = $db->prepare('SELECT id FROM returns WHERE order_id = ?');
        $stmt_return_check->execute([$order_id]);
        if ($stmt_return_check->fetch()) {
            api_json_response(false, 'Đơn hàng này đã gửi yêu cầu trả hàng, không thể gửi yêu cầu bảo hành.', [], 400);
        }

        // Xử lý upload ảnh đính kèm
        $media_paths = [];
        if (isset($_FILES['media']) || isset($_FILES['warranty_media'])) {
            $files = $_FILES['media'] ?? $_FILES['warranty_media'];
            if (is_array($files['name'])) {
                $file_count = count($files['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $mime = $files['type'][$i];
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (in_array($mime, $allowed_types) && $files['size'][$i] <= 10 * 1024 * 1024) {
                            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                            $new_name = 'warranty_' . time() . '_' . $i . '_' . rand(1000, 9999) . '.' . $ext;
                            $target_dir = __DIR__ . '/../uploads/warranties/';
                            if (!file_exists($target_dir)) {
                                mkdir($target_dir, 0777, true);
                            }
                            if (move_uploaded_file($files['tmp_name'][$i], $target_dir . $new_name)) {
                                $media_paths[] = 'uploads/warranties/' . $new_name;
                            }
                        }
                    }
                }
            } else {
                if ($files['error'] === UPLOAD_ERR_OK) {
                    $mime = $files['type'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (in_array($mime, $allowed_types) && $files['size'] <= 10 * 1024 * 1024) {
                        $ext = pathinfo($files['name'], PATHINFO_EXTENSION);
                        $new_name = 'warranty_' . time() . '_0_' . rand(1000, 9999) . '.' . $ext;
                        $target_dir = __DIR__ . '/../uploads/warranties/';
                        if (!file_exists($target_dir)) {
                            mkdir($target_dir, 0777, true);
                        }
                        if (move_uploaded_file($files['tmp_name'], $target_dir . $new_name)) {
                            $media_paths[] = 'uploads/warranties/' . $new_name;
                        }
                    }
                }
            }
        }
        $media_json = !empty($media_paths) ? json_encode($media_paths) : null;

        try {
            addWarrantyRequest($db, $order_id, $product_id, $userId, $reason, $media_json);
            api_json_response(true, 'Gửi yêu cầu bảo hành thành công! Chúng tôi sẽ kiểm tra và liên hệ với bạn sớm nhất.', [
                'order_id' => $order_id,
                'product_id' => $product_id,
            ]);
        } catch (Exception $e) {
            api_json_response(false, 'Không thể gửi yêu cầu bảo hành: ' . $e->getMessage(), [], 500);
        }

    case 'request_return':
        if ($method !== 'POST') {
            api_json_response(false, 'Phương thức không hợp lệ.', [], 405);
        }

        $order_id = (int)($_POST['order_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if ($order_id <= 0 || empty($reason)) {
            api_json_response(false, 'Vui lòng nhập lý do đổi trả đơn hàng.', [], 422);
        }

        // Kiểm tra quyền sở hữu đơn hàng và trạng thái đã giao thành công (completed)
        $stmt_check = $db->prepare('SELECT status FROM orders WHERE id = ? AND user_id = ?');
        $stmt_check->execute([$order_id, $userId]);
        $order_status = $stmt_check->fetchColumn();

        if ($order_status === false) {
            api_json_response(false, 'Không tìm thấy đơn hàng.', [], 404);
        }

        if ($order_status !== 'completed') {
            api_json_response(false, 'Chỉ có thể yêu cầu đổi trả đối với đơn hàng giao thành công.', [], 400);
        }

        // Chặn nếu đơn hàng đã có sản phẩm gửi yêu cầu bảo hành trong bảng warranties
        $stmt_warranty_check = $db->prepare('SELECT id FROM warranties WHERE order_id = ?');
        $stmt_warranty_check->execute([$order_id]);
        if ($stmt_warranty_check->fetch()) {
            api_json_response(false, 'Đơn hàng này đang có sản phẩm yêu cầu bảo hành, không thể yêu cầu trả hàng.', [], 400);
        }

        // Xử lý upload ảnh đính kèm
        $media_paths = [];
        if (isset($_FILES['media']) || isset($_FILES['return_media'])) {
            $files = $_FILES['media'] ?? $_FILES['return_media'];
            if (is_array($files['name'])) {
                $file_count = count($files['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $mime = $files['type'][$i];
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (in_array($mime, $allowed_types) && $files['size'][$i] <= 10 * 1024 * 1024) {
                            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                            $new_name = 'return_' . time() . '_' . $i . '_' . rand(1000, 9999) . '.' . $ext;
                            $target_dir = __DIR__ . '/../uploads/returns/';
                            if (!file_exists($target_dir)) {
                                mkdir($target_dir, 0777, true);
                            }
                            if (move_uploaded_file($files['tmp_name'][$i], $target_dir . $new_name)) {
                                $media_paths[] = 'uploads/returns/' . $new_name;
                            }
                        }
                    }
                }
            } else {
                if ($files['error'] === UPLOAD_ERR_OK) {
                    $mime = $files['type'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (in_array($mime, $allowed_types) && $files['size'] <= 10 * 1024 * 1024) {
                        $ext = pathinfo($files['name'], PATHINFO_EXTENSION);
                        $new_name = 'return_' . time() . '_0_' . rand(1000, 9999) . '.' . $ext;
                        $target_dir = __DIR__ . '/../uploads/returns/';
                        if (!file_exists($target_dir)) {
                            mkdir($target_dir, 0777, true);
                        }
                        if (move_uploaded_file($files['tmp_name'], $target_dir . $new_name)) {
                            $media_paths[] = 'uploads/returns/' . $new_name;
                        }
                    }
                }
            }
        }
        $media_json = !empty($media_paths) ? json_encode($media_paths) : null;

        try {
            addReturnRequest($db, $order_id, $userId, $reason, $media_json);
            api_json_response(true, 'Gửi yêu cầu đổi trả thành công! Chúng tôi sẽ kiểm tra và liên hệ với bạn sớm nhất.', [
                'order_id' => $order_id,
            ]);
        } catch (Exception $e) {
            api_json_response(false, 'Không thể gửi yêu cầu đổi trả: ' . $e->getMessage(), [], 500);
        }

    default:
        api_json_response(false, 'Action không hợp lệ.', [], 400);
}
