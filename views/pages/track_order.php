<?php
/**
 * ============================================================
 * TRACK_ORDER.PHP - TRA CỨU & QUẢN LÝ ĐƠN HÀNG
 * ============================================================
 * 
 * CHỨC NĂNG:
 * 1. Tra cứu đơn hàng bằng mã đơn hoặc SĐT (?q=xxx)
 * 2. Lịch sử đơn hàng của user (tabs phân loại theo status)
 * 3. Hủy đơn hàng (chỉ cho status='pending')
 * 4. Nút thanh toán QR lại cho đơn pending
 * 
 * TRẠNG THÁI ĐƠN HÀNG:
 * - pending    : Chờ xử lý (mới tạo, chưa thanh toán QR)
 * - processing : Đã thanh toán (sau khi xác nhận QR)
 * - delivering : Đang giao hàng
 * - completed  : Giao thành công
 * - cancelled  : Đã hủy
 * 
 * @requires database.php, header.php, footer.php
 */
// session_start() removed by Router
// database.php is auto-loaded by Router

// === XỬ LÝ HỦY ĐƠN HÀNG (POST) ===
// Biến cờ để hiển thị toast thông báo kết quả
$cancel_success = false;  // true = hiện toast thành công
$cancel_error = '';       // Nội dung lỗi nếu hủy thất bại

/**
 * XỬ LÝ YÊU CẦU HỦY ĐƠN HÀNG (POST ACTION)
 * Quy trình:
 * 1. Nhận mã đơn hàng cần hủy từ form modal.
 * 2. Xác thực người dùng phải đang đăng nhập.
 * 3. Kiểm tra tính hợp lệ: Đơn hàng có thuộc về user hay không và trạng thái có phải là 'pending' (Chờ xử lý).
 * 4. Nếu đủ kiện, chuyển trạng thái qua 'cancelled' và nối thêm ghi chú [Khách tự hủy trên web].
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $cancel_id = $_POST['cancel_order_id']; // Mã đơn hàng cần hủy từ modal

    // Bước 1: Kiểm tra user đã đăng nhập
    if (isset($_SESSION['user_id'])) {
        // Lấy thông tin đơn hàng để xác định quyền sở hữu và trạng thái hiện tại
        $stmt_check = $db->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ?");
        $stmt_check->execute([$cancel_id, $_SESSION['user_id']]);
        $order_to_cancel = $stmt_check->fetch(PDO::FETCH_ASSOC);

        // Bước 2: Chỉ cho phép hủy đơn nếu nó tồn tại và đang ở trạng thái pending
        if ($order_to_cancel && $order_to_cancel['status'] === 'pending') {
            $stmt_cancel = $db->prepare("UPDATE orders SET status = 'cancelled', note = CONCAT(IFNULL(note, ''), ' [Khách tự hủy trên web]') WHERE id = ? AND user_id = ?");
            $stmt_cancel->execute([$cancel_id, $_SESSION['user_id']]);
            $cancel_success = true; // Bật cờ để hiển thị toast thành công
        } else {
            $cancel_error = __("cancel_pending_only");
        }
    } else {
        $cancel_error = __("login_required_msg");
    }
}

/**
 * ======================================================
 * HÀM TIỆN ÍCH: XỬ LÝ UPLOAD MULTI-FILE MEDIA
 * ======================================================
 * Tái sử dụng pattern upload từ product_detail.php (phần review).
 * Hỗ trợ ảnh (JPEG, PNG, GIF, WebP) và video (MP4, WebM).
 *
 * @param string $field_name  - Tên input file trong form HTML
 * @param string $upload_dir  - Thư mục đích lưu file
 * @param int    $max_files   - Số file tối đa cho phép (mặc định 5)
 * @return string|null        - JSON mảng đường dẫn file, hoặc null nếu không có file
 */
function processMediaUpload($field_name, $upload_dir, $max_files = 5) {
    $media_paths = [];
    if (!isset($_FILES[$field_name]) || empty($_FILES[$field_name]['name'][0])) {
        return null;
    }

    // Tự khởi tạo thư mục nếu chưa tồn tại
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm'];
    $file_count = count($_FILES[$field_name]['name']);

    for ($f = 0; $f < min($file_count, $max_files); $f++) {
        if ($_FILES[$field_name]['error'][$f] === UPLOAD_ERR_OK) {
            $mime = $_FILES[$field_name]['type'][$f];
            // Kiểm tra MIME type và giới hạn dung lượng 20MB
            if (in_array($mime, $allowed_types) && $_FILES[$field_name]['size'][$f] <= 20 * 1024 * 1024) {
                $ext = pathinfo($_FILES[$field_name]['name'][$f], PATHINFO_EXTENSION);
                $new_name = $field_name . '_' . time() . '_' . $f . '.' . $ext;
                $target = $upload_dir . $new_name;

                if (move_uploaded_file($_FILES[$field_name]['tmp_name'][$f], $target)) {
                    $media_paths[] = $target;
                }
            }
        }
    }

    return !empty($media_paths) ? json_encode($media_paths) : null;
}

// === XỬ LÝ YÊU CẦU BẢO HÀNH & ĐỔI TRẢ (POST) ===
/**
 * Xử lý khi khách hàng gửi biểu mẫu yêu cầu bảo hành hoặc trả hàng
 * Thuật toán: Nhận action từ form (request_warranty hoặc request_return), 
 * kiểm tra session để xác thực người dùng, validate dữ liệu (lý do không rỗng)
 * và gọi các hàm helper trong CSDL.
 */
$action_success = false;
$action_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_SESSION['user_id'])) {
        $action_msg = __("login_required_msg");
    } else {
        $user_id = $_SESSION['user_id'];
        $action = $_POST['action'];
        
        if ($action === 'request_warranty') {
            $order_id = $_POST['order_id'];
            $product_id = $_POST['product_id'];
            $error_type = trim($_POST['error_type'] ?? 'Khác');
            $reason = trim($_POST['reason']);
            
            if (empty($reason)) {
                $action_msg = __("warranty_reason_empty");
            } else {
                $final_reason = "[Lỗi: $error_type] $reason";
                // --- XỬ LÝ UPLOAD MEDIA ĐÍNH KÈM (tái sử dụng pattern từ product_detail.php) ---
                $media_json = processMediaUpload('warranty_media', 'uploads/warranties/');
                addWarrantyRequest($db, $order_id, $product_id, $user_id, $final_reason, $media_json);
                $action_success = true;
                $action_msg = __("warranty_request_success");
            }
        } elseif ($action === 'request_return') {
            $order_id = $_POST['order_id'];
            $return_type = trim($_POST['return_type'] ?? 'Lý do khác');
            $bank_name = trim($_POST['bank_name'] ?? '');
            $bank_account = trim($_POST['bank_account'] ?? '');
            $bank_owner = trim($_POST['bank_owner'] ?? '');
            $reason = trim($_POST['reason']);
            
            if (empty($reason) || empty($bank_name) || empty($bank_account) || empty($bank_owner)) {
                $action_msg = __("return_info_required");
            } else {
                $final_reason = "[Phân loại: $return_type]\nLý do: $reason\n\n--- THÔNG TIN NHẬN HOÀN TIỀN ---\nNgân hàng: $bank_name\nSTK: $bank_account\nChủ tài khoản: " . mb_strtoupper($bank_owner, 'UTF-8');
                // --- XỬ LÝ UPLOAD MEDIA ĐÍNH KÈM ---
                $media_json = processMediaUpload('return_media', 'uploads/returns/');
                addReturnRequest($db, $order_id, $user_id, $final_reason, $media_json);
                $action_success = true;
                $action_msg = __("return_request_success");
            }
        }
    }
}

// === TẢI DỮ LIỆU ĐƠN HÀNG ===
$search_query = isset($_GET['q']) ? trim($_GET['q']) : (isset($_GET['id']) ? trim($_GET['id']) : (isset($_GET['order_id']) ? trim($_GET['order_id']) : '')); // Mã đơn hoặc SĐT
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all'; // Tab trạng thái đang chọn
$orders = [];     // Mảng kết quả đơn hàng
$error = '';      // Thông báo lỗi/rỗng

/**
 * TRƯỜNG HỢP 1: TÌM KIẾM CHỦ ĐỘNG
 * Nếu khách hàng nhập mã hoặc số điện thoại vào ô tìm kiếm thì bỏ qua đăng nhập.
 * Dùng cho mọi đối tượng khách (Guest hoặc User).
 */
if ($search_query !== '') {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? ORDER BY id DESC");
    $stmt->execute([$search_query]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        $error = __("no_orders_found") . ': <b>' . htmlspecialchars($search_query) . '</b>';
    }
}
/**
 * TRƯỜNG HỢP 2: LỊCH SỬ ĐƠN HÀNG THEO TÀI KHOẢN
 * Nếu KHÔNG tìm kiếm chủ động, và khách ĐÃ ĐĂNG NHẬP,
 * hiển thị lịch sử mua hàng, cho phép lọc theo tab trạng thái (status).
 */ elseif (isset($_SESSION['user_id'])) {

    // Truy vấn danh sách đơn hàng theo (hoặc không theo) Tab trạng thái
    // Bỏ qua khi đang xem tab Bảo hành / Đổi trả (không phải trạng thái đơn hàng)
    if ($status_filter === 'all') {
        $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$_SESSION['user_id']]);
    } elseif (!in_array($status_filter, ['warranties', 'returns'])) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? AND status = ? ORDER BY id DESC");
        $stmt->execute([$_SESSION['user_id'], $status_filter]);
    }
    $orders = isset($stmt) ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    // Truy vấn phụ: Đếm số lượng đơn hàng trên từng trạng thái
    // Dùng để render con số (badge) trên thanh Tabs phân loại trạng thái.
    $stmtCount = $db->prepare("SELECT status, COUNT(*) as count FROM orders WHERE user_id = ? GROUP BY status");
    $stmtCount->execute([$_SESSION['user_id']]);
    $status_counts = [];
    $total_my_orders = 0;
    while ($row = $stmtCount->fetch(PDO::FETCH_ASSOC)) {
        $status_counts[$row['status']] = $row['count'];
        $total_my_orders += $row['count'];
    }

    if (empty($orders) && empty($error) && $status_filter === 'all') {
        $error = __("no_orders_yet");
    }

    // === TẢI DỮ LIỆU BẢO HÀNH & ĐỔI TRẢ (cho tab mới) ===
    $user_warranties = getUserWarranties($db, $_SESSION['user_id']);
    $user_returns = getUserReturns($db, $_SESSION['user_id']);
}

/**
 * TRUY VẤN CHI TIẾT SẢN PHẨM CỦA ĐƠN HÀNG
 * Nếu đã lấy được danh sách orders (bất luận từ TH1 hay TH2), 
 * tiếp tục join vào bảng order_details và products để lấy thông tin, hình ảnh món hàng.
 */
if (!empty($orders)) {
    foreach ($orders as &$order) {
        $stmt_details = $db->prepare("SELECT od.*, p.name, p.image FROM order_details od JOIN products p ON od.product_id = p.id WHERE od.order_id = ?");
        $stmt_details->execute([$order['id']]);
        $order['details'] = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
    }
}

require_once __DIR__ . '/../partials/header.php';

/**
 * Hàm helper: Trả về cấu hình UI (màu nền, chữ, viền, icon, nhãn)
 * cho từng trạng thái đơn hàng. Dùng để render badge trạng thái.
 * @param string $status - Mã trạng thái (pending/processing/delivering/completed/cancelled)
 * @return array - ['bg', 'text', 'border', 'label', 'icon']
 */
function getStatusUI($status)
{
    switch ($status) {
        case 'pending':
            return ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'border' => 'border-yellow-200', 'label' => __("pending"), 'icon' => 'fa-clock'];
        case 'processing':
            return ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'label' => __("paid"), 'icon' => 'fa-money-check-dollar'];
        case 'delivering':
            return ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200', 'label' => __("delivering"), 'icon' => 'fa-truck-fast'];
        case 'completed':
            return ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'border' => 'border-green-200', 'label' => __("completed"), 'icon' => 'fa-box-open'];
        case 'cancelled':
            return ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'border' => 'border-red-200', 'label' => __("cancelled"), 'icon' => 'fa-ban'];
        default:
            return ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'border' => 'border-gray-200', 'label' => __("unknown"), 'icon' => 'fa-circle-question'];
    }
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap');

    .track-page { font-family: 'Be Vietnam Pro', sans-serif; background: #f0f2f5; min-height: 100vh; }

    /* ===== HERO SECTION ===== */
    .track-hero {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
        padding: 48px 16px 80px;
        position: relative;
        overflow: hidden;
    }
    .track-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .track-hero-orb-1 {
        position: absolute; width: 400px; height: 400px; border-radius: 50%;
        background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);
        top: -100px; right: -100px; pointer-events: none;
    }
    .track-hero-orb-2 {
        position: absolute; width: 300px; height: 300px; border-radius: 50%;
        background: radial-gradient(circle, rgba(236,72,153,0.10) 0%, transparent 70%);
        bottom: -50px; left: -50px; pointer-events: none;
    }
    .track-hero-badge {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
        color: #a5b4fc; font-size: 11px; font-weight: 700;
        padding: 5px 14px; border-radius: 100px;
        letter-spacing: 1.5px; text-transform: uppercase;
        backdrop-filter: blur(8px); margin-bottom: 16px;
    }
    .track-hero h1 {
        font-size: clamp(26px, 5vw, 42px); font-weight: 900;
        color: #fff; margin: 0 0 12px;
        line-height: 1.15; letter-spacing: -0.5px;
    }
    .track-hero h1 span { color: #818cf8; }
    .track-hero p { color: rgba(255,255,255,0.55); font-size: 14px; margin: 0; }

    /* ===== SEARCH BOX NÂNG CAO ===== */
    .search-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        padding: 24px;
        max-width: 680px;
        margin: -40px auto 0;
        position: relative; z-index: 10;
    }
    .search-label {
        font-size: 12px; font-weight: 700; color: #64748b;
        text-transform: uppercase; letter-spacing: 1px;
        margin-bottom: 12px; display: block;
    }
    .search-input-wrap { display: flex; gap: 10px; }
    .search-input {
        flex: 1; padding: 14px 18px 14px 46px;
        border: 2px solid #e2e8f0; border-radius: 14px;
        font-size: 14px; font-weight: 600; color: #1e293b;
        background: #f8fafc; outline: none;
        transition: all 0.2s;
        font-family: 'Be Vietnam Pro', sans-serif;
    }
    .search-input:focus {
        border-color: #6366f1; background: #fff;
        box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
    }
    .search-input-icon {
        position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
        color: #94a3b8; font-size: 16px;
    }
    .search-btn {
        padding: 14px 28px; border-radius: 14px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        color: #fff; font-weight: 800; font-size: 14px;
        border: none; cursor: pointer; white-space: nowrap;
        box-shadow: 0 4px 15px rgba(99,102,241,0.35);
        transition: all 0.2s; font-family: 'Be Vietnam Pro', sans-serif;
    }
    .search-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(99,102,241,0.4); }
    .search-hint { margin-top: 10px; font-size: 12px; color: #94a3b8; text-align: center; }
    .search-hint span { color: #6366f1; font-weight: 600; }

    /* ===== TABS ===== */
    .tabs-bar {
        background: #fff; border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        margin-bottom: 20px; overflow: hidden;
    }
    .tabs-scroll { display: flex; overflow-x: auto; scrollbar-width: none; }
    .tabs-scroll::-webkit-scrollbar { display: none; }
    .tab-item {
        flex: 1; min-width: 110px; padding: 14px 12px;
        display: flex; flex-direction: column; align-items: center; gap: 4px;
        font-size: 12px; font-weight: 700; color: #94a3b8;
        text-decoration: none; position: relative;
        border-bottom: 3px solid transparent;
        transition: all 0.2s; white-space: nowrap;
    }
    .tab-item:hover { color: #6366f1; background: #f8f7ff; }
    .tab-item.active { color: #6366f1; border-bottom-color: #6366f1; background: #fafaff; }
    .tab-item.active-amber { color: #f59e0b; border-bottom-color: #f59e0b; background: #fffbf0; }
    .tab-item.active-blue { color: #3b82f6; border-bottom-color: #3b82f6; background: #eff6ff; }
    .tab-item.active-indigo { color: #6366f1; border-bottom-color: #6366f1; background: #eef2ff; }
    .tab-item.active-emerald { color: #10b981; border-bottom-color: #10b981; background: #ecfdf5; }
    .tab-item.active-rose { color: #f43f5e; border-bottom-color: #f43f5e; background: #fff1f2; }
    .tab-item.active-orange { color: #f97316; border-bottom-color: #f97316; background: #fff7ed; }
    .tab-item.active-purple { color: #a855f7; border-bottom-color: #a855f7; background: #faf5ff; }
    .tab-badge {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 20px; height: 18px; padding: 0 6px;
        border-radius: 100px; font-size: 10px; font-weight: 800;
        background: #f1f5f9; color: #64748b;
    }
    .tab-item.active .tab-badge { background: #6366f1; color: #fff; }
    .tab-item.active-amber .tab-badge { background: #f59e0b; color: #fff; }
    .tab-item.active-blue .tab-badge { background: #3b82f6; color: #fff; }
    .tab-item.active-indigo .tab-badge { background: #6366f1; color: #fff; }
    .tab-item.active-emerald .tab-badge { background: #10b981; color: #fff; }
    .tab-item.active-rose .tab-badge { background: #f43f5e; color: #fff; }
    .tab-item.active-orange .tab-badge { background: #f97316; color: #fff; }
    .tab-item.active-purple .tab-badge { background: #a855f7; color: #fff; }

    /* ===== ORDER CARD ===== */
    .order-card {
        background: #fff; border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        overflow: hidden; margin-bottom: 16px;
        transition: box-shadow 0.2s, transform 0.2s;
    }
    .order-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.10); transform: translateY(-2px); }
    .order-card-header {
        padding: 16px 20px;
        border-bottom: 1px solid #f1f5f9;
        display: flex; justify-content: space-between; align-items: center;
        flex-wrap: wrap; gap: 8px;
    }
    .order-store-info { display: flex; align-items: center; gap: 8px; }
    .order-store-icon {
        width: 32px; height: 32px; border-radius: 8px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 14px; flex-shrink: 0;
    }
    .order-store-name { font-weight: 800; font-size: 14px; color: #1e293b; }
    .order-store-sub { font-size: 11px; color: #94a3b8; }
    .order-status-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 14px; border-radius: 100px;
        font-size: 11px; font-weight: 800;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    .status-dot { width: 7px; height: 7px; border-radius: 50%; }

    /* Delivery progress bar (Shopee style) */
    .delivery-progress {
        padding: 16px 20px;
        border-bottom: 1px solid #f1f5f9;
        background: #fafafa;
    }
    .progress-steps {
        display: flex; align-items: center; position: relative;
    }
    .progress-step {
        flex: 1; display: flex; flex-direction: column; align-items: center;
        gap: 6px; position: relative; z-index: 1;
    }
    .progress-step-dot {
        width: 34px; height: 34px; border-radius: 50%;
        background: #e2e8f0; border: 3px solid #e2e8f0;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; color: #94a3b8; transition: all 0.3s;
    }
    .progress-step.done .progress-step-dot {
        background: #6366f1; border-color: #6366f1; color: #fff;
    }
    .progress-step.active .progress-step-dot {
        background: #fff; border-color: #6366f1; color: #6366f1;
        box-shadow: 0 0 0 6px rgba(99,102,241,0.12);
        animation: pulseStep 2s infinite;
    }
    .progress-step.rejected .progress-step-dot {
        background: #fee2e2; border-color: #f43f5e; color: #f43f5e;
    }
    .progress-step-label {
        font-size: 10px; font-weight: 700; color: #94a3b8;
        text-align: center; line-height: 1.3;
    }
    .progress-step.done .progress-step-label { color: #6366f1; }
    .progress-step.active .progress-step-label { color: #1e293b; }
    .progress-connector {
        flex: 1; height: 3px; background: #e2e8f0;
        margin: 0; position: relative; top: -14px; z-index: 0;
        transition: background 0.4s;
    }
    .progress-connector.done { background: linear-gradient(90deg, #6366f1, #818cf8); }
    @keyframes pulseStep {
        0%,100% { box-shadow: 0 0 0 4px rgba(99,102,241,0.12); }
        50% { box-shadow: 0 0 0 10px rgba(99,102,241,0.06); }
    }

    /* Order body */
    .order-body { padding: 16px 20px; }
    .order-products { display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px; }
    .order-product-item {
        display: flex; gap: 12px; align-items: center;
        padding: 10px; border-radius: 12px; background: #f8fafc;
        transition: background 0.2s;
    }
    .order-product-item:hover { background: #f0f4ff; }
    .order-product-img {
        width: 60px; height: 60px; border-radius: 10px;
        object-fit: contain; background: #fff;
        border: 1px solid #e2e8f0; flex-shrink: 0; padding: 4px;
    }
    .order-product-name {
        font-size: 13px; font-weight: 700; color: #1e293b;
        margin-bottom: 4px; line-height: 1.3;
    }
    .order-product-name a { color: inherit; text-decoration: none; }
    .order-product-name a:hover { color: #6366f1; }
    .order-product-meta { font-size: 12px; color: #64748b; }
    .order-product-price { font-size: 14px; font-weight: 800; color: #1e293b; margin-left: auto; flex-shrink: 0; }

    /* Order footer */
    .order-footer {
        padding: 12px 20px;
        border-top: 1px solid #f1f5f9;
        display: flex; justify-content: space-between; align-items: center;
        flex-wrap: wrap; gap: 10px;
        background: #fafafa;
    }
    .order-total-label { font-size: 12px; color: #64748b; font-weight: 600; }
    .order-total-amount { font-size: 20px; font-weight: 900; color: #f43f5e; }
    .order-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-action {
        padding: 8px 18px; border-radius: 10px;
        font-size: 12px; font-weight: 800;
        border: none; cursor: pointer; text-decoration: none;
        display: inline-flex; align-items: center; gap: 6px;
        transition: all 0.2s; font-family: 'Be Vietnam Pro', sans-serif;
    }
    .btn-pay { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: #fff; box-shadow: 0 4px 12px rgba(245,158,11,0.3); }
    .btn-pay:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(245,158,11,0.4); color: #fff; }
    .btn-cancel { background: #fff; color: #f43f5e; border: 1.5px solid #fecdd3; }
    .btn-cancel:hover { background: #fff1f2; }
    .btn-return { background: #fff; color: #10b981; border: 1.5px solid #a7f3d0; }
    .btn-return:hover { background: #ecfdf5; }
    .btn-warranty { background: #fff; color: #f59e0b; border: 1.5px solid #fde68a; }
    .btn-warranty:hover { background: #fffbeb; }

    /* Info row */
    .order-info-row {
        display: flex; align-items: flex-start; gap: 8px;
        font-size: 12px; color: #64748b;
        padding: 2px 0;
    }
    .order-info-row i { color: #94a3b8; width: 14px; margin-top: 1px; flex-shrink: 0; }
    .order-info-row span { font-weight: 600; color: #334155; }

    /* Empty state */
    .empty-state {
        text-align: center; padding: 60px 24px;
        background: #fff; border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    }
    .empty-icon {
        width: 100px; height: 100px; border-radius: 50%;
        margin: 0 auto 20px;
        display: flex; align-items: center; justify-content: center;
        font-size: 40px;
    }
    .empty-state h3 { font-size: 18px; font-weight: 800; color: #1e293b; margin: 0 0 8px; }
    .empty-state p { font-size: 14px; color: #64748b; margin: 0 0 24px; }
    .btn-shop {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 12px 28px; border-radius: 12px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        color: #fff; font-weight: 800; font-size: 14px;
        text-decoration: none; box-shadow: 0 4px 15px rgba(99,102,241,0.3);
        transition: all 0.2s;
    }
    .btn-shop:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(99,102,241,0.4); color: #fff; }

    /* Toast */
    @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .animate-slide-up { animation: slideUp 0.4s ease-out; }

    /* Timeline (reuse) */
    .timeline-container {
        display: flex; align-items: flex-start;
        justify-content: space-between; padding: 8px 0; position: relative;
    }
    .timeline-step { display: flex; flex-direction: column; align-items: center; flex: 1; position: relative; z-index: 1; }
    .timeline-dot {
        width: 42px; height: 42px; border-radius: 50%;
        background: #f3f4f6; border: 3px solid #e5e7eb;
        display: flex; align-items: center; justify-content: center;
        font-size: 14px; color: #9ca3af; position: relative; z-index: 2;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .timeline-line {
        position: absolute; top: 21px; left: calc(50% + 21px);
        width: calc(100% - 42px); height: 3px;
        background: #e5e7eb; z-index: 0; transition: background 0.5s ease;
    }
    .timeline-line.done { background: linear-gradient(90deg, #6366f1, #818cf8); }
    .timeline-label { margin-top: 10px; font-size: 11px; font-weight: 600; color: #9ca3af; text-align: center; max-width: 100px; line-height: 1.3; }
    .timeline-step.done .timeline-dot { background: linear-gradient(135deg, #6366f1, #4f46e5); border-color: #a5b4fc; color: #fff; box-shadow: 0 2px 8px rgba(99,102,241,0.3); }
    .timeline-step.done .timeline-label { color: #4f46e5; }
    .timeline-step.active .timeline-dot { animation: pulseActive 2s infinite; box-shadow: 0 0 0 6px rgba(99,102,241,0.15); }
    @keyframes pulseActive { 0%,100% { box-shadow: 0 0 0 4px rgba(99,102,241,0.15); } 50% { box-shadow: 0 0 0 10px rgba(99,102,241,0.08); } }
    .timeline-step.done:last-child .timeline-dot { background: linear-gradient(135deg, #22c55e, #16a34a); border-color: #86efac; box-shadow: 0 2px 8px rgba(34,197,94,0.3); }
    .timeline-step.done:last-child .timeline-label { color: #15803d; }
    .timeline-step.rejected .timeline-dot { background: linear-gradient(135deg, #ef4444, #dc2626) !important; border-color: #fca5a5 !important; color: #fff !important; box-shadow: 0 2px 8px rgba(239,68,68,0.3) !important; }
    .timeline-step.rejected .timeline-label { color: #dc2626 !important; }
    @media (max-width: 500px) {
        .timeline-container { flex-direction: column; align-items: flex-start; gap: 0; padding-left: 20px; }
        .timeline-step { flex-direction: row; align-items: center; gap: 12px; padding-bottom: 0; }
        .timeline-dot { width: 36px; height: 36px; font-size: 12px; flex-shrink: 0; }
        .timeline-line { position: absolute; top: 36px; left: 18px; width: 3px !important; height: 28px; }
        .timeline-label { margin-top: 0; text-align: left; max-width: none; font-size: 12px; }
    }
    /* Warranty/Return cards */
    .wr-card { background: #fff; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden; margin-bottom: 16px; }
    .wr-card-header { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
    .wr-card-body { padding: 16px 18px; }
    .media-thumb-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
    .media-thumb { width: 72px; height: 72px; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; cursor: pointer; position: relative; }
    .media-thumb img, .media-thumb video { width: 100%; height: 100%; object-fit: cover; }
    .media-thumb-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; }
    .media-thumb:hover .media-thumb-overlay { opacity: 1; }
    /* hide scrollbar */
    .hide-scrollbar { scrollbar-width: none; }
    .hide-scrollbar::-webkit-scrollbar { display: none; }
    /* Old classes hidden */
    .ambient-glow-1, .ambient-glow-2, .glowing-input:focus { display: none; }
</style>

<!-- ===== HERO SECTION ===== -->
<div class="track-hero">
    <div class="track-hero-orb-1"></div>
    <div class="track-hero-orb-2"></div>
    <div style="max-width:900px;margin:0 auto;text-align:center;position:relative;z-index:1">
        <div class="track-hero-badge"><i class="fa-solid fa-truck-fast"></i> <?= __("track_order") ?></div>
        <h1><?= __("track_order_title") ?> <span>📦</span></h1>
        <p><?= __("track_order_desc") ?></p>
    </div>
</div>

<div style="max-width:900px;margin:0 auto;padding:0 16px">
    <!-- SEARCH CARD -->
    <div class="search-card">
        <span class="search-label"><i class="fa-solid fa-magnifying-glass mr-1"></i> <?= __("track_order") ?></span>
        <form action="track_order.php" method="GET">
            <div class="search-input-wrap">
                <div style="flex:1;position:relative">
                    <i class="fa-solid fa-hashtag search-input-icon"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>"
                        placeholder="<?= __("track_order_placeholder") ?>"
                        class="search-input" style="width:100%">
                </div>
                <button type="submit" class="search-btn">
                    <i class="fa-solid fa-magnifying-glass-location"></i> <?= __("track_now") ?>
                </button>
            </div>
        </form>
        <p class="search-hint"><i class="fa-solid fa-circle-info mr-1"></i> <?= __("track_order_hint") ?></p>
    </div>

    <div style="padding:24px 0">

    <!-- TABS -->
    <?php if ($search_query === '' && isset($_SESSION['user_id'])): ?>
        <div class="tabs-bar" style="position:sticky;top:60px;z-index:40;margin-bottom:20px">
            <div class="tabs-scroll">
                <?php
                $tabs = [
                    ['href'=>'?status=all',       'icon'=>'fa-receipt',            'label'=>__("all_orders"),  'count'=>$total_my_orders??0,            'cls'=>$status_filter==='all'        ?'active':''],
                    ['href'=>'?status=pending',    'icon'=>'fa-clock',              'label'=>__("pending"),    'count'=>$status_counts['pending']??0,   'cls'=>$status_filter==='pending'    ?'active-amber':''],
                    ['href'=>'?status=processing', 'icon'=>'fa-money-check-dollar', 'label'=>__("paid"),      'count'=>$status_counts['processing']??0,'cls'=>$status_filter==='processing' ?'active-blue':''],
                    ['href'=>'?status=delivering', 'icon'=>'fa-truck-fast',         'label'=>__("delivering"), 'count'=>$status_counts['delivering']??0,'cls'=>$status_filter==='delivering' ?'active-indigo':''],
                    ['href'=>'?status=completed',  'icon'=>'fa-box-open',           'label'=>__("completed"),  'count'=>$status_counts['completed']??0, 'cls'=>$status_filter==='completed'  ?'active-emerald':''],
                    ['href'=>'?status=cancelled',  'icon'=>'fa-ban',                'label'=>__("cancelled"),  'count'=>$status_counts['cancelled']??0, 'cls'=>$status_filter==='cancelled'  ?'active-rose':''],
                    ['href'=>'?status=warranties', 'icon'=>'fa-wrench',             'label'=>__("warranty"),   'count'=>count($user_warranties??[]),    'cls'=>$status_filter==='warranties' ?'active-orange':''],
                    ['href'=>'?status=returns',    'icon'=>'fa-right-left',         'label'=>__("returns"),    'count'=>count($user_returns??[]),       'cls'=>$status_filter==='returns'    ?'active-purple':''],
                ];
                foreach ($tabs as $t): ?>
                <a href="<?= $t['href'] ?>" class="tab-item <?= $t['cls'] ?>">
                    <i class="fa-solid <?= $t['icon'] ?> text-[15px]"></i>
                    <?= $t['label'] ?>
                    <span class="tab-badge"><?= $t['count'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ========================================
         NỘI DUNG CHÍNH: Chuyển đổi theo tab
         ======================================== -->

    <!-- === TAB: LỊCH SỬ BẢO HÀNH (Timeline) === -->
    <?php if ($status_filter === 'warranties' && isset($_SESSION['user_id'])): ?>
        <?php if (empty($user_warranties)): ?>
            <div class="empty-state">
                <div class="empty-icon" style="background:#fff7ed;color:#f97316">
                    <i class="fa-solid fa-wrench"></i>
                </div>
                <h3><?= __("no_warranty_requests") ?></h3>
                <p><?= __("no_warranty_desc") ?></p>
            </div>
        <?php else: ?>
            <div class="space-y-5">
                <?php foreach ($user_warranties as $w):
                    // === TÍNH TOÁN BƯỚC TIMELINE CHO BẢO HÀNH ===
                    // Các bước: 1.Tiếp nhận → 2.Đang xử lý → 3.Hoàn thành/Từ chối
                    $w_steps = [
                        ['label' => __("request_received"), 'icon' => 'fa-inbox', 'done' => true],
                        ['label' => __("processing_request"), 'icon' => 'fa-gear', 'done' => in_array($w['status'], ['processing', 'completed', 'rejected'])],
                        ['label' => $w['status'] === 'rejected' ? __("warranty_rejected") : __("warranty_completed"), 'icon' => $w['status'] === 'rejected' ? 'fa-xmark' : 'fa-circle-check', 'done' => in_array($w['status'], ['completed', 'rejected'])]
                    ];
                    $w_color = match($w['status']) {
                        'pending' => 'yellow',
                        'processing' => 'blue',
                        'completed' => 'green',
                        'rejected' => 'red',
                        default => 'gray'
                    };
                    $w_status_label = match($w['status']) {
                        'pending' => __("pending"),
                        'processing' => __("processing_request"),
                        'completed' => __("completed"),
                        'rejected' => __("rejected"),
                        default => __("unknown")
                    };
                ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition">
                    <!-- Header: Thông tin sản phẩm bảo hành -->
                    <div class="bg-gray-50 p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between sm:items-center gap-3">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($w['product_image'])): ?>
                            <div class="w-12 h-12 shrink-0 bg-white border border-gray-200 rounded-lg p-1 flex items-center justify-center">
                                <img src="<?= htmlspecialchars($w['product_image']) ?>" class="max-w-full max-h-full object-contain">
                            </div>
                            <?php endif; ?>
                            <div>
                                <div class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($w['product_name'] ?? __("product")) ?></div>
                                <div class="text-xs text-gray-500"><?= __("order") ?> #<?= $w['order_id'] ?> · <?= __("request_time") ?> <?= date('H:i d/m/Y', strtotime($w['created_at'])) ?></div>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border bg-<?= $w_color ?>-100 text-<?= $w_color ?>-700 border-<?= $w_color ?>-200">
                            <span class="w-2 h-2 rounded-full bg-<?= $w_color ?>-500 <?= $w['status'] === 'processing' ? 'animate-pulse' : '' ?>"></span>
                            <?= $w_status_label ?>
                        </span>
                    </div>

                    <div class="p-5">
                        <!-- Lý do bảo hành -->
                        <div class="mb-5 bg-gray-50 rounded-lg p-3 border border-gray-100">
                            <div class="text-xs font-bold text-gray-500 mb-1"><i class="fa-solid fa-quote-left mr-1"></i><?= __("warranty_reason") ?>:</div>
                            <div class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($w['reason'])) ?></div>
                        </div>

                        <!-- BẰNG CHỨNG ĐÍNH KÈM (ảnh/video) -->
                        <?php
                        $w_media = !empty($w['media']) ? json_decode($w['media'], true) : [];
                        if (!empty($w_media)): ?>
                        <div class="mb-5">
                            <div class="text-xs font-bold text-gray-500 mb-2"><i class="fa-solid fa-images mr-1"></i><?= __("media_evidence") ?>:</div>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($w_media as $mfile):
                                    $mext = strtolower(pathinfo($mfile, PATHINFO_EXTENSION));
                                    $is_video = in_array($mext, ['mp4', 'webm', 'mov']);
                                ?>
                                <?php if ($is_video): ?>
                                    <div class="relative w-20 h-20 rounded-lg overflow-hidden border border-gray-200 cursor-pointer group" onclick="openTimelineMedia('<?= $mfile ?>', true)">
                                        <video src="<?= $mfile ?>" class="w-full h-full object-cover"></video>
                                        <div class="absolute inset-0 bg-black/30 flex items-center justify-center group-hover:bg-black/50 transition"><i class="fa-solid fa-play text-white text-lg"></i></div>
                                    </div>
                                <?php else: ?>
                                    <img src="<?= $mfile ?>" onclick="openTimelineMedia('<?= $mfile ?>', false)" class="w-20 h-20 object-cover rounded-lg border border-gray-200 cursor-pointer hover:opacity-80 transition hover:shadow-md">
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- TIMELINE TRỰC QUAN -->
                        <div class="timeline-container">
                            <?php foreach ($w_steps as $idx => $step): ?>
                            <div class="timeline-step <?= $step['done'] ? 'done' : '' ?> <?= ($step['done'] && !($w_steps[$idx+1]['done'] ?? true)) ? 'active' : '' ?> <?= ($w['status'] === 'rejected' && $idx === count($w_steps)-1) ? 'rejected' : '' ?>">
                                <div class="timeline-dot">
                                    <i class="fa-solid <?= $step['icon'] ?>"></i>
                                </div>
                                <?php if ($idx < count($w_steps) - 1): ?>
                                    <div class="timeline-line <?= ($w_steps[$idx+1]['done'] ?? false) ? 'done' : '' ?>"></div>
                                <?php endif; ?>
                                <div class="timeline-label"><?= $step['label'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- GHI CHÚ TỪ ADMIN (nếu có) -->
                        <?php if (!empty($w['admin_note'])): ?>
                        <div class="mt-5 bg-blue-50 border border-blue-200 rounded-xl p-4 relative">
                            <div class="absolute -top-3 left-4 bg-blue-500 text-white text-[10px] font-bold px-3 py-0.5 rounded-full flex items-center gap-1">
                                <i class="fa-solid fa-headset"></i> <?= __("store_feedback") ?>
                            </div>
                            <div class="text-sm text-blue-900 leading-relaxed mt-1">
                                <?= nl2br(htmlspecialchars($w['admin_note'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <!-- === TAB: LỊCH SỬ ĐỔI TRẢ (Timeline) === -->
    <?php elseif ($status_filter === 'returns' && isset($_SESSION['user_id'])): ?>
        <?php if (empty($user_returns)): ?>
            <div class="empty-state">
                <div class="empty-icon" style="background:#faf5ff;color:#a855f7">
                    <i class="fa-solid fa-right-left"></i>
                </div>
                <h3><?= __("no_return_requests") ?></h3>
                <p><?= __("no_return_desc") ?></p>
            </div>
        <?php else: ?>
            <div class="space-y-5">
                <?php foreach ($user_returns as $r):
                    // === TÍNH TOÁN BƯỚC TIMELINE CHO ĐỔI TRẢ ===
                    // Các bước: 1.Tiếp nhận → 2.Đang lấy hàng hoàn → 3.Đang kiểm tra → 4.Đã hoàn tiền/Từ chối
                    $r_is_rejected = ($r['status'] === 'rejected');
                    $r_steps = [
                        ['label' => __("return_request_received"), 'icon' => 'fa-inbox', 'done' => true],
                        ['label' => __("picking_up_return"), 'icon' => 'fa-truck-ramp-box', 'done' => in_array($r['status'], ['approved', 'refunded', 'rejected'])],
                        ['label' => __("inspecting_errors"), 'icon' => 'fa-magnifying-glass', 'done' => in_array($r['status'], ['approved', 'refunded'])],
                        ['label' => $r_is_rejected ? __("return_rejected") : __("refunded"), 'icon' => $r_is_rejected ? 'fa-xmark' : 'fa-money-bill-wave', 'done' => in_array($r['status'], ['refunded', 'rejected'])]
                    ];
                    $r_color = match($r['status']) {
                        'pending' => 'yellow',
                        'approved' => 'blue',
                        'refunded' => 'green',
                        'rejected' => 'red',
                        default => 'gray'
                    };
                    $r_status_label = match($r['status']) {
                        'pending' => __("pending"),
                        'approved' => __("approved"),
                        'refunded' => __("refunded"),
                        'rejected' => __("rejected"),
                        default => __("unknown")
                    };
                ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition">
                    <!-- Header -->
                    <div class="bg-gray-50 p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between sm:items-center gap-3">
                        <div>
                            <div class="font-bold text-gray-800 text-sm"><i class="fa-solid fa-right-left text-purple-500 mr-1"></i><?= __("return_request") ?> — <?= __("order") ?> #<?= $r['order_id'] ?></div>
                            <div class="text-xs text-gray-500"><?= __("sent_at") ?> <?= date('H:i d/m/Y', strtotime($r['created_at'])) ?></div>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border bg-<?= $r_color ?>-100 text-<?= $r_color ?>-700 border-<?= $r_color ?>-200">
                            <span class="w-2 h-2 rounded-full bg-<?= $r_color ?>-500 <?= in_array($r['status'], ['pending', 'approved']) ? 'animate-pulse' : '' ?>"></span>
                            <?= $r_status_label ?>
                        </span>
                    </div>

                    <div class="p-5">
                        <!-- Lý do trả hàng -->
                        <div class="mb-5 bg-gray-50 rounded-lg p-3 border border-gray-100">
                            <div class="text-xs font-bold text-gray-500 mb-1"><i class="fa-solid fa-quote-left mr-1"></i><?= __("return_reason") ?>:</div>
                            <div class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($r['reason'])) ?></div>
                        </div>

                        <!-- BẰNG CHỨNG ĐÍNH KÈM (ảnh/video) -->
                        <?php
                        $r_media = !empty($r['media']) ? json_decode($r['media'], true) : [];
                        if (!empty($r_media)): ?>
                        <div class="mb-5">
                            <div class="text-xs font-bold text-gray-500 mb-2"><i class="fa-solid fa-images mr-1"></i><?= __("media_evidence") ?>:</div>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($r_media as $mfile):
                                    $mext = strtolower(pathinfo($mfile, PATHINFO_EXTENSION));
                                    $is_video = in_array($mext, ['mp4', 'webm', 'mov']);
                                ?>
                                <?php if ($is_video): ?>
                                    <div class="relative w-20 h-20 rounded-lg overflow-hidden border border-gray-200 cursor-pointer group" onclick="openTimelineMedia('<?= $mfile ?>', true)">
                                        <video src="<?= $mfile ?>" class="w-full h-full object-cover"></video>
                                        <div class="absolute inset-0 bg-black/30 flex items-center justify-center group-hover:bg-black/50 transition"><i class="fa-solid fa-play text-white text-lg"></i></div>
                                    </div>
                                <?php else: ?>
                                    <img src="<?= $mfile ?>" onclick="openTimelineMedia('<?= $mfile ?>', false)" class="w-20 h-20 object-cover rounded-lg border border-gray-200 cursor-pointer hover:opacity-80 transition hover:shadow-md">
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- TIMELINE TRỰC QUAN -->
                        <div class="timeline-container">
                            <?php foreach ($r_steps as $idx => $step): ?>
                            <div class="timeline-step <?= $step['done'] ? 'done' : '' ?> <?= ($step['done'] && !($r_steps[$idx+1]['done'] ?? true)) ? 'active' : '' ?> <?= ($r_is_rejected && $idx === count($r_steps)-1 && $step['done']) ? 'rejected' : '' ?>">
                                <div class="timeline-dot">
                                    <i class="fa-solid <?= $step['icon'] ?>"></i>
                                </div>
                                <?php if ($idx < count($r_steps) - 1): ?>
                                    <div class="timeline-line <?= ($r_steps[$idx+1]['done'] ?? false) ? 'done' : '' ?>"></div>
                                <?php endif; ?>
                                <div class="timeline-label"><?= $step['label'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- GHI CHÚ TỪ ADMIN (nếu có) -->
                        <?php if (!empty($r['admin_note'])): ?>
                        <div class="mt-5 bg-purple-50 border border-purple-200 rounded-xl p-4 relative">
                            <div class="absolute -top-3 left-4 bg-purple-500 text-white text-[10px] font-bold px-3 py-0.5 rounded-full flex items-center gap-1">
                                <i class="fa-solid fa-headset"></i> <?= __("store_feedback") ?>
                            </div>
                            <div class="text-sm text-purple-900 leading-relaxed mt-1">
                                <?= nl2br(htmlspecialchars($r['admin_note'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <!-- === TAB MẶC ĐỊNH: DANH SÁCH ĐƠN HÀNG === -->
    <?php elseif ($search_query !== '' || isset($_SESSION['user_id'])): ?>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="empty-icon" style="background:#f0f4ff;color:#6366f1">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <h3><?= __("no_orders") ?></h3>
                <p><?= $error ? $error : __("no_orders_status") ?></p>
                <a href="index.php" class="btn-shop">
                    <i class="fa-solid fa-bag-shopping"></i> <?= __("continue_shopping") ?>
                </a>
            </div>

        <?php else: ?>
            <?php if ($search_query !== ''): ?>
                <div style="font-size:13px;font-weight:700;color:#64748b;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #f1f5f9">
                    <i class="fa-solid fa-list-check mr-1"></i> <?= __("search_results_found") ?> <b style="color:#6366f1"><?= count($orders) ?></b> <?= __("orders_count") ?>
                </div>
            <?php endif; ?>

            <div style="display:flex;flex-direction:column;gap:16px">
                <?php foreach ($orders as $order):
                    $ui = getStatusUI($order['status']);
                    $prog_statuses = ['pending','processing','delivering','completed'];
                    $curr_idx = array_search($order['status'], $prog_statuses);
                    $is_cancelled = $order['status'] === 'cancelled';
                    $sbadge = match($order['status']) {
                        'pending'    => ['bg'=>'#fff8e1','color'=>'#f59e0b','dot'=>'#f59e0b'],
                        'processing' => ['bg'=>'#eff6ff','color'=>'#3b82f6','dot'=>'#3b82f6'],
                        'delivering' => ['bg'=>'#eef2ff','color'=>'#6366f1','dot'=>'#6366f1'],
                        'completed'  => ['bg'=>'#ecfdf5','color'=>'#10b981','dot'=>'#10b981'],
                        'cancelled'  => ['bg'=>'#fff1f2','color'=>'#f43f5e','dot'=>'#f43f5e'],
                        default      => ['bg'=>'#f8fafc','color'=>'#64748b','dot'=>'#94a3b8'],
                    };
                ?>
                <div class="order-card">
                    <!-- Card Header -->
                    <div class="order-card-header">
                        <div class="order-store-info">
                            <div class="order-store-icon"><i class="fa-solid fa-store"></i></div>
                            <div>
                                <div class="order-store-name"><?= __("order") ?> #<?= $order['id'] ?></div>
                                <div class="order-store-sub"><i class="fa-regular fa-calendar mr-1"></i><?= date('H:i · d/m/Y', strtotime($order['created_at'])) ?></div>
                            </div>
                        </div>
                        <span class="order-status-badge" style="background:<?= $sbadge['bg'] ?>;color:<?= $sbadge['color'] ?>">
                            <span class="status-dot" style="background:<?= $sbadge['dot'] ?>"></span>
                            <?= $ui['label'] ?>
                        </span>
                    </div>

                    <!-- Delivery Progress Bar -->
                    <?php if (!$is_cancelled): ?>
                    <div class="delivery-progress">
                        <div class="progress-steps">
                            <?php
                            $psteps = [
                                ['icon'=>'fa-clipboard-check',    'label'=>__("pending")],
                                ['icon'=>'fa-money-check-dollar', 'label'=>__("paid")],
                                ['icon'=>'fa-truck-fast',         'label'=>__("delivering")],
                                ['icon'=>'fa-box-open',           'label'=>__("completed")],
                            ];
                            foreach ($psteps as $pi => $ps):
                                $is_done   = ($curr_idx !== false && $pi <= $curr_idx);
                                $is_active = ($curr_idx !== false && $pi === $curr_idx);
                                $pcls = $is_active ? 'active' : ($is_done ? 'done' : '');
                            ?>
                            <div class="progress-step <?= $pcls ?>">
                                <div class="progress-step-dot"><i class="fa-solid <?= $ps['icon'] ?>"></i></div>
                                <div class="progress-step-label"><?= $ps['label'] ?></div>
                            </div>
                            <?php if ($pi < count($psteps)-1): ?>
                            <div class="progress-connector <?= ($curr_idx !== false && $pi < $curr_idx) ? 'done' : '' ?>"></div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Products -->
                    <div class="order-body">
                        <div class="order-products">
                            <?php foreach ($order['details'] as $item): ?>
                            <div class="order-product-item">
                                <img src="<?= htmlspecialchars($item['image']) ?>" class="order-product-img" alt="">
                                <div style="flex:1;min-width:0">
                                    <div class="order-product-name">
                                        <a href="product_detail.php?id=<?= $item['product_id'] ?>">
                                            <?= htmlspecialchars(getCurrentLang() === 'en' ? translate_text($item['name'], 'prod_name_' . $item['product_id']) : $item['name']) ?>
                                        </a>
                                    </div>
                                    <div class="order-product-meta"><?= __("qty") ?>: <?= $item['quantity'] ?></div>
                                </div>
                                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
                                    <div class="order-product-price"><?= number_format($item['price']) ?>đ</div>
                                    <?php if ($order['status'] === 'completed'): ?>
                                    <button type="button" onclick="openWarrantyModal(<?= $order['id'] ?>, <?= $item['product_id'] ?>)" class="btn-action btn-warranty" style="font-size:11px;padding:5px 12px">
                                        <i class="fa-solid fa-wrench"></i> <?= __("warranty") ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <!-- Shipping info compact -->
                        <div style="background:#f8fafc;border-radius:12px;padding:10px 14px;display:flex;flex-wrap:wrap;gap:6px 20px;font-size:12px;color:#64748b">
                            <span><i class="fa-solid fa-user mr-1" style="color:#6366f1"></i><b style="color:#334155"><?= htmlspecialchars($order['fullname']) ?></b></span>
                            <span><i class="fa-solid fa-phone mr-1" style="color:#6366f1"></i><?= htmlspecialchars($order['phone']) ?></span>
                            <span style="flex:1;min-width:200px"><i class="fa-solid fa-location-dot mr-1" style="color:#6366f1"></i><?= htmlspecialchars($order['address']) ?></span>
                        </div>
                    </div>

                    <!-- Footer: Total + Actions -->
                    <div class="order-footer">
                        <div>
                            <div class="order-total-label"><?= __("total_price") ?></div>
                            <div class="order-total-amount"><?= number_format($order['total_price']) ?>đ</div>
                        </div>
                        <div class="order-actions">
                            <?php if ($order['status'] === 'pending'): ?>
                                <a href="payment.php?order_id=<?= $order['id'] ?>" class="btn-action btn-pay">
                                    <i class="fa-solid fa-credit-card"></i> <?= __("pay_qr") ?>
                                </a>
                                <button type="button" onclick="openCancelModal(<?= $order['id'] ?>)" class="btn-action btn-cancel">
                                    <i class="fa-solid fa-xmark"></i> <?= __("cancel_order") ?>
                                </button>
                            <?php elseif ($order['status'] === 'completed'): ?>
                                <button type="button" onclick="openReturnModal(<?= $order['id'] ?>)" class="btn-action btn-return">
                                    <i class="fa-solid fa-right-left"></i> <?= __("return_refund") ?>
                                </button>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <button type="button"
                                    onclick="reorderItems(this, <?= htmlspecialchars(json_encode(array_map(fn($i) => (int)$i['product_id'], $order['details']))) ?>)"
                                    class="btn-action btn-pay" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
                                    <i class="fa-solid fa-rotate-right"></i> <?= __("reorder") ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- THÔNG BÁO TOAST GÓC DƯỚI (Phản hồi sau khi thực hiện hành động) -->

    <!-- Toast: Action thành công (Bảo hành, Đổi trả) -->
    <?php if (isset($action_success) && $action_success): ?>
        <div id="actionSuccessToast"
            class="fixed top-6 right-6 bg-blue-600 text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3 z-[9999] animate-slide-up border-l-4 border-blue-300">
            <i class="fa-solid fa-bell text-xl"></i>
            <span class="font-bold text-sm leading-snug max-w-xs"><?= htmlspecialchars($action_msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- Toast: Action lỗi -->
    <?php if (isset($action_success) && !$action_success && !empty($action_msg)): ?>
        <div id="actionErrorToast" class="fixed top-6 right-6 bg-red-600 text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3 z-[9999] animate-slide-up">
            <i class="fa-solid fa-triangle-exclamation text-xl"></i>
            <span class="font-bold text-sm leading-snug max-w-xs"><?= htmlspecialchars($action_msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- Toast: Hủy thành công -->
    <?php if (isset($cancel_success) && $cancel_success): ?>
        <div id="cancelSuccessToast"
            class="fixed bottom-6 right-6 bg-green-600 text-white px-6 py-3 rounded-lg shadow-xl flex items-center gap-3 z-[9999] animate-slide-up">
            <i class="fa-solid fa-circle-check text-lg"></i>
            <span class="font-medium"><?= __("order_cancelled_success") ?></span>
        </div>
    <?php endif; ?>

    <!-- Toast: Hủy thất bại / Lỗi -->
    <?php if ($cancel_error): ?>
        <div id="cancelErrorToast"
            class="fixed bottom-6 right-6 bg-red-600 text-white px-6 py-3 rounded-lg shadow-xl flex items-center gap-3 z-[9999] animate-slide-up">
            <i class="fa-solid fa-triangle-exclamation text-lg"></i>
            <span class="font-medium"><?= htmlspecialchars($cancel_error) ?></span>
        </div>
    <?php endif; ?>
</div><!-- end inner padding div -->
</div><!-- end max-width wrapper -->

<!-- ========== REORDER TOAST ========== -->
<div id="reorderToast" style="display:none;position:fixed;bottom:24px;right:24px;z-index:9999;
    background:#6366f1;color:#fff;padding:14px 20px;border-radius:14px;
    box-shadow:0 8px 30px rgba(99,102,241,0.35);font-size:14px;font-weight:600;
    display:flex;align-items:center;gap:10px;max-width:320px;opacity:0;transition:opacity .3s">
    <i class="fa-solid fa-cart-shopping text-lg"></i>
    <span id="reorderToastMsg"><?= __("adding_to_cart") ?></span>
</div>

<script>
async function reorderItems(btn, productIds) {
    // Disable button
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + <?= json_encode(__("adding")) ?>;

    const toast = document.getElementById('reorderToast');
    const toastMsg = document.getElementById('reorderToastMsg');
    toast.style.display = 'flex';
    setTimeout(() => toast.style.opacity = '1', 10);
    toastMsg.textContent = <?= json_encode(__("adding_items_count")) ?>.replace('{count}', productIds.length);

    let added = 0;
    for (const id of productIds) {
        try {
            const res = await fetch('add_to_cart.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'ajax=1&id=' + id
            });
            const data = await res.json();
            if (data.success) {
                added++;
                // Update cart badge
                const badge = document.querySelector('.cart-count, [data-cart-count], #cart-count, .badge-cart');
                if (badge && data.cart_count !== undefined) badge.textContent = data.cart_count;
            } else if (data.message === 'not_logged_in') {
                toastMsg.textContent = <?= json_encode(__("login_required_reorder")) ?>;
                setTimeout(() => { toast.style.opacity='0'; setTimeout(()=>toast.style.display='none',400); }, 2500);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> <?= __("reorder") ?>';
                return;
            }
        } catch(e) {}
    }

    toastMsg.textContent = <?= json_encode(__("added_items_success")) ?>.replace('{added}', added).replace('{total}', productIds.length);
    btn.innerHTML = '<i class="fa-solid fa-check"></i> ' + <?= json_encode(__("added_to_cart")) ?>;
    btn.style.background = '#10b981';

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.style.display = 'none', 400);
    }, 3000);

    // Redirect to cart after short delay
    setTimeout(() => { window.location.href = 'cart.php'; }, 1200);
}
</script>

<!-- =========================================================
     MODAL XÁC NHẬN HỦY ĐƠN
     =========================================================
     Hiển thị popup hỏi lại người dùng trước khi thực sự gửi POST request hủy đơn
-->
<div id="cancelModal"
    class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 text-center transform scale-95 opacity-0 transition-all duration-300"
        id="cancelModalContent">
        <div
            class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2"><?= __("confirm_cancel_title") ?></h3>
        <p class="text-gray-600 mb-1"><?= __("confirm_cancel_msg") ?>:</p>
        <p class="text-2xl font-extrabold text-red-600 mb-4">#<span id="cancelOrderId"></span></p>
        <p class="text-sm text-gray-500 mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
            <i class="fa-solid fa-circle-exclamation text-yellow-500 mr-1"></i>
            <?= __("confirm_cancel_warning") ?>
        </p>
        <div class="flex gap-3 justify-center">
            <button onclick="closeCancelModal()"
                class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg font-bold hover:bg-gray-200 transition border border-gray-200 shadow-sm"><?= __("no_keep_it") ?></button>
            <form method="POST"
                action="track_order.php<?= $search_query ? '?q=' . urlencode($search_query) : '?status=' . $status_filter ?>"
                id="cancelForm">
                <input type="hidden" name="cancel_order_id" id="cancelOrderInput" value="">
                <?= csrf_input_field() ?>
                <button type="submit"
                    class="px-6 py-2.5 bg-red-600 text-white rounded-lg font-bold hover:bg-red-700 transition shadow-md"><i
                        class="fa-solid fa-ban mr-1"></i> <?= __("confirm_cancel") ?></button>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================
     MODAL YÊU CẦU BẢO HÀNH KHI SẢN PHẨM BỊ LỖI
     ========================================================= -->
<div id="warrantyModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 transform scale-95 opacity-0 transition-all duration-300 relative" id="warrantyModalContent">
        <button onclick="closeWarrantyModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-xl"></i></button>
        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-2xl mb-4">
            <i class="fa-solid fa-wrench"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2"><?= __("warranty_request") ?></h3>
        <p class="text-gray-500 text-sm mb-4"><?= __("warranty_request_desc") ?></p>
        
        <form method="POST" action="track_order.php<?= isset($_GET['status']) ? '?status=' . htmlspecialchars($_GET['status']) : '' ?>" enctype="multipart/form-data">
            <?= csrf_input_field() ?>
            <input type="hidden" name="action" value="request_warranty">
            <input type="hidden" name="order_id" id="warrantyOrderId">
            <input type="hidden" name="product_id" id="warrantyProductId">
            <div class="mb-4 text-left">
                <div class="bg-blue-50/50 border border-blue-100 rounded-xl p-3 mb-4 flex gap-3 items-start">
                    <i class="fa-solid fa-shield-halved text-blue-500 text-xl mt-0.5"></i>
                    <div>
                        <h4 class="text-xs font-bold text-blue-800 uppercase tracking-wide mb-0.5"><?= __('warranty_policy_title') ?></h4>
                        <p class="text-[11px] text-blue-600 leading-tight"><?= __('warranty_policy_desc') ?></p>
                    </div>
                </div>

                <label class="block text-xs font-bold text-gray-700 mb-1.5 uppercase tracking-wide"><?= __('error_type_label') ?> <span class="text-red-500">*</span></label>
                <div class="relative mb-4">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa-solid fa-layer-group text-gray-400"></i>
                    </div>
                    <select name="error_type" class="w-full border border-gray-200 rounded-xl pl-9 pr-3 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white shadow-sm appearance-none cursor-pointer transition hover:border-blue-300" required>
                        <option value="" disabled selected><?= __('error_type_placeholder') ?></option>
                        <option value="<?= __('err_hardware') ?>"><?= __('err_hardware') ?></option>
                        <option value="<?= __('err_software') ?>"><?= __('err_software') ?></option>
                        <option value="<?= __('err_physical') ?>"><?= __('err_physical') ?></option>
                        <option value="<?= __('err_other') ?>"><?= __('err_other') ?></option>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <i class="fa-solid fa-chevron-down text-gray-400 text-xs"></i>
                    </div>
                </div>

                <label class="block text-sm font-bold text-gray-700 mb-1"><?= __("error_description") ?> <span class="text-red-500">*</span></label>
                <textarea name="reason" rows="3" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="<?= __("error_desc_placeholder") ?>" required></textarea>
            </div>
            <!-- Upload bằng chứng ảnh/video -->
            <div class="mb-4 text-left">
                <label class="block text-sm font-bold text-gray-700 mb-1"><i class="fa-solid fa-camera mr-1 text-blue-500"></i><?= __("media_upload_label") ?></label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-3 text-center cursor-pointer hover:border-blue-400 hover:bg-blue-50/30 transition relative">
                    <input type="file" name="warranty_media[]" multiple accept="image/*,video/mp4,video/webm" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="previewModalMedia(this, 'warranty-preview')">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-gray-300 mb-1"></i>
                    <p class="text-xs text-gray-500"><?= __("drag_drop_text_1") ?> <span class="text-blue-600 font-medium"><?= __("drag_drop_text_2") ?></span> <?= __("drag_drop_text_3") ?></p>
                </div>
                <div id="warranty-preview" class="flex flex-wrap gap-2 mt-2"></div>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition"><?= __("submit_request") ?></button>
        </form>
    </div>
</div>

<!-- =========================================================
     MODAL YÊU CẦU TRẢ HÀNG / HOÀN TIỀN
     ========================================================= -->
<div id="returnModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 transform scale-95 opacity-0 transition-all duration-300 relative" id="returnModalContent">
        <button onclick="closeReturnModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-xl"></i></button>
        <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center text-2xl mb-4">
            <i class="fa-solid fa-right-left"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2"><?= __("return_refund") ?></h3>
        <p class="text-gray-500 text-sm mb-4"><?= __("return_request_desc_prefix") ?> #<span id="returnOrderIdView" class="font-bold"></span>.</p>
        
        <form method="POST" action="track_order.php<?= isset($_GET['status']) ? '?status=' . htmlspecialchars($_GET['status']) : '' ?>" enctype="multipart/form-data">
            <?= csrf_input_field() ?>
            <input type="hidden" name="action" value="request_return">
            <input type="hidden" name="order_id" id="returnOrderId">
            <div class="mb-3 text-left">
                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wide"><?= __('return_type_label') ?> <span class="text-red-500">*</span></label>
                <div class="relative mb-3">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa-solid fa-box-open text-gray-400"></i>
                    </div>
                    <select name="return_type" class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none bg-white shadow-sm appearance-none cursor-pointer transition" required>
                        <option value="" disabled selected><?= __('return_type_placeholder') ?></option>
                        <option value="<?= __('ret_defective') ?>"><?= __('ret_defective') ?></option>
                        <option value="<?= __('ret_wrong_item') ?>"><?= __('ret_wrong_item') ?></option>
                        <option value="<?= __('ret_damaged') ?>"><?= __('ret_damaged') ?></option>
                        <option value="<?= __('ret_missing') ?>"><?= __('ret_missing') ?></option>
                        <option value="<?= __('ret_other') ?>"><?= __('ret_other') ?></option>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <i class="fa-solid fa-chevron-down text-gray-400 text-xs"></i>
                    </div>
                </div>
                
                <!-- Khu vực nhập Bank thu gọn -->
                <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-indigo-600 via-purple-600 to-fuchsia-700 p-3 mb-3 shadow-md text-white">
                    <div class="absolute top-0 right-0 opacity-10 transform translate-x-2 -translate-y-2">
                        <i class="fa-brands fa-cc-visa text-7xl"></i>
                    </div>
                    <div class="relative z-10">
                        <div class="flex justify-between items-center mb-2.5">
                            <p class="text-[9px] font-bold uppercase tracking-wider text-indigo-100"><i class="fa-solid fa-building-columns mr-1"></i> <?= __('refund_info_title') ?></p>
                            <i class="fa-solid fa-microchip text-xl text-yellow-300 opacity-90 drop-shadow-md"></i>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <div class="relative group">
                                <input type="text" name="bank_name" placeholder="<?= __('bank_name_placeholder') ?>" class="w-full bg-white/10 border border-white/20 rounded p-1.5 text-xs text-white placeholder-white/60 outline-none focus:border-white/60 focus:bg-white/20 transition backdrop-blur-md" required>
                            </div>
                            <div class="relative group">
                                <input type="text" name="bank_account" placeholder="<?= __('bank_account_placeholder') ?>" class="w-full bg-white/10 border border-white/20 rounded p-1.5 text-xs text-white placeholder-white/60 outline-none focus:border-white/60 focus:bg-white/20 transition font-mono tracking-wider backdrop-blur-md" required>
                            </div>
                        </div>
                        <div class="relative group">
                            <input type="text" name="bank_owner" placeholder="<?= __('bank_owner_placeholder') ?>" class="w-full bg-white/10 border border-white/20 rounded p-1.5 text-xs text-white placeholder-white/60 outline-none focus:border-white/60 focus:bg-white/20 transition uppercase backdrop-blur-md font-bold" required>
                        </div>
                    </div>
                </div>

                <textarea name="reason" rows="2" class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-purple-500 outline-none mb-3" placeholder="<?= __("return_desc_placeholder") ?>" required></textarea>
            </div>
            <!-- Upload bằng chứng ảnh/video -->
            <div class="mb-4 text-left">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-2 text-center cursor-pointer hover:border-purple-400 hover:bg-purple-50/30 transition relative">
                    <input type="file" name="return_media[]" multiple accept="image/*,video/mp4,video/webm" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="previewModalMedia(this, 'return-preview')">
                    <i class="fa-solid fa-camera text-xl text-purple-400 mb-1"></i>
                    <p class="text-[10px] text-gray-500"><?= __('upload_media_desc') ?></p>
                </div>
                <div id="return-preview" class="flex flex-wrap gap-1 mt-1"></div>
            </div>
            <button type="submit" class="w-full bg-purple-600 text-white font-bold py-2.5 rounded-lg hover:bg-purple-700 transition shadow-md"><?= __("submit_return_request") ?></button>
        </form>
    </div>
</div>

<!-- =========================================================
     MODAL LIGHTBOX XEM ẢNH/VIDEO BẰNG CHỨNG (Timeline)
     ========================================================= -->
<div id="timelineMediaViewer" class="hidden fixed inset-0 bg-black/80 z-[200] flex items-center justify-center backdrop-blur-sm p-4" onclick="closeTimelineMedia(event)">
    <button onclick="document.getElementById('timelineMediaViewer').classList.add('hidden')" class="absolute top-4 right-4 text-white/80 hover:text-white text-3xl z-10 w-10 h-10 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
    <div id="timelineMediaContent" class="max-w-4xl max-h-[85vh] flex items-center justify-center"></div>
</div>

<style>
    @keyframes slideUp {
        from {
            transform: translateY(30px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .animate-slide-up {
        animation: slideUp 0.4s ease-out;
    }

    /* =============================================
       TIMELINE COMPONENT - Đường thời gian trực quan
       ============================================= */
    .timeline-container {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 8px 0;
        position: relative;
    }

    .timeline-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        position: relative;
        z-index: 1;
    }

    /* Dot (circle) chứa icon */
    .timeline-dot {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #f3f4f6;
        border: 3px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: #9ca3af;
        position: relative;
        z-index: 2;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Đường nối giữa các bước */
    .timeline-line {
        position: absolute;
        top: 21px;
        left: calc(50% + 21px);
        width: calc(100% - 42px);
        height: 3px;
        background: #e5e7eb;
        z-index: 0;
        transition: background 0.5s ease;
    }

    .timeline-line.done {
        background: linear-gradient(90deg, #3b82f6, #60a5fa);
    }

    /* Nhãn bên dưới dot */
    .timeline-label {
        margin-top: 10px;
        font-size: 11px;
        font-weight: 600;
        color: #9ca3af;
        text-align: center;
        max-width: 100px;
        line-height: 1.3;
        transition: color 0.3s ease;
    }

    /* === TRẠNG THÁI: Hoàn thành (done) === */
    .timeline-step.done .timeline-dot {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border-color: #93c5fd;
        color: #fff;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    }

    .timeline-step.done .timeline-label {
        color: #1d4ed8;
    }

    /* === TRẠNG THÁI: Đang active (bước hiện tại) === */
    .timeline-step.active .timeline-dot {
        animation: pulseActive 2s infinite;
        box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.15);
    }

    @keyframes pulseActive {
        0%, 100% { box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15); }
        50% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0.08); }
    }

    /* === TRẠNG THÁI: Bước cuối = Hoàn thành (xanh lá) === */
    .timeline-step.done:last-child .timeline-dot {
        background: linear-gradient(135deg, #22c55e, #16a34a);
        border-color: #86efac;
        box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
    }
    .timeline-step.done:last-child .timeline-label {
        color: #15803d;
    }

    /* === TRẠNG THÁI: Từ chối (đỏ) === */
    .timeline-step.rejected .timeline-dot {
        background: linear-gradient(135deg, #ef4444, #dc2626) !important;
        border-color: #fca5a5 !important;
        color: #fff !important;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3) !important;
    }
    .timeline-step.rejected .timeline-label {
        color: #dc2626 !important;
    }

    /* === RESPONSIVE: Mobile vertical layout === */
    @media (max-width: 500px) {
        .timeline-container {
            flex-direction: column;
            align-items: flex-start;
            gap: 0;
            padding-left: 20px;
        }
        .timeline-step {
            flex-direction: row;
            align-items: center;
            gap: 12px;
            padding-bottom: 0;
        }
        .timeline-dot {
            width: 36px;
            height: 36px;
            font-size: 12px;
            flex-shrink: 0;
        }
        .timeline-line {
            position: absolute;
            top: 36px;
            left: 18px;
            width: 3px !important;
            height: 28px;
        }
        .timeline-label {
            margin-top: 0;
            text-align: left;
            max-width: none;
            font-size: 12px;
        }
    }
</style>

<script>
    /**
     * Mở modal xác nhận hủy đơn với hiệu ứng scale + fade in
     * @param {number} orderId - Mã đơn hàng cần hủy
     */
    function openCancelModal(orderId) {
        document.getElementById('cancelOrderId').textContent = orderId;
        document.getElementById('cancelOrderInput').value = orderId;

        const modal = document.getElementById('cancelModal');
        const content = document.getElementById('cancelModalContent');

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        setTimeout(() => {
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    /** 
     * Đóng modal hủy đơn với hiệu ứng scale out + fade 
     * Xử lý: Xóa class hiển thị, đẩy class ẩn vào để tạo hiệu ứng biến mất từ từ.
     * Chờ 300ms sau đó mới thực sự ẩn element đi để đảm bảo animation mượt mà.
     */
    function closeCancelModal() {
        const content = document.getElementById('cancelModalContent');
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');

        setTimeout(() => {
            const modal = document.getElementById('cancelModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }, 300);
    }

    /** Mở modal bảo hành */
    function openWarrantyModal(orderId, productId) {
        document.getElementById('warrantyOrderId').value = orderId;
        document.getElementById('warrantyProductId').value = productId;
        const modal = document.getElementById('warrantyModal');
        const content = document.getElementById('warrantyModalContent');
        modal.classList.remove('hidden'); modal.classList.add('flex');
        setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); content.classList.add('scale-100', 'opacity-100'); }, 10);
    }

    function closeWarrantyModal() {
        const content = document.getElementById('warrantyModalContent');
        content.classList.remove('scale-100', 'opacity-100'); content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { const modal = document.getElementById('warrantyModal'); modal.classList.remove('flex'); modal.classList.add('hidden'); }, 300);
    }

    /** Mở modal Đổi trả */
    function openReturnModal(orderId) {
        document.getElementById('returnOrderId').value = orderId;
        document.getElementById('returnOrderIdView').textContent = orderId;
        const modal = document.getElementById('returnModal');
        const content = document.getElementById('returnModalContent');
        modal.classList.remove('hidden'); modal.classList.add('flex');
        setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); content.classList.add('scale-100', 'opacity-100'); }, 10);
    }

    function closeReturnModal() {
        const content = document.getElementById('returnModalContent');
        content.classList.remove('scale-100', 'opacity-100'); content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { const modal = document.getElementById('returnModal'); modal.classList.remove('flex'); modal.classList.add('hidden'); }, 300);
    }

    // Bắt sự kiện: Đóng modal khi click vào vùng overlay đen mờ (phía ngoài modal content)
    document.getElementById('cancelModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeCancelModal();
    });
    document.getElementById('warrantyModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeWarrantyModal();
    });
    document.getElementById('returnModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeReturnModal();
    });

    // Tự động ẩn toast thông báo kết quả sau vài giây
    setTimeout(() => {
        document.getElementById('cancelSuccessToast')?.remove();
        document.getElementById('cancelErrorToast')?.remove();
        document.getElementById('actionSuccessToast')?.remove();
        document.getElementById('actionErrorToast')?.remove();
    }, 5000);

    /**
     * PREVIEW MEDIA TRONG MODAL BẢO HÀNH / ĐỔI TRẢ
     * Hiển thị ảnh/video xem trước sau khi chọn file upload.
     * Tái sử dụng logic từ product_detail.php.
     */
    function previewModalMedia(input, previewId) {
        const preview = document.getElementById(previewId);
        preview.innerHTML = '';
        if (input.files.length > 5) {
            alert(<?= json_encode(__("max_files_limit")) ?>);
            input.value = '';
            return;
        }
        Array.from(input.files).forEach((file) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'relative w-16 h-16 rounded-lg overflow-hidden border-2 border-gray-200 shadow-sm';
            if (file.type.startsWith('video/')) {
                const video = document.createElement('video');
                video.src = URL.createObjectURL(file);
                video.className = 'w-full h-full object-cover';
                wrapper.appendChild(video);
                const playIcon = document.createElement('div');
                playIcon.className = 'absolute inset-0 bg-black/30 flex items-center justify-center';
                playIcon.innerHTML = '<i class="fa-solid fa-play text-white text-xs"></i>';
                wrapper.appendChild(playIcon);
            } else {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.className = 'w-full h-full object-cover';
                wrapper.appendChild(img);
            }
            const sizeBadge = document.createElement('div');
            sizeBadge.className = 'absolute bottom-0 left-0 right-0 bg-black/60 text-white text-[8px] text-center py-0.5';
            sizeBadge.innerText = (file.size / 1024 / 1024).toFixed(1) + ' MB';
            wrapper.appendChild(sizeBadge);
            preview.appendChild(wrapper);
        });
    }

    /**
     * LIGHTBOX VIEWER: XEM TO ẢNH/VIDEO ĐÍNH KÈM
     */
    function openTimelineMedia(src, isVideo) {
        const modal = document.getElementById('timelineMediaViewer');
        const content = document.getElementById('timelineMediaContent');
        modal.classList.remove('hidden');
        if (isVideo) {
            content.innerHTML = '<video src="' + src + '" controls autoplay class="max-w-full max-h-[85vh] rounded-lg"></video>';
        } else {
            content.innerHTML = '<img src="' + src + '" class="max-w-full max-h-[85vh] rounded-lg shadow-2xl">';
        }
    }
    function closeTimelineMedia(e) {
        if (e.target === document.getElementById('timelineMediaViewer')) {
            document.getElementById('timelineMediaViewer').classList.add('hidden');
        }
    }
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>