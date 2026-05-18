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
            $reason = trim($_POST['reason']);
            
            if (empty($reason)) {
                $action_msg = __("warranty_reason_empty");
            } else {
                // Kiểm tra xem đơn hàng đã có yêu cầu trả hàng/hoàn tiền chưa để tránh xung đột logic
                $stmt_check_return = $db->prepare("SELECT id FROM returns WHERE order_id = ?");
                $stmt_check_return->execute([$order_id]);
                if ($stmt_check_return->fetch()) {
                    $action_success = false;
                    $action_msg = __("warranty_conflict_return");
                } else {
                    // --- XỬ LÝ UPLOAD MEDIA ĐÍNH KÈM (tái sử dụng pattern từ product_detail.php) ---
                    $media_json = processMediaUpload('warranty_media', 'uploads/warranties/');
                    addWarrantyRequest($db, $order_id, $product_id, $user_id, $reason, $media_json);
                    $action_success = true;
                    $action_msg = __("warranty_request_success");
                }
            }
        } elseif ($action === 'request_return') {
            $order_id = $_POST['order_id'];
            $reason = trim($_POST['reason']);
            
            if (empty($reason)) {
                $action_msg = __("return_reason_empty");
            } else {
                // Kiểm tra xem đơn hàng đã có sản phẩm nào yêu cầu bảo hành chưa để tránh xung đột logic
                $stmt_check_warranty = $db->prepare("SELECT id FROM warranties WHERE order_id = ?");
                $stmt_check_warranty->execute([$order_id]);
                if ($stmt_check_warranty->fetch()) {
                    $action_success = false;
                    $action_msg = __("return_conflict_warranty");
                } else {
                    // --- XỬ LÝ UPLOAD MEDIA ĐÍNH KÈM ---
                    $media_json = processMediaUpload('return_media', 'uploads/returns/');
                    addReturnRequest($db, $order_id, $user_id, $reason, $media_json);
                    $action_success = true;
                    $action_msg = __("return_request_success");
                }
            }
        }
    }
}

// === TẢI DỮ LIỆU ĐƠN HÀNG ===
$search_query = isset($_GET['q']) ? trim($_GET['q']) : (isset($_GET['id']) ? trim($_GET['id']) : ''); // Mã đơn hoặc SĐT
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all'; // Tab trạng thái đang chọn
$orders = [];     // Mảng kết quả đơn hàng
$error = '';      // Thông báo lỗi/rỗng

/**
 * TRƯỜNG HỢP 1: TÌM KIẾM CHỦ ĐỘNG
 * Nếu khách hàng nhập mã hoặc số điện thoại vào ô tìm kiếm thì bỏ qua đăng nhập.
 * Dùng cho mọi đối tượng khách (Guest hoặc User).
 */
if ($search_query !== '') {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? OR phone = ? ORDER BY id DESC");
    $stmt->execute([$search_query, $search_query]);
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

$returned_order_ids = [];
$warrantied_order_ids = [];

/**
 * TRUY VẤN CHI TIẾT SẢN PHẨM CỦA ĐƠN HÀNG
 * Nếu đã lấy được danh sách orders (bất luận từ TH1 hay TH2), 
 * tiếp tục join vào bảng order_details và products để lấy thông tin, hình ảnh món hàng.
 */
if (!empty($orders)) {
    $order_ids = array_column($orders, 'id');
    $in_clause = implode(',', array_map('intval', $order_ids));
    
    // Lấy tất cả ID đơn hàng đã có trong returns của các đơn hàng này
    $stmt_ret_exist = $db->query("SELECT DISTINCT order_id FROM returns WHERE order_id IN ($in_clause)");
    if ($stmt_ret_exist) {
        $returned_order_ids = array_column($stmt_ret_exist->fetchAll(PDO::FETCH_ASSOC), 'order_id');
    }
    
    // Lấy tất cả ID đơn hàng đã có trong warranties của các đơn hàng này
    $stmt_war_exist = $db->query("SELECT DISTINCT order_id FROM warranties WHERE order_id IN ($in_clause)");
    if ($stmt_war_exist) {
        $warrantied_order_ids = array_column($stmt_war_exist->fetchAll(PDO::FETCH_ASSOC), 'order_id');
    }

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

<div class="container mx-auto px-4 py-10 max-w-5xl min-h-[60vh]">
    <!-- TIÊU ĐỀ TRANG -->
    <div class="text-center mb-8">
        <h1 class="text-3xl font-extrabold text-primary mb-3"><?= __("track_order_title") ?></h1>
        <p class="text-gray-600"><?= __("track_order_desc") ?></p>
    </div>

    <!-- FORM TÌM KIẾM -->
    <div
        class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 mb-8 max-w-2xl mx-auto relative z-10 overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary to-secondary"></div>
        <form action="track_order.php" method="GET" class="flex flex-col md:flex-row gap-3">
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>"
                    placeholder="<?= __("track_order_placeholder") ?>"
                    class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none transition font-medium text-gray-800">
            </div>
            <button type="submit"
                class="bg-primary text-white font-bold px-8 py-3 rounded-lg hover:bg-blue-800 transition shadow-md whitespace-nowrap">
                <?= __("track_now") ?>
            </button>
        </form>
    </div>

    <!-- TABS PHÂN LOẠI TRẠNG THÁI 
         Chỉ hiển thị khi: 
         1. Người dùng không dùng chức năng tìm kiếm chủ động ($search_query rỗng).
         2. Người dùng đã đăng nhập (có SESSION user_id). 
    -->
    <?php if ($search_query === '' && isset($_SESSION['user_id'])): ?>
        <div class="bg-white border-b border-gray-200 mb-6 sticky top-[60px] z-40 shadow-sm rounded-t-xl overflow-hidden">
            <div class="flex overflow-x-auto hide-scrollbar text-[14px] font-medium">
                <!-- Tab: Tất cả đơn hàng -->
                <a href="?status=all"
                    class="flex-1 min-w-[100px] text-center py-4 border-b-2 transition whitespace-nowrap <?= $status_filter === 'all' ? 'border-primary text-primary font-bold bg-blue-50/50' : 'border-transparent text-gray-500 hover:text-primary hover:bg-gray-50' ?>">
                    <?= __("all_orders") ?> (<?= $total_my_orders ?? 0 ?>)
                </a>
                <a href="?status=pending"
                    class="flex-1 min-w-[100px] text-center py-4 border-b-2 transition whitespace-nowrap <?= $status_filter === 'pending' ? 'border-primary text-primary font-bold bg-blue-50/50' : 'border-transparent text-gray-500 hover:text-primary hover:bg-gray-50' ?>">
                    <?= __("pending") ?> (<?= $status_counts['pending'] ?? 0 ?>)
                </a>
                <a href="?status=processing"
                    class="flex-1 min-w-[130px] text-center py-4 border-b-2 transition whitespace-nowrap <?= $status_filter === 'processing' ? 'border-primary text-primary font-bold bg-blue-50/50' : 'border-transparent text-gray-500 hover:text-primary hover:bg-gray-50' ?>">
                    <?= __("paid") ?> (<?= $status_counts['processing'] ?? 0 ?>)
                </a>
                <a href="?status=delivering"
                    class="flex-1 min-w-[100px] text-center py-4 border-b-2 transition whitespace-nowrap <?= $status_filter === 'delivering' ? 'border-primary text-primary font-bold bg-blue-50/50' : 'border-transparent text-gray-500 hover:text-primary hover:bg-gray-50' ?>">
                    <?= __("delivering") ?> (<?= $status_counts['delivering'] ?? 0 ?>)
                </a>
                <a href="?status=completed"
                    class="flex-1 min-w-[100px] text-center py-4 border-b-2 transition whitespace-nowrap <?= $status_filter === 'completed' ? 'border-primary text-primary font-bold bg-blue-50/50' : 'border-transparent text-gray-500 hover:text-primary hover:bg-gray-50' ?>">
                    <?= __("completed") ?> (<?= $status_counts['completed'] ?? 0 ?>)
                </a>
                <a href="?status=cancelled"
                    class="flex-1 min-w-[100px] text-center py-4 border-b-2 transition whitespace-nowrap <?= $status_filter === 'cancelled' ? 'border-primary text-primary font-bold bg-blue-50/50' : 'border-transparent text-gray-500 hover:text-primary hover:bg-gray-50' ?>">
                    <?= __("cancelled") ?> (<?= $status_counts['cancelled'] ?? 0 ?>)
                </a>
                <!-- TAB MỚI: LỊch sử Bảo hành -->
                <a href="?status=warranties"
                    class="flex-1 min-w-[130px] text-center py-4 border-b-2 transition whitespace-nowrap <?= $status_filter === 'warranties' ? 'border-yellow-500 text-yellow-600 font-bold bg-yellow-50/50' : 'border-transparent text-gray-500 hover:text-yellow-600 hover:bg-yellow-50/30' ?>">
                    <i class="fa-solid fa-wrench text-[11px] mr-1"></i><?= __("warranty") ?> (<?= count($user_warranties ?? []) ?>)
                </a>
                <!-- TAB MỚI: LỊch sử Đổi trả -->
                <a href="?status=returns"
                    class="flex-1 min-w-[130px] text-center py-4 border-b-2 transition whitespace-nowrap <?= $status_filter === 'returns' ? 'border-purple-500 text-purple-600 font-bold bg-purple-50/50' : 'border-transparent text-gray-500 hover:text-purple-600 hover:bg-purple-50/30' ?>">
                    <i class="fa-solid fa-right-left text-[11px] mr-1"></i><?= __("returns") ?> (<?= count($user_returns ?? []) ?>)
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- ========================================
         NỘI DUNG CHÍNH: Chuyển đổi theo tab
         ======================================== -->

    <!-- === TAB: LỊCH SỬ BẢO HÀNH (Timeline) === -->
    <?php if ($status_filter === 'warranties' && isset($_SESSION['user_id'])): ?>
        <?php if (empty($user_warranties)): ?>
            <div class="bg-white p-10 rounded-xl border border-gray-200 text-center shadow-sm">
                <div class="w-24 h-24 bg-yellow-50 text-yellow-400 rounded-full flex items-center justify-center text-4xl mx-auto mb-4">
                    <i class="fa-solid fa-wrench"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-2"><?= __("no_warranty_requests") ?></h3>
                <p class="text-gray-500 mb-6"><?= __("no_warranty_desc") ?></p>
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
                                <div class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($w['product_name'] ?? 'Sản phẩm') ?></div>
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
            <div class="bg-white p-10 rounded-xl border border-gray-200 text-center shadow-sm">
                <div class="w-24 h-24 bg-purple-50 text-purple-400 rounded-full flex items-center justify-center text-4xl mx-auto mb-4">
                    <i class="fa-solid fa-right-left"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-2"><?= __("no_return_requests") ?></h3>
                <p class="text-gray-500 mb-6"><?= __("no_return_desc") ?></p>
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
            <div class="bg-white p-10 rounded-xl border border-gray-200 text-center shadow-sm">
                <div
                    class="w-24 h-24 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center text-4xl mx-auto mb-4">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-2"><?= __("no_orders") ?></h3>
                <p class="text-gray-500 mb-6"><?= $error ? $error : __("no_orders_status") ?></p>
                <a href="index.php"
                    class="inline-block bg-primary text-white font-bold px-8 py-3 rounded-lg hover:bg-blue-800 transition shadow-md">
                    <?= __("continue_shopping") ?>
                </a>
            </div>

        <?php else: ?>
            <?php if ($search_query !== ''): ?>
                <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">
                    <?= __("search_results_found") ?> <?= count($orders) ?> <?= __("orders_count") ?>
                </h3>
            <?php endif; ?>

            <div class="space-y-6">
                <?php foreach ($orders as $order):
                    $ui = getStatusUI($order['status']);
                    ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden transition hover:shadow-md">
                        <!-- Header Đơn Hàng -->
                        <div
                            class="bg-gray-50 p-4 md:p-5 border-b border-gray-200 flex flex-col md:flex-row justify-between md:items-center gap-4">
                            <div>
                                <div class="flex items-center gap-3 mb-1">
                                    <span class="font-bold text-gray-800 text-lg"><?= __("order") ?> #<?= $order['id'] ?></span>
                                    <span
                                        class="<?= $ui['bg'] ?> <?= $ui['text'] ?> <?= $ui['border'] ?> border px-2.5 py-1 rounded text-[11px] font-bold uppercase tracking-wider flex items-center gap-1.5">
                                        <i class="fa-solid <?= $ui['icon'] ?>"></i> <?= $ui['label'] ?>
                                    </span>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?= __("ordered_at") ?>: <b
                                        class="text-gray-700"><?= date('H:i - d/m/Y', strtotime($order['created_at'])) ?></b>
                                </div>
                            </div>
                            <div class="text-left md:text-right">
                                <div class="text-sm text-gray-500 mb-1"><?= __("total_price") ?>:</div>
                                <div class="font-extrabold text-danger text-xl"><?= number_format($order['total_price']) ?>đ</div>
                            </div>
                        </div>

                        <div class="p-4 md:p-5 grid grid-cols-1 md:grid-cols-3 gap-6">

                            <!-- CỘT 1: Thông Tin Nhận Hàng (Tên, SĐT, Địa chỉ, Ghi chú) -->
                            <div class="md:col-span-1 space-y-3 text-sm">
                                <h4 class="font-bold text-gray-800 border-b border-gray-100 pb-2"><i
                                        class="fa-solid fa-address-card text-primary mr-1"></i> <?= __("shipping_info") ?></h4>
                                <p><span class="text-gray-500 inline-block w-20"><?= __("fullname") ?>:</span> <b
                                        class="text-gray-800"><?= htmlspecialchars($order['fullname']) ?></b></p>
                                <p><span class="text-gray-500 inline-block w-20"><?= __("phone") ?>:</span> <b
                                        class="text-gray-800"><?= htmlspecialchars($order['phone']) ?></b></p>
                                <p class="flex"><span class="text-gray-500 inline-block w-20 shrink-0"><?= __("address") ?>:</span> <span
                                        class="text-gray-800 leading-snug"><?= htmlspecialchars($order['address']) ?></span></p>
                                <?php if ($order['note']): ?>
                                    <p class="flex"><span class="text-gray-500 inline-block w-20 shrink-0"><?= __("note") ?>:</span> <span
                                            class="text-gray-600 bg-yellow-50 px-2 py-1 rounded w-full border border-yellow-100 leading-snug"><?= htmlspecialchars($order['note']) ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- CỘT 2 & 3: Danh Sách Sản Phẩm (Hình ảnh, Tên, Số lượng, Giá) -->
                            <div class="md:col-span-2">
                                <h4 class="font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3"><i
                                        class="fa-solid fa-box-open text-primary mr-1"></i> <?= __("ordered_products") ?></h4>
                                <div class="space-y-3 max-h-[250px] overflow-y-auto pr-2 hide-scrollbar">
                                    <?php foreach ($order['details'] as $item): ?>
                                        <div class="flex items-start gap-3 bg-white p-2 rounded-lg border border-gray-100">
                                            <div
                                                class="w-16 h-16 shrink-0 bg-gray-50 border border-gray-200 rounded p-1 flex justify-center items-center">
                                                <img src="<?= htmlspecialchars($item['image']) ?>"
                                                    class="max-w-full max-h-full object-contain">
                                            </div>
                                            <div class="flex-1 flex flex-col justify-between py-1">
                                                <a href="product_detail.php?id=<?= $item['product_id'] ?>"
                                                    class="font-medium text-[13px] text-gray-800 hover:text-primary leading-tight mb-1 line-clamp-2">
                                                    <?= htmlspecialchars($item['name']) ?>
                                                </a>
                                                <div class="flex justify-between items-center text-sm">
                                                    <span class="text-gray-500 font-medium"><?= __("qty") ?>: <?= $item['quantity'] ?></span>
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-bold text-gray-800"><?= number_format($item['price']) ?>đ</span>
                                                        <?php if ($order['status'] === 'completed'): ?>
                                                            <?php if (in_array($order['id'], $returned_order_ids)): ?>
                                                                <span class="text-[11px] text-red-600 bg-red-50 border border-red-200 px-2 py-0.5 rounded font-bold uppercase tracking-wide animate-pulse" title="Đơn hàng đang yêu cầu trả hàng hoàn tiền (không thể bảo hành)"><i class="fa-solid fa-ban mr-1"></i>Đang trả hàng</span>
                                                            <?php else: ?>
                                                                <button type="button" onclick="openWarrantyModal(<?= $order['id'] ?>, <?= $item['product_id'] ?>)" class="text-[11px] bg-white border border-gray-300 text-gray-600 px-2 py-0.5 rounded hover:bg-gray-50 hover:text-blue-600 transition" title="<?= __("warranty") ?>"><i class="fa-solid fa-wrench"></i> <?= __("warranty") ?></button>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- VÙNG NÚT THAO TÁC (Tùy theo trạng thái đơn hàng) -->

                        <!-- Nếu đơn hàng đang chờ xử lý: Cho phép Thanh toán QR hoặc Hủy đơn -->
                        <?php if ($order['status'] === 'pending'): ?>
                            <div
                                class="bg-blue-50 px-5 py-3 border-t border-blue-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                                <span class="text-[13px] text-blue-800"><i class="fa-solid fa-circle-info mr-1"></i> <?= __("pending_order_hint") ?></span>
                                <div class="flex items-center gap-2 shrink-0">
                                    <a href="payment.php?order_id=<?= $order['id'] ?>"
                                        class="text-[12px] bg-primary text-white px-4 py-1.5 rounded font-bold hover:bg-blue-800 transition shadow-sm whitespace-nowrap"><?= __("pay_qr") ?></a>
                                    <button type="button" onclick="openCancelModal(<?= $order['id'] ?>)"
                                        class="text-[12px] bg-white text-red-600 border border-red-300 px-4 py-1.5 rounded font-bold hover:bg-red-50 transition shadow-sm whitespace-nowrap"><i
                                            class="fa-solid fa-xmark mr-1"></i><?= __("cancel_order") ?></button>
                                </div>
                            </div>

                            <!-- Nếu đơn hàng đã bị hủy: Hiển thị thông báo khuyến khích đặt lại -->
                        <?php elseif ($order['status'] === 'cancelled'): ?>
                            <div class="bg-gray-50 px-5 py-3 border-t border-gray-200 text-center">
                                <span class="text-[13px] text-gray-500"><i class="fa-solid fa-clock-rotate-left mr-1"></i> <?= __("cancelled_order_hint") ?></span>
                            </div>

                            <!-- Nếu đơn hàng đã giao thành công: Cho phép đổi trả/hoàn tiền -->
                        <?php elseif ($order['status'] === 'completed'): ?>
                            <div class="bg-green-50 px-5 py-3 border-t border-green-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                                <?php if (in_array($order['id'], $warrantied_order_ids)): ?>
                                    <span class="text-[13px] text-yellow-800 font-bold"><i class="fa-solid fa-circle-exclamation mr-1 text-yellow-600"></i> Đơn hàng đang có sản phẩm yêu cầu bảo hành (không thể trả hàng).</span>
                                <?php else: ?>
                                    <span class="text-[13px] text-green-800"><i class="fa-solid fa-box-check mr-1"></i> <?= __("completed_order_hint") ?></span>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button type="button" onclick="openReturnModal(<?= $order['id'] ?>)" class="text-[12px] bg-white text-gray-700 border border-gray-300 px-4 py-1.5 rounded font-bold hover:bg-gray-100 transition shadow-sm whitespace-nowrap"><i class="fa-solid fa-right-left mr-1"></i><?= __("return_refund") ?></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

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
</div>

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
                <label class="block text-sm font-bold text-gray-700 mb-1"><?= __("error_description") ?> <span class="text-red-500">*</span></label>
                <textarea name="reason" rows="3" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="<?= __("error_desc_placeholder") ?>" required></textarea>
            </div>
            <!-- Upload bằng chứng ảnh/video -->
            <div class="mb-4 text-left">
                <label class="block text-sm font-bold text-gray-700 mb-1"><i class="fa-solid fa-camera mr-1 text-blue-500"></i>Đính kèm bằng chứng <span class="text-xs font-normal text-gray-400">(tối đa 5 file, ≤20MB/file)</span></label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-3 text-center cursor-pointer hover:border-blue-400 hover:bg-blue-50/30 transition relative">
                    <input type="file" name="warranty_media[]" multiple accept="image/*,video/mp4,video/webm" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="previewModalMedia(this, 'warranty-preview')">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-gray-300 mb-1"></i>
                    <p class="text-xs text-gray-500">Kéo thả hoặc <span class="text-blue-600 font-medium">bấm để chọn</span> ảnh/video</p>
                </div>
                <div id="warranty-preview" class="flex flex-wrap gap-2 mt-2"></div>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition">Gửi Yêu Cầu</button>
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
        <h3 class="text-xl font-bold text-gray-800 mb-2">Đổi Trả & Hoàn Tiền</h3>
        <p class="text-gray-500 text-sm mb-4">Vui lòng cung cấp lý do và ảnh/video chứng minh cho đơn hàng #<span id="returnOrderIdView" class="font-bold"></span>.</p>
        
        <form method="POST" action="track_order.php<?= isset($_GET['status']) ? '?status=' . htmlspecialchars($_GET['status']) : '' ?>" enctype="multipart/form-data">
            <?= csrf_input_field() ?>
            <input type="hidden" name="action" value="request_return">
            <input type="hidden" name="order_id" id="returnOrderId">
            <div class="mb-4 text-left">
                <label class="block text-sm font-bold text-gray-700 mb-1">Lý do trả hàng <span class="text-red-500">*</span></label>
                <textarea name="reason" rows="3" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Ví dụ: Giao sai sản phẩm, sản phẩm bị bể vỡ khi nhận..." required></textarea>
            </div>
            <!-- Upload bằng chứng ảnh/video -->
            <div class="mb-4 text-left">
                <label class="block text-sm font-bold text-gray-700 mb-1"><i class="fa-solid fa-camera mr-1 text-purple-500"></i>Đính kèm bằng chứng <span class="text-xs font-normal text-gray-400">(tối đa 5 file, ≤20MB/file)</span></label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-3 text-center cursor-pointer hover:border-purple-400 hover:bg-purple-50/30 transition relative">
                    <input type="file" name="return_media[]" multiple accept="image/*,video/mp4,video/webm" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="previewModalMedia(this, 'return-preview')">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-gray-300 mb-1"></i>
                    <p class="text-xs text-gray-500">Kéo thả hoặc <span class="text-purple-600 font-medium">bấm để chọn</span> ảnh/video</p>
                </div>
                <div id="return-preview" class="flex flex-wrap gap-2 mt-2"></div>
            </div>
            <button type="submit" class="w-full bg-purple-600 text-white font-bold py-3 rounded-lg hover:bg-purple-700 transition">Gửi Yêu Cầu Trả Hàng</button>
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
            alert('Tối đa 5 file!');
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