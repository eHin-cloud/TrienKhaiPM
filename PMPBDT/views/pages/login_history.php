<?php
/**
 * ============================================================
 * LOGIN_HISTORY.PHP - TRANG LỊCH SỬ ĐĂNG NHẬP
 * ============================================================
 * 
 * Hiển thị danh sách tất cả các lần đăng nhập của user hiện tại.
 * Bao gồm: thời gian, IP, trình duyệt, thiết bị, trạng thái.
 * Có phân trang.
 */

use App\Service\UserService;

// 1. Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];

// 2. Phân trang
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// 3. Đếm tổng số bản ghi
$stmtCount = $db->prepare("SELECT COUNT(*) FROM login_history WHERE user_id = ?");
$stmtCount->execute([$userId]);
$totalRecords = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// 4. Lấy dữ liệu (JOIN với bảng users để lấy fullname và username hiển thị)
$stmt = $db->prepare("
    SELECT lh.*, u.fullname, u.username 
    FROM login_history lh
    JOIN users u ON lh.user_id = u.id
    WHERE lh.user_id = ? 
    ORDER BY lh.login_time DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $userId, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$loginHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Hàm parse User-Agent để lấy thông tin trình duyệt và thiết bị
 */
function parseBrowserInfo($ua) {
    $browser = 'Unknown';
    $device = 'Desktop';
    $browserIcon = 'fa-globe';

    // Detect browser (Check Cốc Cốc first since it contains Chrome in UA)
    if (preg_match('/CocCoc|coccoc/i', $ua)) {
        $browser = 'Cốc Cốc';
        $browserIcon = 'fa-regular fa-compass';
    } elseif (strpos($ua, 'Edg/') !== false) {
        $browser = 'Microsoft Edge';
        $browserIcon = 'fa-brands fa-edge';
    } elseif (strpos($ua, 'OPR/') !== false || strpos($ua, 'Opera') !== false) {
        $browser = 'Opera';
        $browserIcon = 'fa-brands fa-opera';
    } elseif (strpos($ua, 'Chrome/') !== false) {
        $browser = 'Google Chrome';
        $browserIcon = 'fa-brands fa-chrome';
    } elseif (strpos($ua, 'Firefox/') !== false) {
        $browser = 'Mozilla Firefox';
        $browserIcon = 'fa-brands fa-firefox-browser';
    } elseif (strpos($ua, 'Safari/') !== false && strpos($ua, 'Chrome') === false) {
        $browser = 'Safari';
        $browserIcon = 'fa-brands fa-safari';
    }

    // Detect device
    $deviceIcon = 'fa-desktop';
    if (preg_match('/Mobile|Android|iPhone|iPod/i', $ua)) {
        $device = 'Mobile';
        $deviceIcon = 'fa-mobile-screen-button';
    } elseif (preg_match('/iPad|Tablet/i', $ua)) {
        $device = 'Tablet';
        $deviceIcon = 'fa-tablet-screen-button';
    }

    // Detect OS (Check Android and iOS before Linux since mobile UAs contain Linux)
    $os = 'Unknown';
    if (preg_match('/Windows NT 10/i', $ua)) $os = 'Windows 10/11';
    elseif (preg_match('/Windows NT 6\.3/i', $ua)) $os = 'Windows 8.1';
    elseif (preg_match('/Android/i', $ua)) $os = 'Android';
    elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) $os = 'iOS';
    elseif (preg_match('/Mac OS X/i', $ua)) $os = 'macOS';
    elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';

    return [
        'browser' => $browser,
        'browserIcon' => $browserIcon,
        'device' => $device,
        'deviceIcon' => $deviceIcon,
        'os' => $os
    ];
}

// Xác định tab hiện tại
$currentTab = 'login_history';
?>

<?php require_once __DIR__ . '/../partials/header.php'; ?>

<!-- CSS cho hiệu ứng -->
<style>
    .history-row { transition: all 0.2s ease; }
    .history-row:hover { transform: translateX(4px); }
    .status-pulse { animation: pulse-dot 2s infinite; }
    @keyframes pulse-dot {
        0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
        70% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
        100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
    .fade-in { animation: fadeInUp 0.4s ease forwards; opacity: 0; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="container mx-auto py-10 px-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-8"><?= __('account_title') ?></h1>

    <div class="flex flex-col md:flex-row gap-8">
        <!-- Sidebar Navigation (giống profile.php) -->
        <div class="w-full md:w-1/4">
            <div class="bg-white rounded-xl shadow-sm p-4 space-y-2">
                <a href="profile.php?tab=profile"
                    class="flex items-center p-3 rounded-lg transition-colors text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-user w-6"></i> <span><?= __('personal_info') ?></span>
                </a>
                <a href="profile.php?tab=security"
                    class="flex items-center p-3 rounded-lg transition-colors text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-lock w-6"></i> <span><?= __('security') ?></span>
                </a>
                <a href="profile.php?tab=orders"
                    class="flex items-center p-3 rounded-lg transition-colors text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-shopping-bag w-6"></i> <span><?= __('order_history') ?></span>
                </a>
                <a href="profile.php?tab=addresses"
                    class="flex items-center p-3 rounded-lg transition-colors text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-map-marker-alt w-6"></i> <span><?= __('address_book') ?></span>
                </a>
                <a href="profile.php?tab=notifications"
                    class="flex items-center p-3 rounded-lg transition-colors text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-bell w-6"></i> <span><?= __('notifications') ?></span>
                </a>
                <a href="profile.php?tab=wishlist"
                    class="flex items-center p-3 rounded-lg transition-colors text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-heart w-6"></i> <span><?= __('wishlist') ?></span>
                </a>
                <a href="login_history.php"
                    class="flex items-center p-3 rounded-lg transition-colors bg-blue-50 text-blue-600 font-semibold">
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

        <!-- Main Content -->
        <div class="w-full md:w-3/4">
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-white">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                            <i class="fas fa-clock-rotate-left text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold"><?= __('login_history_title') ?></h2>
                            <p class="text-blue-200 text-sm mt-0.5"><?= __('login_history_desc') ?></p>
                        </div>
                    </div>
                    <!-- Stats -->
                    <div class="mt-4 flex gap-6">
                        <div class="bg-white/10 rounded-lg px-4 py-2 backdrop-blur-sm">
                            <span class="text-blue-200 text-xs block"><?= __('records') ?></span>
                            <span class="text-white font-bold text-lg"><?= $totalRecords ?></span>
                        </div>
                        <?php
                        $successCount = 0;
                        $failCount = 0;
                        $stmtStats = $db->prepare("SELECT status, COUNT(*) as cnt FROM login_history WHERE user_id = ? GROUP BY status");
                        $stmtStats->execute([$userId]);
                        while ($row = $stmtStats->fetch(PDO::FETCH_ASSOC)) {
                            if ($row['status'] === 'success') $successCount = $row['cnt'];
                            else $failCount = $row['cnt'];
                        }
                        ?>
                        <div class="bg-white/10 rounded-lg px-4 py-2 backdrop-blur-sm">
                            <span class="text-blue-200 text-xs block"><?= __('success') ?></span>
                            <span class="text-green-300 font-bold text-lg"><?= $successCount ?></span>
                        </div>
                        <div class="bg-white/10 rounded-lg px-4 py-2 backdrop-blur-sm">
                            <span class="text-blue-200 text-xs block"><?= __('failed') ?></span>
                            <span class="text-red-300 font-bold text-lg"><?= $failCount ?></span>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="p-6">
                    <?php if (empty($loginHistory)): ?>
                        <div class="text-center py-16">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-clock-rotate-left text-3xl text-gray-400"></i>
                            </div>
                            <p class="text-gray-500 text-lg font-medium"><?= __('no_login_history') ?></p>
                        </div>
                    <?php else: ?>
                        <!-- Desktop Table -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                                        <th class="pb-3 pl-4">#</th>
                                        <th class="pb-3"><?= __('login_time') ?></th>
                                        <th class="pb-3"><?= __('ip_address') ?></th>
                                        <th class="pb-3"><?= __('browser') ?></th>
                                        <th class="pb-3"><?= __('device') ?></th>
                                        <th class="pb-3 text-center"><?= __('status') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loginHistory as $index => $log):
                                        $info = parseBrowserInfo($log['user_agent'] ?? '');
                                        $isSuccess = $log['status'] === 'success';
                                        $isCurrentSession = ($index === 0 && $page === 1 && $isSuccess);
                                        $delay = $index * 0.05;
                                    ?>
                                        <tr class="history-row border-b border-gray-50 hover:bg-blue-50/50 fade-in <?= $isCurrentSession ? 'bg-green-50/50' : '' ?>" style="animation-delay: <?= $delay ?>s">
                                            <td class="py-4 pl-4">
                                                <span class="text-xs font-mono text-gray-400"><?= $offset + $index + 1 ?></span>
                                            </td>
                                            <td class="py-4">
                                                <div class="flex items-center gap-2">
                                                    <?php if ($isCurrentSession): ?>
                                                        <span class="w-2 h-2 bg-green-500 rounded-full status-pulse flex-shrink-0"></span>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-800">
                                                            <?= date('d/m/Y', strtotime($log['login_time'])) ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            <?= date('H:i:s', strtotime($log['login_time'])) ?>
                                                            <?php if ($isCurrentSession): ?>
                                                                <span class="text-green-600 font-medium ml-1">(<?= __('current_session') ?>)</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4">
                                                <code class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded font-mono"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></code>
                                                <?php if (!empty($log['location'])): ?>
                                                    <div class="text-[11px] text-gray-400 mt-1 flex items-center gap-1">
                                                        <i class="fa-solid fa-location-dot text-rose-500 text-[10px]"></i>
                                                        <span><?= htmlspecialchars($log['location']) ?></span>
                                                     </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-4">
                                                <div class="flex items-center gap-2">
                                                    <i class="<?= $info['browserIcon'] ?> text-gray-500"></i>
                                                    <div>
                                                        <div class="text-sm text-gray-700"><?= $info['browser'] ?></div>
                                                        <div class="text-xs text-gray-400"><?= $info['os'] ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4">
                                                <div class="flex items-center gap-2">
                                                    <i class="fa-solid <?= $info['deviceIcon'] ?> text-gray-500"></i>
                                                    <span class="text-sm text-gray-700"><?= $info['device'] ?></span>
                                                </div>
                                            </td>
                                            <td class="py-4 text-center">
                                                <?php if ($isSuccess): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                                        <i class="fa-solid fa-circle-check text-[10px]"></i> <?= __('success') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                                        <i class="fa-solid fa-circle-xmark text-[10px]"></i> <?= __('failed') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Cards -->
                        <div class="md:hidden space-y-3">
                            <?php foreach ($loginHistory as $index => $log):
                                $info = parseBrowserInfo($log['user_agent'] ?? '');
                                $isSuccess = $log['status'] === 'success';
                                $isCurrentSession = ($index === 0 && $page === 1 && $isSuccess);
                            ?>
                                <div class="bg-gray-50 rounded-xl p-4 border <?= $isCurrentSession ? 'border-green-200 bg-green-50/50' : 'border-gray-100' ?> fade-in" style="animation-delay: <?= $index * 0.05 ?>s">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex items-center gap-2">
                                            <?php if ($isCurrentSession): ?>
                                                <span class="w-2 h-2 bg-green-500 rounded-full status-pulse"></span>
                                            <?php endif; ?>
                                            <div>
                                                <div class="font-medium text-gray-800 text-sm"><?= date('d/m/Y H:i:s', strtotime($log['login_time'])) ?></div>
                                                <?php if ($isCurrentSession): ?>
                                                    <span class="text-green-600 text-xs font-medium"><?= __('current_session') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($isSuccess): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700">
                                                <i class="fa-solid fa-circle-check"></i> <?= __('success') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700">
                                                <i class="fa-solid fa-circle-xmark"></i> <?= __('failed') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div class="flex items-center gap-1.5 text-gray-600">
                                            <i class="fa-solid fa-network-wired text-gray-400 w-4"></i>
                                            <code class="bg-white px-1.5 py-0.5 rounded"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></code>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-gray-600">
                                            <i class="<?= $info['browserIcon'] ?> text-gray-400 w-4"></i>
                                            <?= $info['browser'] ?>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-gray-600">
                                            <i class="fa-solid <?= $info['deviceIcon'] ?> text-gray-400 w-4"></i>
                                            <?= $info['device'] ?>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-gray-600">
                                            <i class="fa-solid fa-microchip text-gray-400 w-4"></i>
                                            <?= $info['os'] ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($log['location'])): ?>
                                        <div class="mt-2.5 pt-2.5 border-t border-gray-100 flex items-center gap-1.5 text-[11px] text-gray-500">
                                            <i class="fa-solid fa-location-dot text-rose-500 w-4 text-center"></i>
                                            <span><?= htmlspecialchars($log['location']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4">
                                <div class="text-sm text-gray-500">
                                    <?= __('showing') ?> <span class="font-semibold text-gray-700"><?= $offset + 1 ?>-<?= min($offset + $limit, $totalRecords) ?></span> 
                                    <?= __('of') ?> <span class="font-semibold text-gray-700"><?= $totalRecords ?></span> <?= __('records') ?>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($page > 1): ?>
                                        <a href="login_history.php?page=<?= $page - 1 ?>"
                                            class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-all text-sm font-medium shadow-sm">
                                            <i class="fas fa-chevron-left mr-1.5"></i> <?= __('previous') ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $startPage + 4);
                                    if ($endPage - $startPage < 4) $startPage = max(1, $endPage - 4);
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <a href="login_history.php?page=<?= $i ?>"
                                            class="w-10 h-10 flex items-center justify-center rounded-lg border transition-all text-sm font-bold <?= $i === $page ? 'bg-blue-600 text-white border-blue-600 shadow-md ring-2 ring-blue-100' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400 hover:text-blue-600' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <a href="login_history.php?page=<?= $page + 1 ?>"
                                            class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-all text-sm font-medium shadow-sm">
                                            <?= __('next') ?> <i class="fas fa-chevron-right ml-1.5"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
