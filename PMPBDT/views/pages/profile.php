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
    
    if ($result['success']) {
        // Lưu thông báo vào session để hiển thị sau khi redirect
        $_SESSION['success_msg'] = $result['message'];
        
        // Nếu là cập nhật profile, ta redirect để refresh dữ liệu mới
        if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
            header("Location: profile.php?tab=profile");
            exit();
        }
    } else {
        $message = $result['message'];
        $messageType = 'error';
    }

    if ($twoFaAction === 'enable_2fa' && $result['success']) {
        $twoFaAction = 'verify_2fa_enable';
    }
}

// Lấy thông báo từ session (nếu có) sau khi redirect
if (isset($_SESSION['success_msg'])) {
    $message = $_SESSION['success_msg'];
    $messageType = 'success';
    unset($_SESSION['success_msg']);
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
// Kiểm tra trạng thái đang chờ kích hoạt 2FA (Đồng bộ qua Database để hỗ trợ cả Web và Mobile App)
$hasPendingOtpInDb = !empty($user['two_factor_otp'] ?? '') && (int)($user['two_factor_otp_expires_at'] ?? 0) > time();
$pending2FAEnroll = $_SESSION['two_factor_pending_enroll'] ?? null;
$showTwoFactorOtpForm = $hasPendingOtpInDb || (is_array($pending2FAEnroll) && (int)($pending2FAEnroll['user_id'] ?? 0) === $userId);
$showTwoFactorSetup = $twoFactorInfo === 0 && !$showTwoFactorOtpForm;

// Tab hiện tại (lấy từ GET)
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
$meta_title = 'Quản lý tài khoản - DIENMAYPRO';
require_once __DIR__ . '/../partials/header.php';
?>

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
                            <span class="text-gray-500">Loại tài khoản:</span>
                            <?php if (($user['auth_provider'] ?? 'local') === 'google'): ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700 border border-red-200">
                                    <i class="fa-brands fa-google mr-1"></i> Google
                                </span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-primary border border-blue-200">
                                    <i class="fa-solid fa-user mr-1"></i> DienMayPro
                                </span>
                            <?php endif; ?>
                            <span class="text-gray-500">Tên đăng nhập:</span>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tên đăng nhập</label>
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
                            <?php 
                            $hasPendingResetOtp = !empty($user['reset_password_otp'] ?? '') && (int)($user['reset_password_otp_expires_at'] ?? 0) > time();
                            ?>
                            
                            <!-- Bộ chọn phương thức đổi mật khẩu -->
                            <div class="flex p-1 bg-gray-100 rounded-lg max-w-md mb-6" id="password-method-selector">
                                <button type="button" id="btn-method-password" onclick="switchPasswordMethod('password')" 
                                    class="flex-1 py-2 px-3 text-sm font-medium rounded-md transition-all duration-200 bg-white text-gray-800 shadow-sm cursor-pointer">
                                    <i class="fa-solid fa-lock mr-2 text-blue-500"></i> Dùng Mật khẩu cũ
                                </button>
                                <button type="button" id="btn-method-otp" onclick="switchPasswordMethod('otp')" 
                                    class="flex-1 py-2 px-3 text-sm font-medium rounded-md transition-all duration-200 text-gray-600 hover:text-gray-800 cursor-pointer">
                                    <i class="fa-solid fa-envelope mr-2 text-blue-500"></i> Xác minh OTP Email
                                </button>
                            </div>

                            <!-- PHƯƠNG THỨC 1: Dùng mật khẩu cũ -->
                            <div id="form-method-password" class="max-w-md">
                                <form action="" method="POST" class="space-y-4">
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
                                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium cursor-pointer">
                                            <?= __('update_password') ?>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- PHƯƠNG THỨC 2: Xác minh OTP Email -->
                            <div id="form-method-otp" class="max-w-md hidden">
                                <?php if (!$hasPendingResetOtp): ?>
                                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700 mb-6">
                                        <i class="fa-solid fa-info-circle mr-1"></i> Xác minh qua mã OTP gửi tới email đã đăng ký của bạn để thiết lập/đặt lại mật khẩu mới.
                                    </div>
                                    <form action="" method="POST">
                                        <?= csrf_input_field() ?>
                                        <input type="hidden" name="action" value="send_email_password_otp">
                                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium cursor-pointer">
                                            Gửi mã OTP xác nhận tới <?= htmlspecialchars($user['email'] ?? '') ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700 mb-6">
                                        <i class="fa-solid fa-paper-plane mr-1"></i> Mã OTP đã được gửi đến email <strong><?= htmlspecialchars($user['email'] ?? '') ?></strong>. Vui lòng kiểm tra và nhập mã để đặt mật khẩu.
                                    </div>
                                    <form action="" method="POST" class="space-y-4">
                                        <?= csrf_input_field() ?>
                                        <input type="hidden" name="action" value="change_password_email_otp">

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Mã OTP xác thực</label>
                                            <input type="text" name="otp_code" maxlength="6" pattern="\d{6}" required
                                                class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                                placeholder="Nhập 6 chữ số">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('new_password') ?></label>
                                            <input type="password" name="new_password" required
                                                class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('confirm_new_pw') ?></label>
                                            <input type="password" name="confirm_password" required
                                                class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                                        </div>

                                        <div class="flex justify-between items-center pt-4">
                                            <button type="button" 
                                                onclick="document.getElementById('action_resend').value='send_email_password_otp'; document.getElementById('google_otp_form').submit();"
                                                class="text-sm text-blue-600 hover:underline cursor-pointer">
                                                Gửi lại mã OTP
                                            </button>
                                            <button type="submit"
                                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium cursor-pointer">
                                                <?= __('update_password') ?>
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <!-- Form ẩn dùng để gửi lại mã OTP -->
                                    <form id="google_otp_form" method="POST" class="hidden">
                                        <?= csrf_input_field() ?>
                                        <input type="hidden" name="action" id="action_resend" value="send_email_password_otp">
                                    </form>
                                <?php endif; ?>
                            </div>

                            <script>
                            function switchPasswordMethod(method) {
                                const btnPwd = document.getElementById('btn-method-password');
                                const btnOtp = document.getElementById('btn-method-otp');
                                const formPwd = document.getElementById('form-method-password');
                                const formOtp = document.getElementById('form-method-otp');

                                if (!btnPwd || !btnOtp || !formPwd || !formOtp) return;

                                if (method === 'password') {
                                    btnPwd.className = "flex-1 py-2 px-3 text-sm font-medium rounded-md transition-all duration-200 bg-white text-gray-800 shadow-sm cursor-pointer";
                                    btnOtp.className = "flex-1 py-2 px-3 text-sm font-medium rounded-md transition-all duration-200 text-gray-600 hover:text-gray-800 cursor-pointer";
                                    formPwd.classList.remove('hidden');
                                    formOtp.classList.add('hidden');
                                    localStorage.setItem('pwd_change_method', 'password');
                                } else {
                                    btnOtp.className = "flex-1 py-2 px-3 text-sm font-medium rounded-md transition-all duration-200 bg-white text-gray-800 shadow-sm cursor-pointer";
                                    btnPwd.className = "flex-1 py-2 px-3 text-sm font-medium rounded-md transition-all duration-200 text-gray-600 hover:text-gray-800 cursor-pointer";
                                    formOtp.classList.remove('hidden');
                                    formPwd.classList.add('hidden');
                                    localStorage.setItem('pwd_change_method', 'otp');
                                }
                            }

                            document.addEventListener('DOMContentLoaded', function() {
                                const hasPendingOtp = <?php echo json_encode($hasPendingResetOtp); ?>;
                                const savedMethod = localStorage.getItem('pwd_change_method');
                                
                                if (hasPendingOtp) {
                                    switchPasswordMethod('otp');
                                } else if (savedMethod === 'otp') {
                                    switchPasswordMethod('otp');
                                } else {
                                    switchPasswordMethod('password');
                                }
                            });
                            </script>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-start justify-between gap-4 mb-4">
                                <div>
                                    <h2 class="text-xl font-semibold flex items-center">
                                        <i class="fas fa-shield-halved mr-2 text-blue-500"></i> Bảo mật 2 lớp
                                    </h2>
                                    <p class="text-sm text-gray-500 mt-1">Dùng ứng dụng Authenticator để lấy mã xác thực động.</p>
                                </div>
                                <?php if (!$twoFactorColumnsReady): ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700 border border-yellow-200">Cần cập nhật DB</span>
                                <?php elseif ((int) $twoFactorInfo === 1): ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">Đang bật</span>
                                <?php elseif ($showTwoFactorOtpForm): ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 border border-blue-200">Đang chờ xác minh</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200">Đang tắt</span>
                                <?php endif; ?>
                                <?php if (($user['email'] ?? '') !== '' && preg_match('/@gmail\.com$/i', (string) $user['email'])): ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 border border-blue-200">Đủ điều kiện bật 2FA</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!$twoFactorColumnsReady): ?>
                                <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-700">
                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> Cơ sở dữ liệu chưa có cột `two_factor_enabled` và `two_factor_secret`. Vui lòng chạy file `sql/add_two_factor_columns_to_users.sql`.
                                </div>
                            <?php else: ?>
                                <?php if ((int) $twoFactorInfo === 1): ?>
                                    <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700 mb-4">
                                        <i class="fa-solid fa-circle-check mr-1"></i> Tài khoản của bạn đang được bảo vệ bằng Authenticator.
                                    </div>
                                    <form method="POST" class="max-w-md">
                                        <?= csrf_input_field() ?>
                                        <input type="hidden" name="action" value="disable_2fa">
                                        <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition-colors font-medium">Tắt bảo mật 2 lớp</button>
                                    </form>
                                <?php else: ?>
                                    <?php if (!(($user['auth_provider'] ?? 'local') === 'google' || preg_match('/@gmail\.com$/i', (string)($user['email'] ?? '')))): ?>
                                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                            Chỉ tài khoản có Gmail hoặc tài khoản Google mới có thể bật 2 lớp trong hệ thống này.
                                        </div>
                                    <?php elseif (!$showTwoFactorOtpForm): ?>
                                        <form method="POST" class="max-w-md">
                                            <?= csrf_input_field() ?>
                                            <input type="hidden" name="action" value="enable_2fa">
                                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">Bật bảo mật 2 lớp</button>
                                        </form>
                                    <?php else: ?>
                                        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700">
                                            <i class="fa-solid fa-circle-check mr-1"></i> Đã gửi OTP đến Gmail của bạn. Vui lòng nhập mã để xác nhận bật 2FA.
                                        </div>
                                        <form method="POST" class="max-w-md mt-4">
                                            <?= csrf_input_field() ?>
                                            <input type="hidden" name="action" value="verify_2fa_enable">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Mã OTP</label>
                                                <input type="text" name="otp_code" maxlength="6" pattern="\d{6}" required
                                                    class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                                    placeholder="Nhập mã 6 số từ Gmail">
                                            </div>
                                            <button type="submit" class="mt-4 w-full bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">Xác nhận bật 2FA</button>
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
                                                        class="text-blue-500 hover:underline text-sm"><?= __('view_detail') ?></a>
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
                                <p>Đang tải danh sách địa chỉ...</p>
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
                                    <button type="button" onclick="closeAddressModal()" class="flex-1 px-4 py-2 border rounded-xl text-gray-600 hover:bg-gray-50 font-medium">Hủy</button>
                                    <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium">Lưu</button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($currentTab === 'notifications'): ?>
                    <!-- Tab: Thông báo -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold flex items-center">
                                <i class="fas fa-bell mr-2 text-blue-500"></i> <?= __('notifications') ?>
                            </h2>
                            <button onclick="markAllReadAndReload()" class="text-sm text-blue-600 hover:underline font-medium">Đánh dấu tất cả là đã đọc</button>
                        </div>
                        <div id="full-noti-list" class="space-y-3">
                            <!-- JS sẽ load thông báo vào đây -->
                            <div class="p-10 text-center text-gray-400">
                                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                                <p>Đang tải thông báo...</p>
                            </div>
                        </div>
                    </div>
                <?php elseif ($currentTab === 'wishlist'): ?>
                    <!-- Tab: Danh sách yêu thích -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden min-h-[500px]">
                        <!-- Header -->
                        <div class="bg-gradient-to-r from-pink-500 to-rose-600 p-6 text-white">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                                    <i class="fas fa-heart text-xl"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold"><?= __('wishlist') ?></h2>
                                    <p class="text-rose-100 text-sm mt-0.5"><?= count($wishlistItems) ?> <?= __('products_count') ?></p>
                                </div>
                            </div>
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
                                                <?= img_lazy($item['image'], $item['name'], 'w-full h-full object-contain mix-blend-multiply group-hover:scale-110 transition-transform duration-500') ?>
                                                <?php if ($item['old_price'] > $item['price']): ?>
                                                    <div class="absolute top-3 left-3 bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                                                        -<?= round((($item['old_price'] - $item['price']) / $item['old_price']) * 100) ?>%
                                                    </div>
                                                <?php endif; ?>
                                            </a>

                                            <!-- Thông tin -->
                                            <div class="p-4 flex flex-col flex-1">
                                                <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1"><?= htmlspecialchars($item['category_name']) ?></div>
                                                <a href="product_detail.php?id=<?= $item['id'] ?>" class="text-sm font-bold text-gray-800 line-clamp-2 hover:text-primary transition-colors mb-2 min-h-[40px]">
                                                    <?= htmlspecialchars($item['name']) ?>
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
                                                        <button onclick="addToCart(<?= $item['id'] ?>)" 
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
                        <p>Bạn chưa có địa chỉ nào được lưu.</p>
                    </div>`;
                return;
            }

            let html = '';
            items.forEach(item => {
                html += `
                    <div class="p-5 border rounded-xl hover:border-blue-500 transition-all bg-white relative group ${item.is_default ? 'ring-2 ring-blue-100 border-blue-500' : ''}">
                        ${item.is_default ? '<span class="absolute -top-3 left-4 bg-blue-600 text-white text-[10px] font-bold px-2 py-1 rounded-md shadow-sm">MẶC ĐỊNH</span>' : ''}
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
                            <button onclick="setDefaultAddress(${item.id})" class="mt-3 text-xs text-blue-600 font-medium hover:underline">Đặt làm mặc định</button>
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
                title.innerText = 'Cập nhật địa chỉ';
                document.getElementById('addr-id').value = item.id;
                document.getElementById('addr-fullname').value = item.fullname;
                document.getElementById('addr-phone').value = item.phone;
                document.getElementById('addr-address').value = item.address;
                document.getElementById('addr-default').checked = item.is_default;
            } else {
                title.innerText = 'Thêm địa chỉ mới';
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
            if (!confirm('Bạn có chắc chắn muốn xóa địa chỉ này?')) return;
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
                    }
                });
        }

        function renderFullNotiList(items) {
            const list = document.getElementById('full-noti-list');
            if (items.length === 0) {
                list.innerHTML = `<div class="p-10 text-center text-gray-400">Bạn chưa có thông báo nào.</div>`;
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
                Swal.fire('Lỗi', 'Không thể kết nối đến máy chủ', 'error');
            });
        }

        function addToCart(productId) {
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('product_id', productId);
            formData.append('quantity', 1);
            
            fetch(getApiUrl('add_to_cart.php'), {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Thành công',
                        text: '<?= __('added_to_cart') ?>',
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: 'Đến giỏ hàng',
                        cancelButtonText: 'Tiếp tục xem'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'cart.php';
                        }
                    });
                } else {
                    Swal.fire('Thông báo', data.message, 'warning');
                }
            });
        }
    </script>
</body>

</html>