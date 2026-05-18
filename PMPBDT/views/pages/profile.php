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
$twoFaAction = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $userService->handleAccountAction($_POST, $userId);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';

    if ($twoFaAction === 'enable_2fa' && $result['success']) {
        $twoFaAction = 'verify_2fa_enable';
    }
}

// 3. Lấy dữ liệu profile với phân trang
$page = isset($_GET['page']) && (int) $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$limit = 5; // Số đơn hàng mỗi trang
$profileData = $userService->getUserProfileData($userId, $page, $limit);

// BẠN CẦN ĐẢM BẢO CÓ ĐỦ 3 DÒNG NÀY (Đừng để thiếu dòng nào nhé):
$user = $profileData['user'];
$orders = $profileData['orders'];
$pagination = $profileData['pagination']; // <- Biến bị thiếu gây ra lỗi dòng 297

$twoFactorInfo = (int) ($user['two_factor_enabled'] ?? 0);
$twoFactorSecret = $user['two_factor_secret'] ?? '';
$twoFactorProvisioningUri = '';
$twoFactorColumnsReady = isset($user['two_factor_enabled']) || isset($user['two_factor_secret']);
$pending2FAEnroll = $_SESSION['two_factor_pending_enroll'] ?? null;
$showTwoFactorOtpForm = is_array($pending2FAEnroll) && (int)($pending2FAEnroll['user_id'] ?? 0) === $userId;
$showTwoFactorSetup = $twoFactorInfo === 0 && !$showTwoFactorOtpForm;

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
        $_SESSION['success_msg'] = __("update_profile_success");

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

// --- WISHLIST LOGIC ---
$wishlistItems = [];
if ($currentTab === 'wishlist') {
    $stmtWish = $db->prepare("
        SELECT p.*, w.created_at as added_at, c.name as category_name
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmtWish->execute([$userId]);
    $wishlistItems = $stmtWish->fetchAll(PDO::FETCH_ASSOC);
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
        <h1 class="text-3xl font-bold text-gray-800 mb-8"><?= __('account_title') ?></h1>

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
                        <i class="fas fa-user w-6"></i> <span><?= __('personal_info') ?></span>
                    </a>
                    <a href="?tab=security"
                        class="flex items-center p-3 rounded-lg transition-colors <?php echo $currentTab === 'security' ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <i class="fas fa-lock w-6"></i> <span><?= __('security') ?></span>
                    </a>
                    <a href="?tab=orders"
                        class="flex items-center p-3 rounded-lg transition-colors <?php echo $currentTab === 'orders' ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <i class="fas fa-shopping-bag w-6"></i> <span><?= __('order_history') ?></span>
                    </a>
                    <a href="?tab=addresses"
                        class="flex items-center p-3 rounded-lg transition-colors <?php echo $currentTab === 'addresses' ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <i class="fas fa-map-marker-alt w-6"></i> <span><?= __('address_book') ?></span>
                    </a>
                    <a href="?tab=notifications"
                        class="flex items-center p-3 rounded-lg transition-colors <?php echo $currentTab === 'notifications' ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <i class="fas fa-bell w-6"></i> <span><?= __('notifications') ?></span>
                    </a>
                    <a href="?tab=wishlist"
                        class="flex items-center p-3 rounded-lg transition-colors <?php echo $currentTab === 'wishlist' ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-600 hover:bg-gray-50'; ?>">
                        <i class="fas fa-heart w-6"></i> <span><?= __('wishlist') ?></span>
                    </a>
                    <a href="login_history.php"
                        class="flex items-center p-3 rounded-lg transition-colors text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-clock-rotate-left w-6"></i> <span><?= __('login_history') ?></span>
                    </a>
                    <div class="border-t pt-2 mt-2">
                        <form method="POST" action="index.php" class="m-0">
                            <?= csrf_input_field() ?>
                            <input type="hidden" name="action" value="logout">
                            <button type="submit"
                                class="w-full flex items-center p-3 rounded-lg text-red-500 hover:bg-red-50 transition-colors">
                                <i class="fas fa-sign-out-alt w-6"></i> <span><?= __('logout') ?></span>
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
                            <i class="fas fa-user-edit mr-2 text-blue-500"></i> <?= __('update_info') ?>
                        </h2>
                        <div class="mb-5 flex flex-wrap items-center gap-3 text-sm">
                            <span class="text-gray-500"><?= __('account_type') ?>:</span>
                            <?php if (($user['auth_provider'] ?? 'local') === 'google'): ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700 border border-red-200">
                                    <i class="fa-brands fa-google mr-1"></i> Google
                                </span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-primary border border-blue-200">
                                    <i class="fa-solid fa-user mr-1"></i> DienMayPro
                                </span>
                            <?php endif; ?>
                            <span class="text-gray-500"><?= __('username') ?>:</span>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                @<?= e($user['username'] ?? '') ?>
                            </span>
                        </div>
                        <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?= csrf_input_field() ?>
                            <input type="hidden" name="action" value="update_profile">

                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('fullname') ?></label>
                                <input type="text" name="fullname"
                                    value="<?php echo e($user['fullname'] ?? ''); ?>"
                                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>

                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('username') ?></label>
                                <input type="text" name="username"
                                    value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>

                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('email') ?></label>
                                <input type="email" name="email"
                                    value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>

                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('phone') ?></label>
                                <input type="text" name="phone"
                                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>

                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('address') ?></label>
                                <input type="text" name="address"
                                    value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"
                                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>

                            <div class="col-span-2 flex justify-end mt-4">
                                <button type="submit"
                                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                    <?= __('save_changes') ?>
                                </button>
                            </div>
                        </form>
                    </div>

                <?php elseif ($currentTab === 'security'): ?>
                    <!-- Tab: Bảo mật -->
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h2 class="text-xl font-semibold mb-6 flex items-center">
                                <i class="fas fa-key mr-2 text-blue-500"></i> <?= __('change_password') ?>
                            </h2>
                            <form action="" method="POST" class="max-w-md space-y-4">
                                <?= csrf_input_field() ?>
                                <input type="hidden" name="action" value="change_password">

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('current_password') ?></label>
                                    <input type="password" name="current_password"
                                        class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                        required>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('new_password') ?></label>
                                    <input type="password" name="new_password"
                                        class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                        required>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('confirm_new_pw') ?></label>
                                    <input type="password" name="confirm_password"
                                        class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                        required>
                                </div>

                                <div class="flex justify-end pt-4">
                                    <button type="submit"
                                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                        <?= __('update_password') ?>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-start justify-between gap-4 mb-4">
                                <div>
                                    <h2 class="text-xl font-semibold flex items-center">
                                        <i class="fas fa-shield-halved mr-2 text-blue-500"></i> <?= __('two_factor_auth') ?>
                                    </h2>
                                    <p class="text-sm text-gray-500 mt-1"><?= __('two_factor_desc') ?></p>
                                </div>
                                <?php if (!$twoFactorColumnsReady): ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700 border border-yellow-200"><?= __('need_db_update') ?></span>
                                <?php elseif ((int) $twoFactorInfo === 1): ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200"><?= __('enabled') ?></span>
                                <?php elseif ($showTwoFactorOtpForm): ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 border border-blue-200"><?= __('waiting_verification') ?></span>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200"><?= __('disabled') ?></span>
                                <?php endif; ?>
                                <?php if (($user['email'] ?? '') !== '' && preg_match('/@gmail\.com$/i', (string) $user['email'])): ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 border border-blue-200"><?= __('eligible_for_2fa') ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if (!$twoFactorColumnsReady): ?>
                                <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-700">
                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= __('two_factor_db_error') ?>
                                </div>
                            <?php else: ?>
                                <?php if ((int) $twoFactorInfo === 1): ?>
                                    <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700 mb-4">
                                        <i class="fa-solid fa-circle-check mr-1"></i> <?= __('two_factor_active_msg') ?>
                                    </div>
                                    <form method="POST" class="max-w-md">
                                        <?= csrf_input_field() ?>
                                        <input type="hidden" name="action" value="disable_2fa">
                                        <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition-colors font-medium"><?= __('disable_two_factor') ?></button>
                                    </form>
                                <?php else: ?>
                                    <?php if (!(($user['auth_provider'] ?? 'local') === 'google' || preg_match('/@gmail\.com$/i', (string)($user['email'] ?? '')))): ?>
                                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                            <?= __('two_factor_restrictions') ?>
                                        </div>
                                    <?php elseif (!$showTwoFactorOtpForm): ?>
                                        <form method="POST" class="max-w-md">
                                            <?= csrf_input_field() ?>
                                            <input type="hidden" name="action" value="enable_2fa">
                                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium"><?= __('enable_two_factor') ?></button>
                                        </form>
                                    <?php else: ?>
                                        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700">
                                            <i class="fa-solid fa-circle-check mr-1"></i> <?= __('otp_sent_msg') ?>
                                        </div>
                                        <form method="POST" class="max-w-md mt-4">
                                            <?= csrf_input_field() ?>
                                            <input type="hidden" name="action" value="verify_2fa_enable">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('otp_code') ?></label>
                                                <input type="text" name="otp_code" maxlength="6" pattern="\d{6}" required
                                                    class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                                    placeholder="<?= __('otp_placeholder') ?>">
                                            </div>
                                            <button type="submit" class="mt-4 w-full bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium"><?= __('confirm_two_factor') ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($currentTab === 'orders'): ?>
                    <!-- Tab: Đơn hàng -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-xl font-semibold mb-6 flex items-center">
                            <i class="fas fa-history mr-2 text-blue-500"></i> <?= __('order_history') ?>
                        </h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-600 text-sm uppercase">
                                        <th class="p-3 border-b"><?= __('order_id') ?></th>
                                        <th class="p-3 border-b"><?= __('order_date') ?></th>
                                        <th class="p-3 border-b"><?= __('total') ?></th>
                                        <th class="p-3 border-b"><?= __('status') ?></th>
                                        <th class="p-3 border-b text-center"><?= __('detail') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="5" class="p-8 text-center text-gray-500"><?= __('no_orders') ?></td>
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
                                                        onclick="event.preventDefault(); openOrderDetailDrawer(<?php echo $order['id']; ?>);"
                                                        class="text-blue-500 hover:underline text-sm font-semibold flex items-center justify-center gap-1">
                                                        <i class="fa-solid fa-eye text-[11px]"></i> <?= __('view_detail') ?>
                                                    </a>
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
                                        <i class="fas fa-chevron-left mr-1.5"></i> <?= __("previous") ?>
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
                                        <?= __("next") ?> <i class="fas fa-chevron-right ml-1.5"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($currentTab === 'addresses'): ?>
                    <!-- Tab: Sổ địa chỉ -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold flex items-center">
                                <i class="fas fa-map-marked-alt mr-2 text-blue-500"></i> <?= __('address_book') ?>
                            </h2>
                            <button onclick="showAddressModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                                <i class="fas fa-plus mr-1"></i> <?= __('add_new_address') ?>
                            </button>
                        </div>
                        
                        <div id="address-list" class="space-y-4">
                            <!-- JS sẽ load địa chỉ vào đây -->
                            <div class="p-10 text-center text-gray-400">
                                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                                <p><?= __('loading_addresses') ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Thêm/Sửa Địa chỉ -->
                    <div id="addressModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                        <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
                            <div class="p-5 border-b flex justify-between items-center bg-gray-50">
                                <h3 id="modalTitle" class="font-bold text-gray-800"><?= __('add_new_address') ?></h3>
                                <button onclick="closeAddressModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
                            </div>
                            <form id="addressForm" class="p-6 space-y-4">
                                <input type="hidden" id="addr-id" name="id">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('fullname') ?></label>
                                    <input type="text" id="addr-fullname" name="fullname" required class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('phone') ?></label>
                                    <input type="tel" id="addr-phone" name="phone" required class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('address') ?></label>
                                    <textarea id="addr-address" name="address" required rows="3" class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-blue-500 outline-none"></textarea>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="addr-default" name="is_default" value="1" class="w-4 h-4 text-blue-600 rounded">
                                    <label for="addr-default" class="text-sm text-gray-700 font-medium"><?= __('set_default_address') ?></label>
                                </div>
                                <div class="pt-4 flex gap-3">
                                    <button type="button" onclick="closeAddressModal()" class="flex-1 px-4 py-2 border rounded-xl text-gray-600 hover:bg-gray-50 font-medium"><?= __('cancel') ?></button>
                                    <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium"><?= __('save') ?></button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($currentTab === 'notifications'): ?>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold flex items-center">
                                <i class="fas fa-bell mr-2 text-blue-500"></i> <?= __('notifications') ?>
                            </h2>
                            <button onclick="markAllReadAndReload()" class="text-sm text-blue-600 hover:underline font-medium"><?= __('mark_all_read') ?></button>
                        </div>
                        <div id="full-noti-list" class="space-y-3">
                            <!-- JS sẽ load thông báo vào đây -->
                            <div class="p-10 text-center text-gray-400">
                                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                                <p><?= __('loading_notifications') ?></p>
                            </div>
                        </div>
                    </div>
                <?php elseif ($currentTab === 'wishlist'): ?>
                    <!-- Tab: Danh sách yêu thích -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden min-h-[500px]">
                        <!-- Header -->
                        <div class="bg-gradient-to-r from-pink-500 to-rose-600 p-6 text-white flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                                    <i class="fas fa-heart text-xl"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold"><?= __('wishlist') ?></h2>
                                    <p class="text-rose-100 text-sm mt-0.5"><?= count($wishlistItems) ?> <?= __('products_count') ?></p>
                                </div>
                            </div>
                            <?php if (!empty($wishlistItems)): ?>
                                <button onclick="clearWishlist()" class="bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 border border-white/10 shadow-sm">
                                    <i class="fas fa-trash-can text-sm"></i> <?= __('clear_all') ?>
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Content -->
                        <div class="p-6">
                            <?php if (empty($wishlistItems)): ?>
                                <div class="text-center py-20">
                                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-heart-crack text-3xl text-gray-300"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-800 mb-1"><?= __('wishlist_empty') ?></h3>
                                    <p class="text-gray-500 text-sm max-w-xs mx-auto mb-6"><?= __('wishlist_empty_desc') ?></p>
                                    <a href="index.php" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-2 rounded-full font-bold hover:bg-blue-700 transition-all shadow-lg shadow-blue-200 text-sm">
                                        <i class="fas fa-shopping-cart"></i> <?= __('continue_shopping') ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <?php foreach ($wishlistItems as $item): ?>
                                        <div class="group bg-white rounded-2xl border border-gray-100 hover:border-pink-200 hover:shadow-xl hover:shadow-pink-500/5 transition-all duration-300 overflow-hidden relative flex flex-col h-full">
                                            <!-- Nút xóa khỏi danh sách -->
                                            <button onclick="toggleWishlist(<?= $item['id'] ?>, this)" 
                                                class="absolute top-3 right-3 w-8 h-8 bg-white/90 backdrop-blur-md text-gray-400 hover:text-red-500 rounded-full flex items-center justify-center shadow-sm hover:bg-red-50 transition-all z-10 group/del"
                                                title="<?= __('remove_from_wishlist') ?>">
                                                <i class="fas fa-xmark text-sm transition-transform group-hover/del:rotate-90"></i>
                                            </button>

                                            <!-- Ảnh sản phẩm -->
                                            <a href="product_detail.php?id=<?= $item['id'] ?>" class="block aspect-square p-4 bg-gray-50/50 group-hover:bg-white transition-colors relative overflow-hidden">
                                                <?= img_lazy($item['image'], getCurrentLang() === 'en' ? translate_text($item['name'], 'prod_name_' . $item['id']) : $item['name'], 'w-full h-full object-contain mix-blend-multiply group-hover:scale-110 transition-transform duration-500') ?>
                                                <?php if ($item['old_price'] > $item['price']): ?>
                                                    <div class="absolute top-3 left-3 bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                                                        -<?= round((($item['old_price'] - $item['price']) / $item['old_price']) * 100) ?>%
                                                    </div>
                                                <?php endif; ?>
                                            </a>

                                            <!-- Thông tin -->
                                            <div class="p-4 flex flex-col flex-1">
                                                <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1"><?= htmlspecialchars(__cat($item['category_name'])) ?></div>
                                                <a href="product_detail.php?id=<?= $item['id'] ?>" class="text-sm font-bold text-gray-800 line-clamp-2 hover:text-primary transition-colors mb-2 min-h-[40px]">
                                                    <?= htmlspecialchars(getCurrentLang() === 'en' ? translate_text($item['name'], 'prod_name_' . $item['id']) : $item['name']) ?>
                                                </a>

                                                <div class="mt-auto">
                                                    <div class="flex items-baseline gap-2">
                                                        <span class="text-lg font-black text-red-600"><?= number_format($item['price'], 0, ',', '.') ?>đ</span>
                                                        <?php if ($item['old_price'] > $item['price']): ?>
                                                            <span class="text-xs text-gray-400 line-through"><?= number_format($item['old_price'], 0, ',', '.') ?>đ</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-between">
                                                        <!-- Nút xóa (Prominent square button) -->
                                                        <button onclick="toggleWishlist(<?= $item['id'] ?>, this)" 
                                                            class="w-10 h-10 bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white rounded-xl flex items-center justify-center transition-all shadow-sm shadow-rose-100 group/del"
                                                            title="<?= __('remove') ?>">
                                                            <i class="fas fa-trash-can"></i>
                                                        </button>

                                                        <!-- Nút thêm vào giỏ -->
                                                        <button onclick="addToCartAjax(<?= $item['id'] ?>)"
                                                            class="bg-primary text-white w-10 h-10 rounded-xl flex items-center justify-center hover:bg-blue-700 transition-all shadow-md shadow-blue-100"
                                                            title="<?= __('add_to_cart') ?>">
                                                            <i class="fas fa-cart-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==========================================
         DRAWER CHI TIẾT ĐƠN HÀNG (SLIDE-OVER PANEL)
         ========================================== -->
    <div id="order-detail-backdrop" class="fixed inset-0 z-[9990] bg-slate-900/50 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-300"></div>

    <div id="order-detail-drawer" class="fixed inset-y-0 right-0 z-[10000] w-full max-w-md md:max-w-lg bg-white dark:bg-slate-800 shadow-2xl transform translate-x-full transition-transform duration-300 flex flex-col h-full border-l border-gray-100 dark:border-slate-700">
        <!-- Header -->
        <div class="p-6 border-b border-gray-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-900/30">
            <div>
                <span class="text-xs font-bold text-blue-600 uppercase tracking-wider"><?= __('my_orders') ?></span>
                <h3 class="font-black text-xl text-slate-800 dark:text-white mt-0.5" id="order-drawer-title">Đơn hàng #...</h3>
            </div>
            <button onclick="closeOrderDetailDrawer()" class="w-9 h-9 rounded-full bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        
        <!-- Body -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6" id="order-drawer-body">
            <!-- Spinner -->
            <div class="flex flex-col items-center justify-center py-20 gap-3 text-slate-400">
                <div class="w-10 h-10 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                <p class="text-sm font-medium">Đang tải chi tiết đơn hàng...</p>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../partials/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load địa chỉ nếu đang ở tab addresses
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            
            if (tab === 'addresses') {
                loadAddresses();
            } else if (tab === 'notifications') {
                loadFullNotifications();
            }

            // Xử lý submit form địa chỉ
            const addrForm = document.getElementById('addressForm');
            if (addrForm) {
                addrForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    saveAddress();
                });
            }
        });

        // Helper để lấy đường dẫn API chính xác
        function getApiUrl(apiPath) {
            // Lấy thư mục hiện tại của trang profile.php (thường là /PMPBDT/public/)
            const currentDir = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
            return currentDir + apiPath;
        }

        // --- QUẢN LÝ ĐỊA CHỈ ---
        function loadAddresses() {
            const list = document.getElementById('address-list');
            fetch(getApiUrl('api/address.php?action=list'))
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderAddressList(data.data);
                    }
                });
        }

        function renderAddressList(items) {
            const list = document.getElementById('address-list');
            if (items.length === 0) {
                list.innerHTML = `
                    <div class="p-10 text-center text-gray-400 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
                        <i class="fas fa-map-marker-alt text-4xl mb-3"></i>
                        <p><?= __('no_saved_addresses') ?></p>
                    </div>`;
                return;
            }

            let html = '';
            items.forEach(item => {
                html += `
                    <div class="p-5 border rounded-xl hover:border-blue-500 transition-all bg-white relative group ${item.is_default ? 'ring-2 ring-blue-100 border-blue-500' : ''}">
                        ${item.is_default ? '<span class="absolute -top-3 left-4 bg-blue-600 text-white text-[10px] font-bold px-2 py-1 rounded-md shadow-sm"><?= __('default_label') ?></span>' : ''}
                        <div class="flex justify-between items-start">
                            <div class="space-y-1">
                                <h4 class="font-bold text-gray-800 flex items-center gap-2">
                                    ${item.fullname}
                                    <span class="h-4 w-[1px] bg-gray-300"></span>
                                    <span class="text-gray-600 font-normal text-sm">${item.phone}</span>
                                </h4>
                                <p class="text-sm text-gray-600 leading-relaxed max-w-md">${item.address}</p>
                            </div>
                            <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick='showAddressModal(${JSON.stringify(item).replace(/"/g, "&quot;")})' class="text-blue-500 hover:text-blue-700 p-2"><i class="fas fa-edit"></i></button>
                                <button onclick="deleteAddress(${item.id})" class="text-red-500 hover:text-red-700 p-2"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        ${!item.is_default ? `
                            <button onclick="setDefaultAddress(${item.id})" class="mt-3 text-xs text-blue-600 font-medium hover:underline"><?= __('set_as_default') ?></button>
                        ` : ''}
                    </div>
                `;
            });
            list.innerHTML = html;
        }

        function showAddressModal(item = null) {
            const modal = document.getElementById('addressModal');
            const form = document.getElementById('addressForm');
            const title = document.getElementById('modalTitle');

            form.reset();
            if (item) {
                title.innerText = '<?= __('update_address') ?>';
                document.getElementById('addr-id').value = item.id;
                document.getElementById('addr-fullname').value = item.fullname;
                document.getElementById('addr-phone').value = item.phone;
                document.getElementById('addr-address').value = item.address;
                document.getElementById('addr-default').checked = item.is_default;
            } else {
                title.innerText = '<?= __('add_new_address') ?>';
                document.getElementById('addr-id').value = '';
            }

            modal.classList.remove('hidden');
        }

        function closeAddressModal() {
            document.getElementById('addressModal').classList.add('hidden');
        }

        function saveAddress() {
            const formData = new FormData(document.getElementById('addressForm'));
            const data = Object.fromEntries(formData.entries());
            data.is_default = document.getElementById('addr-default').checked ? 1 : 0;
            
            const id = data.id;
            const action = id ? 'update' : 'add';

            // Debug alert
            // alert('Đang gửi yêu cầu ' + action + '...');

            const params = new URLSearchParams();
            for (const key in data) {
                params.append(key, data[key]);
            }

            fetch(getApiUrl(`api/address.php?action=${action}`), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => {
                if (!res.ok) throw new Error('Mã lỗi HTTP: ' + res.status);
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    closeAddressModal();
                    loadAddresses();
                    if (typeof showSuccessModal === 'function') {
                        showSuccessModal(data.message);
                    } else {
                        alert('Thành công: ' + data.message);
                    }
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Lỗi kết nối hoặc lỗi server: ' + err.message);
            });
        }

        function deleteAddress(id) {
            if (!confirm('<?= __('confirm_delete_address') ?>')) return;
            fetch(getApiUrl('api/address.php?action=delete'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadAddresses();
                }
            });
        }

        function setDefaultAddress(id) {
            fetch(getApiUrl('api/address.php?action=set_default'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadAddresses();
                }
            });
        }

        // --- QUẢN LÝ THÔNG BÁO TẠI TRANG RIÊNG ---
        function loadFullNotifications() {
            fetch(getApiUrl('api/notification.php?action=list'))
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderFullNotiList(data.data.items);
                    } else {
                        document.getElementById('full-noti-list').innerHTML = `<div class="p-10 text-center text-gray-400"><?= __('please_login_to_view') ?></div>`;
                    }
                })
                .catch(() => {
                    document.getElementById('full-noti-list').innerHTML = `<div class="p-10 text-center text-gray-400"><?= __('please_login_to_view') ?></div>`;
                });
        }

        function renderFullNotiList(items) {
            const list = document.getElementById('full-noti-list');
            if (items.length === 0) {
                list.innerHTML = `<div class="p-10 text-center text-gray-400"><?= __('no_notifications') ?></div>`;
                return;
            }

            let html = '';
            items.forEach(item => {
                html += `
                    <div class="p-4 border rounded-xl transition-all ${!item.is_read ? 'bg-blue-50 border-blue-200' : 'bg-white hover:bg-gray-50'}" onclick="markOneRead(${item.id})">
                        <div class="flex justify-between items-start mb-1">
                            <h4 class="font-bold text-gray-800">${item.title}</h4>
                            <span class="text-[10px] text-gray-400">${new Date(item.created_at).toLocaleString('vi-VN')}</span>
                        </div>
                        <p class="text-sm text-gray-600">${item.message}</p>
                    </div>
                `;
            });
            list.innerHTML = html;
        }

        function markOneRead(id) {
            fetch(getApiUrl('api/notification.php?action=read'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            }).then(() => loadFullNotifications());
        }

        function markAllReadAndReload() {
            fetch(getApiUrl('api/notification.php?action=read_all'), { method: 'POST' })
                .then(() => loadFullNotifications());
        }

        // --- QUẢN LÝ DANH SÁCH YÊU THÍCH ---
        function toggleWishlist(productId, btn) {
            const formData = new FormData();
            formData.append('action', 'toggle');
            formData.append('product_id', productId);
            
            // Thêm CSRF token từ header nếu có
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            formData.append('csrf_token', csrfToken);

            fetch(getApiUrl('api/wishlist.php?action=toggle'), {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Hiệu ứng xóa card
                    const card = btn.closest('.group');
                    if (card) {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(() => {
                            location.reload(); 
                        }, 300);
                    }
                    
                    Swal.fire({
                        title: 'Thông báo',
                        text: data.message,
                        icon: 'success',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                } else {
                    Swal.fire('Lỗi', data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('<?= __('error') ?>', '<?= __('cannot_connect_server') ?>', 'error');
            });
        }

        function clearWishlist() {
            Swal.fire({
                title: '<?= __('notification') ?>',
                text: '<?= __('confirm_clear_wishlist') ?>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '<?= __('agree') ?>',
                cancelButtonText: '<?= __('cancel') ?>'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
                    formData.append('csrf_token', csrfToken);

                    fetch(getApiUrl('api/wishlist.php?action=clear'), {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: '<?= __('notification') ?>',
                                text: data.message,
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('<?= __('error') ?>', data.message, 'error');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('<?= __('error') ?>', '<?= __('cannot_connect_server') ?>', 'error');
                    });
                }
            });
        }

        // ==========================================
        // HÀM MỞ & ĐÓNG DRAWER CHI TIẾT ĐƠN HÀNG
        // ==========================================
        function openOrderDetailDrawer(orderId) {
            const backdrop = document.getElementById('order-detail-backdrop');
            const drawer = document.getElementById('order-detail-drawer');
            const title = document.getElementById('order-drawer-title');
            const body = document.getElementById('order-drawer-body');
            
            title.innerText = `Đơn hàng #${orderId}`;
            
            // Hiện skeleton
            body.innerHTML = `
                <div class="flex flex-col items-center justify-center py-20 gap-3 text-slate-400">
                    <div class="w-10 h-10 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-sm font-medium">Đang tải chi tiết đơn hàng...</p>
                </div>
            `;
            
            // Kích hoạt animation slide-over
            backdrop.classList.remove('opacity-0', 'pointer-events-none');
            backdrop.classList.add('opacity-100');
            drawer.classList.remove('translate-x-full');
            
            // Gọi AJAX API tải thông tin chi tiết
            fetch(`order_details.php?id=${orderId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const order = data.order;
                        let step = 1;
                        if (order.status === 'processing') step = 2;
                        else if (order.status === 'delivering') step = 3;
                        else if (order.status === 'completed') step = 4;
                        
                        let timelineHtml = '';
                        if (order.status === 'cancelled') {
                            timelineHtml = `
                            <div class="bg-red-50 dark:bg-red-950/20 rounded-2xl p-5 border border-red-100 dark:border-red-900/30 text-center">
                                <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/40 text-red-600 flex items-center justify-center mx-auto text-xl mb-3">
                                    <i class="fa-solid fa-ban"></i>
                                </div>
                                <h4 class="font-bold text-red-600 text-base mb-1">Đơn hàng đã bị hủy</h4>
                                <p class="text-xs text-slate-500">Đơn hàng đã được hoàn trả hoặc bị hủy bởi khách hàng.</p>
                            </div>
                            `;
                        } else {
                            timelineHtml = `
                            <div class="bg-blue-50/50 dark:bg-blue-900/10 rounded-2xl p-5 border border-blue-100/50 dark:border-blue-900/20">
                                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm mb-4 flex items-center gap-2">
                                    <i class="fa-solid fa-truck-fast text-blue-600"></i> Trạng thái đơn hàng
                                </h4>
                                <div class="relative pl-6 space-y-6 border-l-2 border-gray-200 dark:border-slate-700 ml-3">
                                    <!-- Bước 1 -->
                                    <div class="relative">
                                        <div class="absolute -left-[31px] top-0 w-4 h-4 rounded-full flex items-center justify-center border-2 border-white dark:border-slate-800 ${step >= 1 ? 'bg-blue-600' : 'bg-gray-300'}"></div>
                                        <p class="text-sm font-bold text-slate-800 dark:text-white">Đặt hàng thành công</p>
                                        <p class="text-xs text-slate-500 mt-0.5">${order.created_at}</p>
                                    </div>
                                    <!-- Bước 2 -->
                                    <div class="relative">
                                        <div class="absolute -left-[31px] top-0 w-4 h-4 rounded-full flex items-center justify-center border-2 border-white dark:border-slate-800 ${step >= 2 ? 'bg-blue-600' : 'bg-gray-300'}"></div>
                                        <p class="text-sm font-bold text-slate-800 dark:text-white">Đã xác nhận & Thanh toán</p>
                                        <p class="text-xs text-slate-500 mt-0.5">${step >= 2 ? order.processing_at : 'Chờ xử lý thanh toán'}</p>
                                    </div>
                                    <!-- Bước 3 -->
                                    <div class="relative">
                                        <div class="absolute -left-[31px] top-0 w-4 h-4 rounded-full flex items-center justify-center border-2 border-white dark:border-slate-800 ${step >= 3 ? 'bg-blue-600' : 'bg-gray-300'}"></div>
                                        <p class="text-sm font-bold text-slate-800 dark:text-white">Đang giao hàng</p>
                                        <p class="text-xs text-slate-500 mt-0.5">${step >= 3 ? order.delivering_at : 'Chuẩn bị đóng gói'}</p>
                                    </div>
                                    <!-- Bước 4 -->
                                    <div class="relative">
                                        <div class="absolute -left-[31px] top-0 w-4 h-4 rounded-full flex items-center justify-center border-2 border-white dark:border-slate-800 ${step >= 4 ? 'bg-green-600' : 'bg-gray-300'}"></div>
                                        <p class="text-sm font-bold ${step >= 4 ? 'text-green-600' : 'text-slate-800 dark:text-white'}">Giao hàng thành công</p>
                                        <p class="text-xs text-slate-500 mt-0.5">${step >= 4 ? order.completed_at : 'Chưa bàn giao'}</p>
                                    </div>
                                </div>
                            </div>
                            `;
                        }

                        let itemsHtml = '';
                        order.items.forEach(item => {
                            itemsHtml += `
                            <div class="flex items-center gap-4 py-3 border-b border-gray-100 dark:border-slate-700 last:border-0">
                                <img src="${item.image}" alt="${item.name}" class="w-16 h-16 rounded-xl object-cover bg-gray-50 border border-gray-100 dark:border-slate-700 flex-shrink-0">
                                <div class="flex-1 min-w-0">
                                    <h5 class="font-semibold text-slate-800 dark:text-white text-sm truncate hover:text-blue-600 transition-colors">
                                        <a href="product_detail.php?id=${item.product_id}" target="_blank">${item.name}</a>
                                    </h5>
                                    <p class="text-xs text-slate-500 mt-1">${item.price_formatted} x ${item.quantity}</p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="font-bold text-slate-800 dark:text-white text-sm">${item.total}</p>
                                </div>
                            </div>
                            `;
                        });

                        body.innerHTML = `
                            <div class="flex justify-between items-center bg-gray-50 dark:bg-slate-900/20 p-4 rounded-2xl border border-gray-100 dark:border-slate-700">
                                <span class="text-sm font-semibold text-slate-500">Mã đơn hàng:</span>
                                <span class="font-bold text-slate-800 dark:text-white">#${order.id}</span>
                            </div>

                            ${timelineHtml}
                            
                            <div class="space-y-3">
                                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm flex items-center gap-2">
                                    <i class="fa-solid fa-basket-shopping text-blue-600"></i> Danh sách sản phẩm
                                </h4>
                                <div class="border border-gray-100 dark:border-slate-700 rounded-2xl p-4 bg-white dark:bg-slate-800 shadow-sm divide-y divide-gray-100 dark:divide-slate-700">
                                    ${itemsHtml}
                                </div>
                            </div>

                            <div class="space-y-3 bg-gray-50 dark:bg-slate-900/20 rounded-2xl p-5 border border-gray-100 dark:border-slate-700">
                                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm flex items-center gap-2">
                                    <i class="fa-solid fa-address-card text-blue-600"></i> Thông tin nhận hàng
                                </h4>
                                <div class="text-xs text-slate-600 dark:text-slate-300 space-y-2">
                                    <p><span class="font-semibold text-slate-800 dark:text-slate-200">Người nhận:</span> ${order.fullname}</p>
                                    <p><span class="font-semibold text-slate-800 dark:text-slate-200">Số điện thoại:</span> ${order.phone}</p>
                                    <p><span class="font-semibold text-slate-800 dark:text-slate-200">Địa chỉ:</span> ${order.address}</p>
                                    <p><span class="font-semibold text-slate-800 dark:text-slate-200">Phương thức thanh toán:</span> ${order.payment_method}</p>
                                    ${order.note ? `<p><span class="font-semibold text-slate-800 dark:text-slate-200">Ghi chú:</span> ${order.note}</p>` : ''}
                                </div>
                            </div>

                            <div class="flex justify-between items-center py-4 border-t border-gray-100 dark:border-slate-700">
                                <span class="font-bold text-slate-800 dark:text-slate-200 text-base">Tổng số tiền:</span>
                                <span class="font-black text-xl text-blue-600 dark:text-blue-400">${order.total_price_formatted}</span>
                            </div>
                        `;
                    } else {
                        body.innerHTML = `
                            <div class="text-center py-20 text-red-500">
                                <i class="fa-solid fa-circle-exclamation text-3xl mb-3"></i>
                                <p class="text-sm font-medium">Không thể tải thông tin đơn hàng này.</p>
                            </div>
                        `;
                    }
                })
                .catch(err => {
                    body.innerHTML = `
                        <div class="text-center py-20 text-red-500">
                            <i class="fa-solid fa-circle-exclamation text-3xl mb-3"></i>
                            <p class="text-sm font-medium">Lỗi kết nối máy chủ.</p>
                        </div>
                    `;
                });
        }

        function closeOrderDetailDrawer() {
            const backdrop = document.getElementById('order-detail-backdrop');
            const drawer = document.getElementById('order-detail-drawer');
            
            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0', 'pointer-events-none');
            drawer.classList.add('translate-x-full');
        }

        // Close when clicking backdrop
        document.addEventListener('DOMContentLoaded', () => {
            const backdrop = document.getElementById('order-detail-backdrop');
            if (backdrop) {
                backdrop.addEventListener('click', closeOrderDetailDrawer);
            }
        });
    </script>
</body>

</html>