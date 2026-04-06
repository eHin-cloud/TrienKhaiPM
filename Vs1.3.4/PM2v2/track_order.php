<?php
session_start();
require_once 'database.php';

$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$orders = [];
$error = '';

// Nếu khách hàng nhập mã hoặc số điện thoại để tìm kiếm chủ động
if ($search_query !== '') {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? OR phone = ? ORDER BY id DESC");
    $stmt->execute([$search_query, $search_query]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        $error = 'Không tìm thấy đơn hàng nào khớp với mã hoặc số điện thoại: <b>' . htmlspecialchars($search_query) . '</b>';
    }
} 
// NẾU KHÔNG TÌM KIẾM MÀ KHÁCH ĐANG ĐĂNG NHẬP -> TỰ ĐỘNG HIỂN THỊ ĐƠN MUA CỦA KHÁCH
elseif (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orders)) {
        $error = 'Bạn chưa có đơn hàng nào trên hệ thống.';
    }
}

// Nếu có đơn hàng, nạp thêm chi tiết sản phẩm của từng đơn hàng đó
if (!empty($orders)) {
    foreach ($orders as &$order) {
        $stmt_details = $db->prepare("SELECT od.*, p.name, p.image FROM order_details od JOIN products p ON od.product_id = p.id WHERE od.order_id = ?");
        $stmt_details->execute([$order['id']]);
        $order['details'] = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
    }
}

require_once 'header.php';

// Hàm helper để render status badge
function getStatusUI($status) {
    switch ($status) {
        case 'pending':
            return ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'border' => 'border-yellow-200', 'label' => 'Chờ xử lý', 'icon' => 'fa-clock'];
        case 'processing':
            return ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'label' => 'Đã thanh toán', 'icon' => 'fa-money-check-dollar'];
        case 'delivering':
            return ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200', 'label' => 'Đang giao hàng', 'icon' => 'fa-truck-fast'];
        case 'completed':
            return ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'border' => 'border-green-200', 'label' => 'Giao thành công', 'icon' => 'fa-box-open'];
        case 'cancelled':
            return ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'border' => 'border-red-200', 'label' => 'Đã hủy', 'icon' => 'fa-ban'];
        default:
            return ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'border' => 'border-gray-200', 'label' => 'Không xác định', 'icon' => 'fa-circle-question'];
    }
}
?>

<div class="container mx-auto px-4 py-10 max-w-4xl min-h-[60vh]">
    <div class="text-center mb-8">
        <h1 class="text-3xl font-extrabold text-primary mb-3">Tra Cứu Đơn Hàng</h1>
        <p class="text-gray-600">Nhập Mã đơn hàng hoặc Số điện thoại đặt hàng để kiểm tra trạng thái</p>
    </div>

    <!-- FORM TÌM KIẾM -->
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-gray-200 mb-10 max-w-2xl mx-auto relative z-10 overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary to-secondary"></div>
        <form action="track_order.php" method="GET" class="flex flex-col md:flex-row gap-3">
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>" placeholder="VD: 1024 hoặc 0901234567" 
                       class="w-full pl-10 pr-4 py-3.5 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none transition font-medium text-gray-800">
            </div>
            <button type="submit" class="bg-primary text-white font-bold px-8 py-3.5 rounded-lg hover:bg-blue-800 transition shadow-md whitespace-nowrap">
                Tra cứu ngay
            </button>
        </form>
    </div>

    <!-- KẾT QUẢ DANH SÁCH ĐƠN HÀNG -->
    <?php if ($search_query !== '' || isset($_SESSION['user_id'])): ?>
        
        <?php if ($error): ?>
            <div class="bg-red-50 p-8 rounded-xl border border-red-200 text-center max-w-2xl mx-auto">
                <div class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h3 class="text-lg font-bold text-red-700 mb-2">Chưa có thông tin!</h3>
                <p class="text-red-600"><?= $error ?></p>
            </div>
        <?php elseif (!empty($orders)): ?>
            <h3 class="text-lg font-bold text-gray-800 mb-6 border-b pb-2">
                <?= $search_query !== '' ? "Đã tìm thấy " . count($orders) . " kết quả:" : "Lịch sử mua hàng của bạn (" . count($orders) . " đơn):" ?>
            </h3>
            
            <div class="space-y-6">
                <?php foreach ($orders as $order): 
                    $ui = getStatusUI($order['status']);
                ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden transition hover:shadow-md">
                    <!-- Order Header -->
                    <div class="bg-gray-50 p-4 md:p-5 border-b border-gray-200 flex flex-col md:flex-row justify-between md:items-center gap-4">
                        <div>
                            <div class="flex items-center gap-3 mb-1">
                                <span class="font-bold text-gray-800 text-lg">Đơn hàng #<?= $order['id'] ?></span>
                                <span class="<?= $ui['bg'] ?> <?= $ui['text'] ?> <?= $ui['border'] ?> border px-2.5 py-1 rounded text-[11px] font-bold uppercase tracking-wider flex items-center gap-1.5">
                                    <i class="fa-solid <?= $ui['icon'] ?>"></i> <?= $ui['label'] ?>
                                </span>
                            </div>
                            <div class="text-sm text-gray-500">
                                Đặt lúc: <b class="text-gray-700"><?= date('H:i - d/m/Y', strtotime($order['created_at'])) ?></b>
                            </div>
                        </div>
                        <div class="text-left md:text-right">
                            <div class="text-sm text-gray-500 mb-1">Tổng tiền:</div>
                            <div class="font-extrabold text-danger text-xl"><?= number_format($order['total_price']) ?>đ</div>
                        </div>
                    </div>

                    <div class="p-4 md:p-5 grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <!-- Order Detail -->
                        <div class="md:col-span-1 space-y-3 text-sm">
                            <h4 class="font-bold text-gray-800 border-b border-gray-100 pb-2"><i class="fa-solid fa-address-card text-primary mr-1"></i> Thông tin nhận hàng</h4>
                            <p><span class="text-gray-500 inline-block w-20">Họ tên:</span> <b class="text-gray-800"><?= htmlspecialchars($order['fullname']) ?></b></p>
                            <p><span class="text-gray-500 inline-block w-20">Điện thoại:</span> <b class="text-gray-800"><?= htmlspecialchars($order['phone']) ?></b></p>
                            <p class="flex"><span class="text-gray-500 inline-block w-20 shrink-0">Địa chỉ:</span> <span class="text-gray-800 leading-snug"><?= htmlspecialchars($order['address']) ?></span></p>
                            <?php if ($order['note']): ?>
                            <p class="flex"><span class="text-gray-500 inline-block w-20 shrink-0">Ghi chú:</span> <span class="text-gray-600 bg-yellow-50 px-2 py-1 rounded w-full border border-yellow-100 leading-snug"><?= htmlspecialchars($order['note']) ?></span></p>
                            <?php endif; ?>
                        </div>

                        <!-- Product List -->
                        <div class="md:col-span-2">
                            <h4 class="font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3"><i class="fa-solid fa-box-open text-primary mr-1"></i> Sản phẩm đã đặt</h4>
                            <div class="space-y-3 max-h-[250px] overflow-y-auto pr-2 hide-scrollbar">
                                <?php foreach ($order['details'] as $item): ?>
                                    <div class="flex items-start gap-3 bg-white p-2 rounded-lg border border-gray-100">
                                        <div class="w-16 h-16 shrink-0 bg-gray-50 border border-gray-200 rounded p-1 flex justify-center items-center">
                                            <img src="<?= htmlspecialchars($item['image']) ?>" class="max-w-full max-h-full object-contain">
                                        </div>
                                        <div class="flex-1 flex flex-col justify-between py-1">
                                            <a href="product_detail.php?id=<?= $item['product_id'] ?>" class="font-medium text-[13px] text-gray-800 hover:text-primary leading-tight mb-1 line-clamp-2">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                            <div class="flex justify-between items-center text-sm">
                                                <span class="text-gray-500 font-medium">SL: <?= $item['quantity'] ?></span>
                                                <span class="font-bold text-gray-800"><?= number_format($item['price']) ?>đ</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                    
                    <?php if ($order['status'] === 'pending'): ?>
                    <div class="bg-blue-50 px-5 py-3 border-t border-blue-100 flex justify-between items-center">
                        <span class="text-[13px] text-blue-800"><i class="fa-solid fa-circle-info mr-1"></i> Đơn hàng đang chờ xử lý. Bạn cũng có thể thanh toán trước qua mã QR.</span>
                        <a href="payment.php?order_id=<?= $order['id'] ?>" class="text-[12px] bg-primary text-white px-4 py-1.5 rounded font-bold hover:bg-blue-800 transition">Thanh toán QR</a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>