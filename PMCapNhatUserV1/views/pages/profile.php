<?php
use App\Service\UserService;

// 1. Kiểm tra đăng nhập (session đã được start ở index.php)
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); // Quay về trang chủ nếu chưa đăng nhập
    exit();
}

$userId = $_SESSION['user_id'];
// $db đã được khởi tạo toàn cục ở public/index.php qua core/database.php
$userService = new UserService($db);

// 2. Xử lý hành động POST
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $userService->handleAccountAction($_POST, $userId);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// 3. Lấy dữ liệu profile với phân trang
$page = isset($_GET['page']) && (int) $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$limit = 5; // Số đơn hàng mỗi trang
$profileData = $userService->getUserProfileData($userId, $page, $limit);

// BẠN CẦN ĐẢM BẢO CÓ ĐỦ 3 DÒNG NÀY (Đừng để thiếu dòng nào nhé):
$user = $profileData['user'];
$orders = $profileData['orders'];
$pagination = $profileData['pagination']; // <- Biến bị thiếu gây ra lỗi dòng 297

// 1. KIỂM TRA POST REQUEST: Người dùng có vừa bấm nút "Cập nhật" không?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    // 2. GOM DỮ LIỆU TỪ FORM: Lấy dữ liệu từ các thẻ <input name="...">
    $data = [
        'full_name' => $_POST['full_name'],
        'phone' => $_POST['phone'],
        'address' => $_POST['address'] // Nhận địa chỉ mới nhập từ form
    ];

    // 3. GỌI SERVICE XỬ LÝ: Truyền ID ($userId đã lấy từ Session) và mảng dữ liệu vào
    if ($userService->updateProfile($userId, $data)) {

        // 4. THÔNG BÁO & CHUYỂN HƯỚNG: Lưu câu thông báo vào Session
        $_SESSION['success_msg'] = "Cập nhật thông tin thành công!";

        // Load lại chính trang này (Refresh) bằng lệnh header() 
        // để form cập nhật lại giá trị mới nhất từ DB
        header("Location: profile.php");
        exit(); // Bắt buộc phải có exit() sau khi dùng header chuyển hướng
    }
}

// Xác định tab hiện tại
$currentTab = $_GET['tab'] ?? 'profile';

/**
 * Hàm tạo URL phân trang, giữ nguyên các tham số hiện tại (như tab=orders)
 */
function buildProfilePageUrl($p)
{
    $params = $_GET;
    $params['page'] = $p;
    return 'profile.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tài khoản - DIENMAYPRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-100 font-sans">

    <?php require_once __DIR__ . '/../partials/header.php'; ?>

    <div class="container mx-auto py-10 px-4">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Tài khoản của tôi</h1>

        <?php if ($message): ?>
            <div
                class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700 border border-green-400' : 'bg-red-100 text-red-700 border border-red-400'; ?>">
                <i
                    class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row gap-8">
            <!-- Sidebar Navigation -->
            <div class="w-full md:w-1/4">
                <div class="bg-white rounded-xl shadow-sm p-4 space-y-2">
                    <a href="?tab=profile"
                        class="flex items-center p-3 rounded-lg transition-colors <?php echo $currentTab === 'profile' ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <i class="fas fa-user w-6"></i> <span>Thông tin cá nhân</span>
                    </a>
                    <a href="?tab=security"
                        class="flex items-center p-3 rounded-lg transition-colors <?php echo $currentTab === 'security' ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <i class="fas fa-lock w-6"></i> <span>Bảo mật & Mật khẩu</span>
                    </a>
                    <a href="?tab=orders"
                        class="flex items-center p-3 rounded-lg transition-colors <?php echo $currentTab === 'orders' ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <i class="fas fa-shopping-bag w-6"></i> <span>Lịch sử đơn hàng</span>
                    </a>
                    <div class="border-t pt-2 mt-2">
                        <form method="POST" action="index.php" class="m-0">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit"
                                class="w-full flex items-center p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors">
                                <i class="fas fa-sign-out-alt w-6"></i> <span>Đăng xuất</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="w-full md:w-3/4">
                <?php if ($currentTab === 'profile'): ?>
                    <!-- Tab: Thông tin cá nhân -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-xl font-semibold mb-6 flex items-center">
                            <i class="fas fa-user-edit mr-2 text-blue-500"></i> Cập nhật thông tin
                        </h2>
                        <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Họ và Tên</label>
                                <input type="text" name="fullname"
                                    value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>"
                                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>

                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email"
                                    value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>

                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Số điện thoại</label>
                                <input type="text" name="phone"
                                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>

                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Địa chỉ</label>
                                <input type="text" name="address"
                                    value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"
                                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>

                            <div class="col-span-2 flex justify-end mt-4">
                                <button type="submit"
                                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                    Lưu thay đổi
                                </button>
                            </div>
                        </form>
                    </div>

                <?php elseif ($currentTab === 'security'): ?>
                    <!-- Tab: Bảo mật -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-xl font-semibold mb-6 flex items-center">
                            <i class="fas fa-key mr-2 text-blue-500"></i> Thay đổi mật khẩu
                        </h2>
                        <form action="" method="POST" class="max-w-md space-y-4">
                            <input type="hidden" name="action" value="change_password">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mật khẩu hiện tại</label>
                                <input type="password" name="current_password"
                                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                    required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mật khẩu mới</label>
                                <input type="password" name="new_password"
                                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                    required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Xác nhận mật khẩu mới</label>
                                <input type="password" name="confirm_password"
                                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                    required>
                            </div>

                            <div class="flex justify-end pt-4">
                                <button type="submit"
                                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                    Cập nhật mật khẩu
                                </button>
                            </div>
                        </form>
                    </div>

                <?php elseif ($currentTab === 'orders'): ?>
                    <!-- Tab: Đơn hàng -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-xl font-semibold mb-6 flex items-center">
                            <i class="fas fa-history mr-2 text-blue-500"></i> Lịch sử mua hàng
                        </h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-600 text-sm uppercase">
                                        <th class="p-3 border-b">Mã đơn</th>
                                        <th class="p-3 border-b">Ngày đặt</th>
                                        <th class="p-3 border-b">Tổng tiền</th>
                                        <th class="p-3 border-b">Trạng thái</th>
                                        <th class="p-3 border-b text-center">Chi tiết</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="5" class="p-8 text-center text-gray-500">Bạn chưa có đơn hàng nào.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                            <tr class="border-b hover:bg-gray-50 transition-colors">
                                                <td class="p-3 font-medium">#<?php echo $order['id']; ?></td>
                                                <td class="p-3 text-gray-600">
                                                    <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                                </td>
                                                <td class="p-3 font-semibold text-blue-600">
                                                    <?php echo number_format($order['total_price'], 0, ',', '.'); ?>đ
                                                </td>
                                                <td class="p-3">
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium 
                                                        <?php
                                                        echo $order['status'] === 'completed' ? 'bg-green-100 text-green-700' :
                                                            ($order['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                                                        ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <a href="track_order.php?id=<?php echo $order['id']; ?>"
                                                        class="text-blue-500 hover:underline text-sm">Xem chi tiết</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Phân trang -->
                        <?php if ($pagination['total_pages'] > 1): ?>
                            <div class="mt-8 flex justify-center items-center gap-2">
                                <?php if ($pagination['current_page'] > 1): ?>
                                    <a href="<?php echo buildProfilePageUrl($pagination['current_page'] - 1); ?>"
                                        class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-all text-sm font-medium shadow-sm">
                                        <i class="fas fa-chevron-left mr-1.5"></i> Trước
                                    </a>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $pagination['current_page'] - 2);
                                $endPage = min($pagination['total_pages'], $startPage + 4);
                                if ($endPage - $startPage < 4) {
                                    $startPage = max(1, $endPage - 4);
                                }

                                for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                    <a href="<?php echo buildProfilePageUrl($i); ?>"
                                        class="w-10 h-10 flex items-center justify-center rounded-lg border transition-all text-sm font-bold <?php echo $i === $pagination['current_page'] ? 'bg-blue-600 text-white border-blue-600 shadow-md ring-2 ring-blue-100' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400 hover:text-blue-600'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                                    <a href="<?php echo buildProfilePageUrl($pagination['current_page'] + 1); ?>"
                                        class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-all text-sm font-medium shadow-sm">
                                        Sau <i class="fas fa-chevron-right ml-1.5"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../partials/footer.php'; ?>
</body>

</html>