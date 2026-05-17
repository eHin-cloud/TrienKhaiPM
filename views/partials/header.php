<?php
/**
 * ============================================================
 * HEADER.PHP - GIAO DIỆN HEADER & XỬ LÝ ĐĂNG NHẬP/ĐĂNG KÝ
 * ============================================================
 * 
 * File được require_once bởi tất cả các trang public (index, cart, 
 * checkout, payment, product_detail, track_order).
 * 
 * CHỨC NĂNG:
 * 1. XỬ LÝ XÁC THỰC (PHP):
 *    - Đăng nhập (action=login): Kiểm tra username + password
 *    - Đăng ký (action=register): Tạo tài khoản mới (role=customer)
 *    - Đăng xuất (action=logout): Hủy session, redirect về trang chủ
 *    - Validate mật khẩu: tối thiểu 8 ký tự + có chữ cái
 * 
 * 2. GIAO DIỆN HEADER (HTML):
 *    - Logo + Thanh tìm kiếm (desktop + mobile)
 *    - Menu danh mục (dropdown desktop + mobile grid icons)
 *    - Nút: Tra cứu đơn, Giỏ hàng (có badge đếm), User menu
 *    - Navigation bar danh mục (desktop)
 * 
 * 3. MODAL ĐĂNG NHẬP/ĐĂNG KÝ:
 *    - 2 tabs: Đăng nhập / Đăng ký (chuyển đổi bằng JS)
 *    - Validate mật khẩu real-time (JS)
 *    - Tự động mở modal khi có lỗi xác thực
 * 
 * BIẾN GLOBAL ĐƯỢC TẠO:
 * - $categories    : Mảng danh mục (dùng cho menu + trang index)
 * - $current_page  : Tên file hiện tại (VD: 'index.php')
 * - $cat_id_filter : ID danh mục đang lọc
 * - $search_query  : Từ khóa tìm kiếm hiện tại
 * 
 * @requires database.php - Hàm getAllCategories(), kết nối $db
 */

// ==========================================
// XỬ LÝ ĐĂNG NHẬP / ĐĂNG KÝ / ĐĂNG XUẤT
// ==========================================
$auth_error = '';            // Thông báo lỗi xác thực (nếu có)
$auth_success = '';          // Thông báo thành công (quên mật khẩu)
$show_register_tab = false;  // Cờ đánh dấu hiện tab Đăng ký (khi đăng ký lỗi)
$show_forgot_tab = false;    // Cờ đánh dấu hiện tab Quên mật khẩu
$forgot_step = 1;            // Bước 1: gửi OTP, Bước 2: nhập OTP + mật khẩu mới
$forgot_email = '';          // Email dùng trong luồng quên mật khẩu

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- ĐĂNG NHẬP ---
    if ($_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        // Truy vấn so khớp username + password trong DB
        // LƯU Ý: Mật khẩu lưu dạng plain text (nên dùng password_hash() ở production)
        // Chỉ truy vấn theo username, sau đó xác thực mật khẩu bằng password_verify()
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !password_verify($password, $user['password'])) {
            $user = null; // Mật khẩu không khớp
        }

        if ($user) {
            if (isset($user['is_banned']) && $user['is_banned'] == 1) {
                $auth_error = "Tài khoản của bạn đã bị khóa tạm thời do có dấu hiệu bất thường. Vui lòng liên hệ Nhân viên qua zalo để biết thêm chi tiết.";
                record_login_history($db, $user['id'], 'failed');
            } else {
                $has2FA = (int)($user['two_factor_enabled'] ?? 0) === 1;
                if ($has2FA) {
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }

                    $otp = (string) random_int(100000, 999999);
                    $_SESSION['pending_2fa_user_id'] = $user['id'];
                    $_SESSION['pending_2fa_username'] = $user['username'];
                    $_SESSION['pending_2fa_name'] = $user['fullname'];
                    $_SESSION['pending_2fa_role'] = $user['role'];
                    $_SESSION['pending_2fa_email'] = $user['email'];
                    $_SESSION['pending_2fa_otp'] = $otp;
                    $_SESSION['pending_2fa_expires_at'] = time() + 600;
                    $_SESSION['pending_2fa_attempts'] = 0;
                    $_SESSION['pending_2fa_started_at'] = time();

                    require_once __DIR__ . '/../../core/mail_helper.php';
                    require_once __DIR__ . '/../../core/otp_mail_templates.php';
                    $subject = 'DienMayPro - Mã OTP xác minh đăng nhập';
                    $body = buildOtpEmailTemplate(
                        'Xác minh đăng nhập',
                        'Mã OTP bảo mật 2 lớp',
                        $otp,
                        'Bạn vừa đăng nhập vào tài khoản DienMayPro. Vui lòng dùng mã OTP bên dưới để hoàn tất đăng nhập.'
                    );
                    $sent = sendEmail($user['email'], $user['fullname'] ?: 'Khách hàng', $subject, $body);
                    if (!$sent) {
                        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_username'], $_SESSION['pending_2fa_name'], $_SESSION['pending_2fa_role'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_otp'], $_SESSION['pending_2fa_expires_at'], $_SESSION['pending_2fa_attempts'], $_SESSION['pending_2fa_started_at']);
                        $auth_error = 'Không thể gửi mã OTP 2FA qua email. Vui lòng thử lại sau.';
                    } else {
                        header('Location: /' . trim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/index.php?route=two_factor.php');
                        exit;
                    }
                }

                // Đăng nhập thành công -> Lưu thông tin vào session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];

                record_login_history($db, $user['id'], 'success');

                $redirect = $_SERVER['REQUEST_URI'];
                header("Location: " . ($redirect ?: 'index.php'));
                exit;
            }
        } else {
            // Ghi lịch sử đăng nhập thất bại
            try {
                $stmtFind = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmtFind->execute([$username]);
                $foundUser = $stmtFind->fetch(PDO::FETCH_ASSOC);
                $failUserId = $foundUser ? $foundUser['id'] : 0;
                record_login_history($db, $failUserId, 'failed');
            } catch (Exception $e) { /* Bỏ qua nếu bảng chưa tạo */
            }

            $auth_error = __('auth_error');
        }
    }

    // --- ĐĂNG KÝ ---
    elseif ($_POST['action'] === 'register') {
        // Khởi tạo UserService để xử lý logic đăng ký
        $userService = new \App\Service\UserService($db);
        $result = $userService->registerUser($_POST);

        if ($result['success']) {
            // Tự động đăng nhập sau khi đăng ký thành công
            $_SESSION['user_id'] = $result['userId'];
            $_SESSION['fullname'] = trim($_POST['fullname']);
            $_SESSION['role'] = 'customer';

            // Redirect an toàn về đúng trang hiện tại (bao gồm các tham số)
            $redirect = $_SERVER['REQUEST_URI'];
            header("Location: " . ($redirect ?: 'index.php'));
            exit;
        } else {
            $auth_error = $result['message'];
            $show_register_tab = true;
        }
    }

    // --- QUÊN MẬT KHẨU: GỬI OTP ---
    elseif ($_POST['action'] === 'forgot_password_send_otp') {
        $userService = new \App\Service\UserService($db);
        $forgot_email = trim($_POST['email'] ?? '');
        $result = $userService->requestPasswordResetOtp($_POST);

        if ($result['success']) {
            $auth_success = $result['message'];
            $show_forgot_tab = true;
            $forgot_step = 2;
        } else {
            $auth_error = $result['message'];
            $show_forgot_tab = true;
            $forgot_step = 1;
        }
    }

    // --- QUÊN MẬT KHẨU: XÁC MINH OTP + ĐỔI MẬT KHẨU ---
    elseif ($_POST['action'] === 'forgot_password_reset') {
        $userService = new \App\Service\UserService($db);
        $forgot_email = trim($_POST['email'] ?? '');
        $result = $userService->resetPasswordWithOtp($_POST);

        if ($result['success']) {
            $auth_success = $result['message'];
            $show_forgot_tab = false; // quay về tab login
            $forgot_step = 1;
        } else {
            $auth_error = $result['message'];
            $show_forgot_tab = true;
            $forgot_step = 2;
        }
    }


    // --- ĐĂNG XUẤT ---
    elseif ($_POST['action'] === 'logout') {
        session_destroy();
        // Redirect về trang chủ public
        header("Location: /" . ltrim(dirname($_SERVER['SCRIPT_NAME']), '/'));
        exit;
    }
}

// ==========================================
// TẢI DỮ LIỆU CHO MENU NAVIGATION
// ==========================================
$categories = getAllCategories($db);                                        // Tất cả danh mục (cho menu)
$current_page = basename($_SERVER['PHP_SELF']);                             // Tên file hiện tại
$cat_id_filter = isset($_GET['cat_id']) ? (int) $_GET['cat_id'] : 0;       // ID danh mục đang lọc
$search_query = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; // Từ khóa tìm kiếm
?>

<!-- ==========================================
     BẮT ĐẦU HTML DOCUMENT
     ========================================== -->
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <!-- Viewport setting cho responsive, chặn zoom trên mobile -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <!-- Dynamic SEO Meta Tags -->
    <title><?= isset($meta_title) ? e($meta_title) : 'Điện Máy PRO - Chuyên nghiệp & Tận tâm' ?></title>
    <meta name="description" content="<?= isset($meta_desc) ? e($meta_desc) : 'Hệ thống bán lẻ điện máy hàng đầu Việt Nam. Tivi, Tủ lạnh, Máy giặt, Gia dụng chính hãng, giá tốt nhất.' ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= isset($meta_title) ? e($meta_title) : 'Điện Máy PRO' ?>">
    <meta property="og:description" content="<?= isset($meta_desc) ? e($meta_desc) : 'Hệ thống bán lẻ điện máy hàng đầu Việt Nam.' ?>">
    <meta property="og:image" content="<?= isset($meta_image) ? e($meta_image) : 'https://dienmaypro.vn/assets/og-image.jpg' ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="<?= isset($meta_title) ? e($meta_title) : 'Điện Máy PRO' ?>">
    <meta property="twitter:description" content="<?= isset($meta_desc) ? e($meta_desc) : 'Hệ thống bán lẻ điện máy hàng đầu Việt Nam.' ?>">
    <!-- Favicon hình sét vàng (inline SVG base64) -->
    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMjAgNTEyIj48cGF0aCBmaWxsPSIjZmZjZjAwIiBkPSJNMjQwLjUgMjI0SDM1MkMzNjUuMyAyMjQgMzc3LjMgMjMyLjMgMzgxLjEgMjQ0LjdDMzg2LjYgMjU3LjIgMzgzLjEgMjcxLjMgMzczLjEgMjgwLjFMMTE3LjEgNTA0LjFDMTA1LjggNTEzLjkgODkuMjcgNTE0LjcgNzcuMTkgNTA1LjlDNjUuMSA0OTcuMSA2MC43IDQ4MS4xIDY2LjM2 NDY3LjRMMTMxLjYgMzA0LjVINDhDMzQuNzMgMzA0LjUgMjIuNjcgMjk2LjIgMTguMTEgMjgzLjdDMTMuNTQgMjcxLjIgMTcuMSAyNTcuMSAyNy4xIDI0OC4xTDI4My4xIDI0LjFDMjk0LjIgMTQuMjggMzEwLjcgMTMuNTMgMzIyLjggMjIuMzRDMzM0LjkgMzEuMTUgMzM5LjMgNDcuMSAzMzMuNiA2MC44MUwyNDAuNSAyMjR6Ii8+PC9zdmc+">
    <!-- TailwindCSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome 6 - Icon library -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Cấu hình màu sắc tuỳ chỉnh cho TailwindCSS -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0046ab',    // Xanh dương chủ đạo
                        secondary: '#ffcf00',  // Vàng highlight
                        danger: '#d70018',     // Đỏ (giá, sale)
                        navBg: '#00388a'       // Xanh đậm cho nav bar
                    }
                }
            }
        }
    </script>

    <!-- CSS tùy chỉnh -->
    <style>
        /* Font Inter từ Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f2f6;
        }

        /* Ẩn scrollbar nhưng vẫn scroll được */
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Badge sale gradient đỏ */
        .sale-badge {
            background: linear-gradient(to right, #d70018, #ff4d4f);
        }

        /* Animation tab chuyển mượt */
        .auth-panel {
            transition: opacity .28s ease, transform .28s ease, max-height .28s ease;
            will-change: opacity, transform;
        }

        .auth-panel.is-hidden {
            opacity: 0;
            transform: translateY(8px);
            pointer-events: none;
            max-height: 0;
            overflow: hidden;
            margin: 0 !important;
        }

        .auth-panel.is-visible {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
            max-height: 1200px;
        }

        .auth-tab-button {
            transition: color .2s ease, border-color .2s ease, background-color .2s ease, transform .2s ease;
        }

        .auth-tab-button:hover {
            transform: translateY(-1px);
        }

        /* Nút Yêu thích ở thẻ sản phẩm (góc phải) */
        .btn-wishlist-card {
            position: absolute;
            top: 96px;
            right: 8px;
            transform: scale(0.8);
            background: rgba(255, 255, 255, 0.95);
            color: #f43f5e;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 30;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .product-card:hover .btn-wishlist-card {
            opacity: 1;
            transform: scale(1);
        }

        .btn-wishlist-card:hover, .btn-wishlist-card.active {
            background: #f43f5e;
            color: white;
            transform: scale(1.1) !important;
        }
    </style>

    <!-- JS cho Tìm kiếm thông minh và Thông báo -->
    <script>
        // Helper để lấy đường dẫn API chính xác (Global)
        function getApiUrl(apiPath) {
            const currentDir = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
            return currentDir + apiPath;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // 1. TÌM KIẾM THÔNG MINH
            const searchInput = document.getElementById('search-input');
            const suggestions = document.getElementById('search-suggestions');
            const suggestionResults = document.getElementById('suggestion-results');
            let debounceTimer;

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    handleSearchInput(this, suggestions, suggestionResults);
                });

                // Đóng khi click ngoài
                document.addEventListener('click', (e) => {
                    if (!searchInput.contains(e.target) && !suggestions.contains(e.target)) {
                        suggestions.classList.add('hidden');
                    }
                });
            }

            // Mobile Search
            const searchInputMobile = document.getElementById('search-input-mobile');
            const suggestionsMobile = document.getElementById('search-suggestions-mobile');
            const suggestionResultsMobile = document.getElementById('suggestion-results-mobile');

            if (searchInputMobile) {
                searchInputMobile.addEventListener('input', function() {
                    handleSearchInput(this, suggestionsMobile, suggestionResultsMobile);
                });
                document.addEventListener('click', (e) => {
                    if (!searchInputMobile.contains(e.target) && !suggestionsMobile.contains(e.target)) {
                        suggestionsMobile.classList.add('hidden');
                    }
                });
            }

            function handleSearchInput(input, suggestBox, resultBox) {
                clearTimeout(debounceTimer);
                const keyword = input.value.trim();
                if (keyword.length < 2) {
                    suggestBox.classList.add('hidden');
                    return;
                }

                debounceTimer = setTimeout(() => {
                    fetch(getApiUrl(`api/search.php?keyword=${encodeURIComponent(keyword)}`))
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                renderSuggestions(data.data, keyword, suggestBox, resultBox);
                            }
                        });
                }, 300);
            }

            function renderSuggestions(data, keyword, suggestBox, resultBox) {
                const { products, categories } = data;
                if (products.length === 0 && categories.length === 0) {
                    suggestBox.classList.add('hidden');
                    return;
                }

                let html = '';
                
                if (categories.length > 0) {
                    html += `<div class="p-2 bg-gray-50 text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b">Danh mục gợi ý</div>`;
                    categories.forEach(cat => {
                        html += `
                            <a href="index.php?cat_id=${cat.id}" class="flex items-center px-4 py-2 hover:bg-gray-100 transition border-b border-gray-50">
                                <i class="fa-solid fa-layer-group text-blue-500 mr-3 text-sm"></i>
                                <span class="text-sm font-medium text-gray-700">${cat.display_name}</span>
                            </a>
                        `;
                    });
                }

                if (products.length > 0) {
                    html += `<div class="p-2 bg-gray-50 text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b">Sản phẩm gợi ý</div>`;
                    products.forEach(p => {
                        const price = new Intl.NumberFormat('vi-VN').format(p.price);
                        html += `
                            <a href="product_detail.php?id=${p.id}" class="flex items-center gap-3 p-3 hover:bg-gray-100 transition border-b border-gray-50">
                                <img src="${p.image}" class="w-12 h-12 object-contain bg-white rounded border p-1" alt="${p.name}">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-semibold text-gray-800 truncate">${p.name}</h4>
                                    <p class="text-xs font-bold text-red-600">${price}đ</p>
                                </div>
                            </a>
                        `;
                    });
                }

                resultBox.innerHTML = html;
                suggestBox.classList.remove('hidden');
            }

            // 2. THÔNG BÁO
            const notiBtn = document.getElementById('noti-btn');
            const notiList = document.getElementById('noti-list');
            const notiBadge = document.getElementById('noti-count-badge');
            let notiLoaded = false;

            if (notiBtn) {
                notiBtn.addEventListener('mouseenter', function() {
                    if (!notiLoaded) {
                        loadNotifications();
                        notiLoaded = true;
                    }
                });
            }

            window.loadNotifications = function() {
                fetch(getApiUrl('api/notification.php?action=list'))
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            renderNotifications(data.data.items);
                            updateNotiBadge(data.data.unread_count);
                        }
                    });
            }

            function renderNotifications(items) {
                if (items.length === 0) {
                    notiList.innerHTML = `<div class="p-10 text-center text-gray-400 text-sm">Bạn không có thông báo nào.</div>`;
                    return;
                }

                let html = '';
                items.forEach(item => {
                    html += `
                        <div class="p-3 border-b hover:bg-gray-50 transition cursor-pointer relative ${!item.is_read ? 'bg-blue-50/50' : ''}" onclick="markRead(${item.id})">
                            ${!item.is_read ? '<span class="absolute right-3 top-3 w-2 h-2 bg-blue-500 rounded-full"></span>' : ''}
                            <h5 class="text-xs font-bold text-gray-800 pr-4">${item.title}</h5>
                            <p class="text-xs text-gray-600 mt-1 leading-relaxed">${item.message}</p>
                            <span class="text-[10px] text-gray-400 mt-2 block">${formatTime(item.created_at)}</span>
                        </div>
                    `;
                });
                notiList.innerHTML = html;
            }

            window.markRead = function(id) {
                fetch(getApiUrl('api/notification.php?action=read'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                }).then(() => loadNotifications());
            }

            window.markAllRead = function() {
                fetch(getApiUrl('api/notification.php?action=read_all'), {
                    method: 'POST'
                }).then(() => loadNotifications());
            }

            function updateNotiBadge(count) {
                if (count > 0) {
                    notiBadge.innerText = count;
                    notiBadge.classList.remove('hidden');
                } else {
                    notiBadge.classList.add('hidden');
                }
            }
            
            // 3. SO SÁNH SẢN PHẨM
            <?php
            $initialCompareList = [];
            if (!empty($_SESSION['compare_list'])) {
                try {
                    $ids = implode(',', array_map('intval', $_SESSION['compare_list']));
                    $stmt = $db->query("SELECT id, name, image FROM products WHERE id IN ($ids) ORDER BY FIELD(id, $ids)");
                    if ($stmt) {
                        $initialCompareList = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                } catch (Exception $e) {
                    // Fail silently
                }
            }
            ?>
            const initialCompareList = <?php echo json_encode($initialCompareList); ?>;
            const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
            window.toggleCompare = function(id, btn) {
                // Xác định action: Nếu đã có trong danh sách thì là remove, ngược lại là add
                // Ta có thể kiểm tra danh sách hiện tại qua DOM hoặc gọi API. 
                // Ở đây ta gọi API với action 'add' trước, nếu API báo đã có, ta gọi 'remove' (hoặc ngược lại)
                // Cách tốt nhất là truyền action cụ thể hoặc để API tự xử lý. 
                // Nhưng để đơn giản và an toàn, ta sẽ dùng list hiện có trong renderCompareBar để check.
                
                let currentItems = [];
                const bar = document.getElementById('compare-sticky-bar');
                if (bar && !bar.classList.contains('hidden')) {
                    // Lấy ID từ các nút xóa hoặc lưu list vào biến global
                }

                const formData = new FormData();
                // Ta sẽ gửi action mặc định là 'add'. Nếu API báo đã có, ta sẽ thực hiện 'remove'.
                formData.append('action', 'add'); 
                formData.append('product_id', id);
                formData.append('csrf_token', csrfToken);

                fetch(getApiUrl('ajax_compare.php'), {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Nếu message báo đã có -> Ta thực hiện remove
                        if (data.message === 'Sản phẩm này đã có trong danh sách so sánh.') {
                            const removeData = new FormData();
                            removeData.append('action', 'remove');
                            removeData.append('product_id', id);
                            removeData.append('csrf_token', csrfToken);
                            
                            return fetch(getApiUrl('ajax_compare.php'), {
                                method: 'POST',
                                body: removeData
                            }).then(res2 => res2.json());
                        }
                        return data;
                    } else {
                        throw new Error(data.message);
                    }
                })
                .then(finalData => {
                    if (finalData && finalData.success) {
                        updateCompareBadge(finalData.count, finalData.full_list);
                        if (finalData.message) {
                            Swal.fire({
                                title: 'Thông báo',
                                text: finalData.message,
                                icon: 'success',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        }
                    }
                })
                .catch(err => {
                    if (err.message === 'Bạn chỉ nên so sánh các sản phẩm cùng loại.') {
                        Swal.fire({
                            title: 'Khác loại sản phẩm',
                            text: 'Bạn chỉ nên so sánh các sản phẩm cùng loại. Bạn có muốn xóa danh sách hiện tại để bắt đầu so sánh loại sản phẩm mới này không?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Đồng ý, xóa và thêm mới',
                            cancelButtonText: 'Bỏ qua',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const forceData = new FormData();
                                forceData.append('action', 'force_add');
                                forceData.append('product_id', id);
                                forceData.append('csrf_token', csrfToken);
                                
                                fetch(getApiUrl('ajax_compare.php'), {
                                    method: 'POST',
                                    body: forceData
                                })
                                .then(res => res.json())
                                .then(data => {
                                    if (data.success) {
                                        updateCompareBadge(data.count, data.full_list);
                                        Swal.fire({
                                            title: 'Thành công',
                                            text: data.message,
                                            icon: 'success',
                                            toast: true,
                                            position: 'top-end',
                                            showConfirmButton: false,
                                            timer: 3000
                                        });
                                    }
                                });
                            }
                        });
                    } else {
                        Swal.fire('Thông báo', err.message, 'warning');
                    }
                });
            }

            let currentCompareCategoryId = null;

            window.updateCompareBadge = function(count, fullList = []) {
                if (fullList.length > 0) {
                    currentCompareCategoryId = fullList[0].category_id;
                } else {
                    currentCompareCategoryId = null;
                }
                renderCompareBar(fullList);
            }

            window.renderCompareBar = function(items) {
                const bar = document.getElementById('compare-sticky-bar');
                const listContainer = document.getElementById('compare-bar-items');
                const countBadge = document.getElementById('compare-bar-count');
                
                if (!items || items.length === 0) {
                    bar.classList.add('hidden');
                    return;
                }

                bar.classList.remove('hidden');
                countBadge.innerText = items.length;
                
                // Cập nhật text nút So sánh chính (để hiện số lượng cả khi thu gọn)
                const mainBtn = document.getElementById('compare-main-btn');
                if (mainBtn) {
                    mainBtn.innerHTML = `So sánh (${items.length}) <i class="fa-solid fa-right-left text-[10px]"></i>`;
                }

                let html = '';
                // Render tối đa 3 slot
                for (let i = 0; i < 3; i++) {
                    const item = items[i];
                    if (item) {
                        html += `
                            <div class="relative w-full md:w-48 bg-white border border-gray-100 rounded-2xl p-2 flex items-center gap-2 shadow-sm group border-l-4 border-l-primary">
                                <button onclick="toggleCompare(${item.id})" class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-gray-100 text-gray-500 rounded-full flex items-center justify-center hover:bg-red-500 hover:text-white transition-all z-10 shadow-sm border border-gray-100">
                                    <i class="fa-solid fa-xmark text-[10px]"></i>
                                </button>
                                <div class="w-10 h-10 shrink-0 bg-gray-50 rounded-lg flex items-center justify-center overflow-hidden p-1">
                                    <img src="${item.image}" class="w-full h-full object-contain">
                                </div>
                                <p class="text-[10px] font-bold text-gray-700 line-clamp-2 leading-tight">${item.name}</p>
                            </div>
                        `;
                    } else {
                        html += `
                            <div onclick="openCompareSearch()" 
                                 class="hidden md:flex w-48 border-2 border-dashed border-gray-200 rounded-2xl items-center justify-center p-2 opacity-60 bg-gray-50/30 cursor-pointer hover:border-primary hover:bg-white transition-all group/add">
                                <i class="fa-solid fa-plus text-gray-300 mr-2 text-[10px] group-hover/add:text-primary transition-colors"></i>
                                <span class="text-[10px] font-medium text-gray-400 italic group-hover/add:text-primary transition-colors">Thêm...</span>
                            </div>
                        `;
                    }
                }
                listContainer.innerHTML = html;
            }

            window.openCompareSearch = function() {
                document.getElementById('compare-search-modal').classList.remove('hidden');
                const input = document.getElementById('compare-search-input');
                input.focus();
                
                // Nếu chưa có từ khóa và đã có danh mục so sánh -> Hiển thị gợi ý
                if (!input.value.trim() && currentCompareCategoryId) {
                    const resultsContainer = document.getElementById('compare-search-results');
                    resultsContainer.innerHTML = `
                        <div class="flex items-center justify-center py-10">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                        </div>`;
                        
                    fetch(getApiUrl(`api/search.php?cat_id=${currentCompareCategoryId}`))
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.data.products.length > 0) {
                                renderCompareSearchResults(data.data.products, true);
                            }
                        });
                }
            }

            window.closeCompareSearch = function() {
                document.getElementById('compare-search-modal').classList.add('hidden');
                document.getElementById('compare-search-input').value = '';
                document.getElementById('compare-search-results').innerHTML = `
                    <div class="text-center py-10 text-gray-400">
                        <i class="fa-solid fa-keyboard text-3xl mb-3 block opacity-20"></i>
                        <p class="text-sm italic">Nhập tên sản phẩm để tìm kiếm...</p>
                    </div>`;
            }

            let searchTimeout = null;
            window.handleCompareSearch = function(keyword) {
                const resultsContainer = document.getElementById('compare-search-results');
                if (!keyword.trim()) {
                    // Nếu xóa trắng ô search -> Nếu có danh mục đang so sánh thì lại hiện gợi ý
                    if (currentCompareCategoryId) {
                        window.openCompareSearch(); // Gọi lại hàm mở để hiện gợi ý
                    } else {
                        resultsContainer.innerHTML = `
                            <div class="text-center py-10 text-gray-400">
                                <i class="fa-solid fa-keyboard text-3xl mb-3 block opacity-20"></i>
                                <p class="text-sm italic">Nhập tên sản phẩm để tìm kiếm...</p>
                            </div>`;
                    }
                    return;
                }

                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    fetch(getApiUrl(`api/search.php?keyword=${encodeURIComponent(keyword)}`))
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.data.products.length > 0) {
                                renderCompareSearchResults(data.data.products, false);
                            } else {
                                resultsContainer.innerHTML = `
                                    <div class="text-center py-10 text-gray-400">
                                        <i class="fa-solid fa-face-frown text-3xl mb-3 block opacity-20"></i>
                                        <p class="text-sm italic">Không tìm thấy sản phẩm nào...</p>
                                    </div>`;
                            }
                        });
                }, 300);
            }

            /**
             * Helper function để render kết quả tìm kiếm trong modal so sánh
             */
            function renderCompareSearchResults(products, isSuggestion = false) {
                const resultsContainer = document.getElementById('compare-search-results');
                let html = '';
                
                if (isSuggestion) {
                    html += `<div class="px-2 mb-2"><span class="text-[11px] font-bold text-primary uppercase tracking-wider bg-blue-50 px-2 py-1 rounded">Gợi ý sản phẩm cùng loại</span></div>`;
                }

                products.forEach(p => {
                    const priceFmt = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
                    const oldPriceFmt = p.old_price ? new Intl.NumberFormat('vi-VN').format(p.old_price) + 'đ' : '';
                    
                    html += `
                        <div class="flex items-center gap-4 p-3 bg-white border border-gray-100 rounded-2xl hover:border-primary/30 hover:bg-primary/5 transition-all group">
                            <div class="w-16 h-16 bg-gray-50 rounded-xl flex items-center justify-center p-1 shrink-0 overflow-hidden">
                                <img src="${jsAsset(p.image)}" class="max-w-full max-h-full object-contain group-hover:scale-110 transition-transform duration-300">
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-bold text-gray-800 line-clamp-1 group-hover:text-primary transition-colors">${p.name}</h4>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="text-danger font-bold text-sm">${priceFmt}</span>
                                    ${oldPriceFmt ? `<span class="text-gray-400 text-xs line-through">${oldPriceFmt}</span>` : ''}
                                </div>
                            </div>
                            <button onclick="toggleCompare(${p.id}); closeCompareSearch();" 
                                    class="px-5 py-2 bg-red-50 text-red-600 rounded-xl text-sm font-bold hover:bg-red-600 hover:text-white transition-all shadow-sm">
                                Chọn
                            </button>
                        </div>
                    `;
                });
                resultsContainer.innerHTML = html;
            }

            window.toggleCompareBar = function() {
                const bar = document.getElementById('compare-sticky-bar');
                const icon = document.getElementById('compare-bar-toggle-icon');
                const text = document.getElementById('compare-bar-toggle-text');
                
                if (bar.classList.contains('is-collapsed')) {
                    // MỞ RỘNG (Về dạng thanh ngang full-width)
                    bar.classList.remove('is-collapsed');
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                    text.innerText = 'Thu gọn';
                } else {
                    // THU GỌN (Về dạng ô vuông floating)
                    bar.classList.add('is-collapsed');
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                    text.innerText = 'Mở rộng';
                }
            }

            // Tự động load danh sách khi trang vừa nạp (Dùng dữ liệu từ PHP để tránh mất khi reload)
            document.addEventListener('DOMContentLoaded', function() {
                if (initialCompareList && initialCompareList.length > 0) {
                    currentCompareCategoryId = initialCompareList[0].category_id;
                    renderCompareBar(initialCompareList);
                }
            });

            function formatTime(dateStr) {
                const date = new Date(dateStr);
                return date.toLocaleString('vi-VN', { 
                    day: '2-digit', month: '2-digit', year: 'numeric', 
                    hour: '2-digit', minute: '2-digit' 
                });
            }
        });
    </script>
</head>

<body class="antialiased pb-20 md:pb-0">

    <!-- ==========================================
         HEADER CHÍNH - Sticky ở đầu trang
         ========================================== -->
    <header class="bg-primary text-white sticky top-0 z-50">
        <!-- Container chính chứa Logo, Search, Actions -->
        <div class="container mx-auto px-4 h-[60px] flex items-center justify-between gap-4">

            <!-- LOGO -->
            <a href="index.php"
                class="text-2xl font-extrabold flex items-center gap-1.5 shrink-0 hover:opacity-90 transition">
                <i class="fa-solid fa-bolt-lightning text-secondary text-3xl"></i>
                <span class="tracking-tight">DIENMAY<span class="text-secondary">PRO</span></span>
            </a>

            <!-- THANH TÌM KIẾM (Desktop - ẩn trên mobile) -->
            <div class="hidden md:flex flex-1 max-w-[600px] h-10 relative">
                <form action="index.php" method="GET"
                    class="flex w-full h-full bg-white rounded-md shadow-sm items-center">
                    <!-- Dropdown danh mục trong search bar -->
                    <div class="relative h-full">
                        <button type="button" id="cat-dropdown-btn"
                            class="px-3 h-full bg-gray-50 border-r border-gray-200 cursor-pointer flex items-center gap-2 text-gray-700 text-[13px] font-medium hover:bg-gray-100 transition rounded-l-md">
                            <i class="fa-solid fa-bars"></i> <?= __('category') ?> <i
                                class="fa-solid fa-chevron-down text-[10px]"></i>
                        </button>
                        <!-- Menu dropdown danh mục (ẩn mặc định, toggle bằng JS) -->
                        <div id="cat-dropdown-menu"
                            class="hidden absolute left-0 top-full mt-1 w-[220px] bg-white border border-gray-200 rounded-xl shadow-2xl z-50 py-2">
                            <ul class="text-sm text-gray-700">
                                <li>
                                    <a href="index.php" class="flex items-center px-4 py-2.5 hover:bg-blue-50 hover:text-primary transition">
                                        <i class="fa-solid fa-border-all w-5 text-gray-400"></i> <?= __('all_products') ?>
                                    </a>
                                </li>
                                <div class="my-1 border-t border-gray-100"></div>
                                <?php foreach ($categories as $cat): ?>
                                    <li>
                                        <a href="index.php?cat_id=<?= $cat['id'] ?>" class="flex items-center px-4 py-2.5 hover:bg-blue-50 hover:text-primary transition">
                                            <i class="fa-solid <?= $cat['icon'] ?> w-5 text-primary/70"></i>
                                            <span class="ml-1"><?= htmlspecialchars(__cat($cat['name'])) ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <!-- Input tìm kiếm -->
                    <input type="text" name="search" id="search-input" value="<?= $search_query ?>"
                        placeholder="<?= __('search_placeholder') ?>" autocomplete="off"
                        class="flex-1 h-full px-3 text-gray-800 text-[13px] focus:outline-none bg-transparent">
                    <!-- Nút tìm kiếm -->
                    <button type="submit"
                        class="h-full px-5 bg-secondary text-primary hover:bg-yellow-400 transition rounded-r-md">
                        <i class="fa-solid fa-magnifying-glass font-bold"></i>
                    </button>
                </form>
                <!-- Search Suggestions -->
                <div id="search-suggestions" class="hidden absolute left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-xl z-[60] overflow-hidden text-gray-800">
                    <div id="suggestion-results" class="max-h-[450px] overflow-y-auto"></div>
                </div>
            </div>

            <!-- CÁC NÚT HÀNH ĐỘNG BÊN PHẢI -->
            <div class="flex items-center gap-4 text-[12px] font-medium h-full">
                <?php
                // Đếm tổng số đơn hàng của user đang đăng nhập (hiển thị badge)
                $order_count = 0;
                if (isset($_SESSION['user_id'])) {
                    $stmt_oc = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                    $stmt_oc->execute([$_SESSION['user_id']]);
                    $order_count = (int) $stmt_oc->fetchColumn();
                }
                ?>
                <!-- Nút Tra cứu đơn hàng (chỉ hiện desktop) -->
                <a href="track_order.php"
                    class="hidden lg:flex flex-col items-center justify-center h-full px-2 hover:bg-white/10 rounded transition gap-1 relative">
                    <i class="fa-solid fa-truck-fast text-xl"></i>
                    <span><?= __('track_order') ?></span>
                    <!-- Badge đếm số đơn (ẩn nếu = 0) -->
                    <span
                        class="absolute top-1 right-0 md:top-1 md:right-1 bg-secondary text-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none <?= $order_count > 0 ? '' : 'hidden' ?>">
                        <?= $order_count ?>
                    </span>
                </a>

                <!-- NÚT THÔNG BÁO -->
                <?php
                $unread_notifications = 0;
                if (isset($_SESSION['user_id'])) {
                    $stmt_un = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                    $stmt_un->execute([$_SESSION['user_id']]);
                    $unread_notifications = (int) $stmt_un->fetchColumn();
                }
                ?>
                <div class="relative group h-full">
                    <button id="noti-btn" class="flex flex-col items-center justify-center h-full px-2 hover:bg-white/10 rounded transition gap-1 relative">
                        <i class="fa-solid fa-bell text-xl"></i>
                        <span class="hidden md:block"><?= __('notifications') ?></span>
                        <span id="noti-count-badge" class="absolute top-1 right-0 md:top-1 md:right-1 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none <?= $unread_notifications > 0 ? '' : 'hidden' ?>">
                            <?= $unread_notifications ?>
                        </span>
                    </button>
                    <!-- Dropdown thông báo -->
                    <div id="noti-dropdown" class="absolute right-0 top-full mt-0 w-[320px] bg-white border border-gray-200 rounded shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50 overflow-hidden text-gray-800">
                        <div class="p-3 border-b flex justify-between items-center bg-gray-50">
                            <span class="font-bold text-sm">Thông báo</span>
                            <button onclick="markAllRead()" class="text-xs text-blue-600 hover:underline">Đánh dấu tất cả là đã đọc</button>
                        </div>
                        <div id="noti-list" class="max-h-[400px] overflow-y-auto">
                            <div class="p-8 text-center text-gray-400 text-xs">
                                <i class="fa-solid fa-spinner fa-spin mb-2 text-lg"></i>
                                <p>Đang tải thông báo...</p>
                            </div>
                        </div>
                        <div class="p-2 border-t text-center bg-gray-50">
                            <a href="profile.php?tab=notifications" class="text-xs text-blue-600 font-medium">Xem tất cả</a>
                        </div>
                    </div>
                </div>

                <!-- NÚT GIỎ HÀNG với badge đếm số lượng -->
                <?php
                // Đếm tổng SP trong giỏ hàng
                $cart_count = 0;
                if (isset($_SESSION['user_id']) && function_exists('getCartCount')) {
                    $cart_count = getCartCount($db, $_SESSION['user_id']);
                }
                ?>
                <a href="cart.php"
                    class="flex flex-col items-center justify-center h-full px-2 hover:bg-white/10 rounded transition gap-1 relative">
                    <i class="fa-solid fa-cart-shopping text-xl"></i>
                    <span class="hidden md:block"><?= __('cart') ?></span>
                    <!-- Badge đếm giỏ hàng (id để JS cập nhật khi addToCartAjax) -->
                    <span id="cart-count-badge"
                        class="absolute top-1 right-0 md:top-1 md:right-1 bg-secondary text-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none <?= $cart_count > 0 ? '' : 'hidden' ?>">
                        <?= $cart_count ?>
                    </span>
                </a>

                <!-- Đường phân cách dọc (desktop) -->
                <div class="w-px h-8 bg-white/20 hidden md:block mx-1"></div>

                <!-- MENU USER: Hiện thị khác nhau khi đã/chưa đăng nhập -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- ĐÃ ĐĂNG NHẬP: Hiện tên user + dropdown menu -->
                    <div
                        class="flex flex-col items-center justify-center h-full px-2 gap-1 relative group cursor-pointer hover:bg-white/10 rounded transition">
                        <a href="profile.php" class="flex flex-col items-center justify-center">
                            <i class="fa-solid fa-circle-user text-xl"></i>
                            <span
                                class="truncate max-w-[80px] text-secondary"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                        </a>
                        <!-- Dropdown menu (hiện khi hover) -->
                        <div
                            class="absolute right-0 top-full mt-0 w-[180px] bg-white border border-gray-200 rounded shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50 overflow-hidden">
                            <!-- Link Quản trị (chỉ hiện cho admin/manager) -->
                            <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                                <a href="admin.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i
                                        class="fa-solid fa-shield-halved text-danger mr-2"></i> <?= __('admin_panel') ?></a>
                            <?php endif; ?>
                            <a href="video.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i
                                    class="fa-solid fa-video text-primary mr-2"></i> Video</a>
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i
                                    class="fa-solid fa-user-gear text-primary mr-2"></i> <?= __('my_account') ?></a>
                            <!-- Nút Đăng xuất -->
                            <form method="POST" class="m-0">
                                <?= csrf_input_field() ?>
                                <input type="hidden" name="action" value="logout">
                                <button type="submit"
                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 border-t border-gray-100"><i
                                        class="fa-solid fa-right-from-bracket mr-2 text-gray-500"></i>
                                    <?= __('logout') ?></button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- CHƯA ĐĂNG NHẬP: Hiện nút mở modal đăng nhập -->
                    <button onclick="document.getElementById('loginModal').classList.remove('hidden')"
                        class="flex flex-col items-center justify-center h-[40px] px-3 bg-white/10 border border-white/20 rounded-lg hover:bg-white/20 transition gap-1">
                        <i class="fa-solid fa-circle-user text-sm"></i>
                        <span class="hidden md:block leading-none"><?= __('login') ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- THANH TÌM KIẾM MOBILE (ẩn trên desktop) -->
        <div class="md:hidden px-4 pb-3 relative">
            <form action="index.php" method="GET" class="h-10 bg-white rounded-md shadow-sm flex items-center w-full">
                <!-- Dropdown danh mục mobile -->
                <div class="relative h-full">
                    <button type="button" id="mobile-cat-dropdown-btn"
                        class="h-full px-4 bg-gray-50 border-r border-gray-200 flex items-center hover:bg-gray-100 active:bg-gray-200 transition rounded-l-md">
                        <i class="fa-solid fa-bars text-gray-700"></i>
                    </button>
                    <div id="mobile-cat-dropdown-menu"
                        class="hidden absolute left-0 top-full mt-1 w-[220px] bg-white border border-gray-200 rounded-xl shadow-2xl z-50 py-2">
                        <ul class="text-sm text-gray-700">
                            <li>
                                <a href="index.php" class="flex items-center px-4 py-2.5 hover:bg-blue-50 transition">
                                    <i class="fa-solid fa-border-all w-5 text-gray-400"></i> <?= __('all_products') ?>
                                </a>
                            </li>
                            <div class="my-1 border-t border-gray-100"></div>
                            <?php foreach ($categories as $cat): ?>
                                <li>
                                    <a href="index.php?cat_id=<?= $cat['id'] ?>" class="flex items-center px-4 py-2.5 hover:bg-blue-50 transition">
                                        <i class="fa-solid <?= $cat['icon'] ?> w-5 text-primary/70"></i>
                                        <span class="ml-1"><?= htmlspecialchars(__cat($cat['name'])) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <input type="text" name="search" id="search-input-mobile" value="<?= $search_query ?>" placeholder="<?= __('search_mobile') ?>" autocomplete="off"
                    class="flex-1 h-full px-3 text-gray-800 text-[13px] focus:outline-none bg-transparent">
                <button type="submit" class="h-full px-4 bg-secondary text-primary rounded-r-md">
                    <i class="fa-solid fa-magnifying-glass font-bold"></i>
                </button>
            </form>
            <!-- Search Suggestions Mobile -->
            <div id="search-suggestions-mobile" class="hidden absolute left-4 right-4 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-xl z-[60] overflow-hidden text-gray-800">
                <div id="suggestion-results-mobile" class="max-h-[350px] overflow-y-auto"></div>
            </div>
        </div>

        <!-- NAVIGATION BAR DANH MỤC (Desktop - ẩn trên mobile) -->
        <nav class="bg-navBg hidden md:block text-[13px] font-semibold border-t border-blue-800">
            <div class="container mx-auto flex justify-between">
                <ul class="flex">
                    <!-- Link Trang chủ -->
                    <li><a href="index.php"
                            class="h-10 px-4 flex items-center gap-1.5 hover:bg-white/10 transition <?= ($cat_id_filter == 0 && $current_page == 'index.php' && $search_query == '') ? 'bg-white/10' : '' ?>"><i
                                class="fa-solid fa-house"></i> <?= __('home') ?></a></li>
                    <!-- Các danh mục -->
                    <?php foreach ($categories as $cat): ?>
                        <li><a href="index.php?cat_id=<?= $cat['id'] ?>"
                                class="h-10 px-4 flex items-center gap-1.5 hover:bg-white/10 transition <?= $cat_id_filter == $cat['id'] ? 'bg-white/10' : '' ?>"><i
                                    class="fa-solid <?= $cat['icon'] ?>"></i>
                                <?= mb_strtoupper(__cat($cat['name']), 'UTF-8') ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <!-- Language Switcher (thay thế AI TƯ VẤN) -->
                <?php $currentLang = getCurrentLang(); ?>
                <div class="relative h-10 group" id="lang-switcher">
                    <button type="button"
                        class="h-10 px-4 flex items-center gap-2 text-secondary hover:bg-white/10 transition cursor-pointer">
                        <i class="fa-solid fa-globe"></i>
                        <span
                            class="text-[13px] font-semibold"><?= $currentLang === 'vi' ? '🇻🇳 VI' : '🇬🇧 EN' ?></span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-white/60"></i>
                    </button>
                    <div
                        class="absolute right-0 top-full mt-0 w-[160px] bg-white border border-gray-200 rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50 overflow-hidden">
                        <a href="?<?= http_build_query(array_merge($_GET, ['lang' => 'vi'])) ?>"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm transition <?= $currentLang === 'vi' ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-700 hover:bg-gray-50' ?>">
                            <span class="text-lg">🇻🇳</span> Tiếng Việt
                            <?php if ($currentLang === 'vi'): ?><i
                                    class="fa-solid fa-check text-blue-500 ml-auto text-xs"></i><?php endif; ?>
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['lang' => 'en'])) ?>"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm transition <?= $currentLang === 'en' ? 'bg-blue-50 text-blue-600 font-semibold' : 'text-gray-700 hover:bg-gray-50' ?>">
                            <span class="text-lg">🇬🇧</span> English
                            <?php if ($currentLang === 'en'): ?><i
                                    class="fa-solid fa-check text-blue-500 ml-auto text-xs"></i><?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- ==========================================
         JS: Toggle dropdown danh mục (Desktop + Mobile)
         ========================================== -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Toggle dropdown danh mục Desktop
            const btnDesktop = document.getElementById('cat-dropdown-btn');
            const menuDesktop = document.getElementById('cat-dropdown-menu');
            if (btnDesktop && menuDesktop) {
                btnDesktop.addEventListener('click', function (e) {
                    e.stopPropagation();
                    menuDesktop.classList.toggle('hidden');
                });
                // Ngăn menu đóng khi click vào bên trong menu
                menuDesktop.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            }

            // Toggle dropdown danh mục Mobile
            const btnMobile = document.getElementById('mobile-cat-dropdown-btn');
            const menuMobile = document.getElementById('mobile-cat-dropdown-menu');
            if (btnMobile && menuMobile) {
                btnMobile.addEventListener('click', function (e) {
                    e.stopPropagation();
                    menuMobile.classList.toggle('hidden');
                });
                // Ngăn menu đóng khi click vào bên trong menu
                menuMobile.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            }

            // Đóng dropdown khi click ra ngoài
            document.addEventListener('click', function (e) {
                if (menuDesktop && !menuDesktop.classList.contains('hidden')) {
                    menuDesktop.classList.add('hidden');
                }
                if (menuMobile && !menuMobile.classList.contains('hidden')) {
                    menuMobile.classList.add('hidden');
                }
            });
        });
    </script>

    <!-- ==========================================
         THANH DANH MỤC MOBILE (Scroll ngang với icons)
         ========================================== -->
    <div class="md:hidden bg-white border-b border-gray-200 overflow-x-auto hide-scrollbar shadow-sm">
        <div class="flex gap-4 px-4 py-3 min-w-max">
            <!-- Icon "Tất cả" -->
            <a href="index.php" class="flex flex-col items-center gap-1.5 w-16 group">
                <div
                    class="w-12 h-12 <?= $cat_id_filter == 0 && $current_page == 'index.php' && $search_query == '' ? 'bg-primary text-white' : 'bg-blue-50 text-primary' ?> rounded-2xl flex items-center justify-center text-lg shadow-sm">
                    <i class="fa-solid fa-house"></i></div>
                <span class="text-[10px] font-medium text-gray-700 text-center leading-tight"><?= __('all') ?></span>
            </a>
            <!-- Icons từng danh mục -->
            <?php foreach ($categories as $cat): ?>
                <a href="index.php?cat_id=<?= $cat['id'] ?>" class="flex flex-col items-center gap-1.5 w-16 group">
                    <div
                        class="w-12 h-12 <?= $cat_id_filter == $cat['id'] ? 'bg-primary text-white' : 'bg-blue-50 text-primary group-hover:bg-blue-100' ?> rounded-2xl flex items-center justify-center text-lg transition shadow-sm">
                        <i class="fa-solid <?= $cat['icon'] ?>"></i></div>
                    <span
                        class="text-[10px] font-medium text-gray-700 text-center leading-tight whitespace-normal"><?= htmlspecialchars(__cat($cat['name'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ==========================================
         MODAL ĐĂNG NHẬP / ĐĂNG KÝ
         ========================================== -->
    <!-- Modal tự động hiện khi có $auth_error (đăng nhập/đăng ký lỗi) -->
    <div id="loginModal"
        class="<?= ($auth_error || $auth_success || $show_forgot_tab) ? '' : 'hidden' ?> fixed inset-0 z-[100] flex items-start sm:items-center justify-center px-4 py-6 bg-slate-950/70 backdrop-blur-md overflow-y-auto">

        <div class="relative w-full max-w-4xl overflow-hidden rounded-[28px] bg-white shadow-2xl ring-1 ring-white/10">


            <button onclick="document.getElementById('loginModal').classList.add('hidden')"
                class="absolute left-4 top-4 z-20 inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>

            <div class="grid grid-cols-1 lg:grid-cols-[0.85fr_1.15fr] lg:h-[620px]">


                <!-- BẢNG QUẢNG CÁO: Ẩn trên mobile, chỉ hiện trên desktop (lg) -->
                <div class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-gradient-to-br from-[#0f5fe6] via-[#0b4fbf] to-[#0a2f7a] p-8 text-white md:p-10">

                    <div class="absolute -right-20 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
                    <div class="absolute -bottom-24 -left-20 h-72 w-72 rounded-full bg-secondary/15"></div>

                    <div class="relative z-10">
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.2em] backdrop-blur">
                            <i class="fa-solid fa-bolt-lightning text-secondary"></i> DienMayPro
                        </div>
                        <h2 class="mt-6 max-w-md text-4xl font-black leading-tight md:text-5xl">
                            Mua sắm điện máy
                            <span class="text-secondary">thông minh hơn</span>
                        </h2>
                        <p class="mt-4 max-w-md text-sm leading-7 text-blue-100 md:text-base">
                            Hàng nghìn sản phẩm chính hãng với giá tốt nhất. Giao hàng nhanh, bảo hành chính hãng và trải nghiệm mượt mà trên mọi thiết bị.
                        </p>
                    </div>

                    <div class="relative z-10 grid grid-cols-3 gap-3 text-center text-sm">
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                            <p class="text-2xl font-black text-secondary">10K+</p>
                            <p class="mt-1 text-xs font-semibold tracking-[0.15em] text-blue-100">SẢN PHẨM</p>
                        </div>
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                            <p class="text-2xl font-black text-secondary">98%</p>
                            <p class="mt-1 text-xs font-semibold tracking-[0.15em] text-blue-100">HÀI LÒNG</p>
                        </div>
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                            <p class="text-2xl font-black text-secondary">2H+</p>
                            <p class="mt-1 text-xs font-semibold tracking-[0.15em] text-blue-100">GIAO HÀNG</p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 p-4 sm:p-6 md:p-8 flex items-center overflow-hidden">

                    <div class="mx-auto w-full max-w-xl flex-col">
                        <!-- Tabs: Đăng nhập / Đăng ký -->
                        <div class="mb-3 grid grid-cols-2 rounded-2xl bg-white p-1 shadow-sm ring-1 ring-slate-200 transition-all duration-300">

                            <button id="tab-login" onclick="switchAuthTab('login')"
                                class="auth-tab-button rounded-xl py-3 text-center text-sm font-bold transition-all duration-300 ease-out <?= (!$show_register_tab && !$show_forgot_tab) ? 'bg-primary text-white shadow-md shadow-primary/20' : 'text-slate-500 hover:text-slate-700' ?>"><?= __('login') ?></button>
                            <button id="tab-register" onclick="switchAuthTab('register')"
                                class="auth-tab-button rounded-xl py-3 text-center text-sm font-bold transition-all duration-300 ease-out <?= $show_register_tab ? 'bg-primary text-white shadow-md shadow-primary/20' : 'text-slate-500 hover:text-slate-700' ?>"><?= __('register') ?></button>
                        </div>

                        <!-- Thông báo lỗi (nếu có) -->
                        <?php
                        if (isset($_GET['auth_error']) && !$auth_error) {
                            $auth_error = htmlspecialchars($_GET['auth_error']);
                            $show_register_tab = false;
                            $show_forgot_tab = false;
                        }
                        ?>
                        <?php if ($auth_error): ?>
                            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                <i class="fa-solid fa-circle-exclamation mr-1"></i><?= $auth_error ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($auth_success): ?>
                            <div class="mb-4 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                                <i class="fa-solid fa-circle-check mr-1"></i><?= $auth_success ?>
                            </div>
                        <?php endif; ?>

                        <div id="auth-panel-shell" class="w-full h-full lg:max-h-[500px] overflow-y-auto hide-scrollbar px-1">



                            <!-- FORM ĐĂNG NHẬP -->
                            <form id="form-login" method="POST" class="auth-panel <?= ($show_register_tab || $show_forgot_tab) ? 'hidden' : '' ?>">

                                <?= csrf_input_field() ?>
                                <input type="hidden" name="action" value="login">
                                <h3 class="text-2xl font-black text-slate-800">Chào mừng quay lại</h3>
                                <p class="mt-1 text-sm text-slate-500">Đăng nhập để tiếp tục mua sắm.</p>
                                <div class="mt-5 space-y-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700"><?= __('username') ?></label>
                                        <input type="text" name="username" required
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700"><?= __('password') ?></label>
                                        <input type="password" name="password" required minlength="8"
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10">
                                    </div>
                                </div>
                                <button type="submit"
                                    class="mt-5 w-full rounded-xl bg-primary py-3.5 font-bold text-white shadow-lg shadow-primary/20 transition hover:bg-blue-800"><?= __('login_btn') ?></button>
                                <a href="google_login.php"
                                    class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    <i class="fa-brands fa-google text-[#EA4335]"></i>
                                    Đăng nhập bằng Google
                                </a>
                                <a href="forgot_password.php"
                                    class="mt-3 inline-flex w-full items-center justify-center rounded-xl text-sm font-semibold text-primary transition hover:underline">Quên mật khẩu?</a>
                            </form>

                            <!-- FORM ĐĂNG KÝ -->
                            <form id="form-register" method="POST" class="auth-panel <?= !$show_register_tab ? 'hidden' : '' ?>">

                                <?= csrf_input_field() ?>
                                <input type="hidden" name="action" value="register">
                                <h3 class="text-2xl font-black text-slate-800">Tạo tài khoản mới</h3>
                                <p class="mt-1 text-sm text-slate-500">Chỉ mất vài giây để bắt đầu.</p>
                                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-sm font-medium text-slate-700"><?= __('fullname') ?></label>
                                        <input type="text" name="fullname" required
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700"><?= __('phone') ?> *</label>
                                        <input type="tel" name="phone" required pattern="[0-9]{10}"
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                                            placeholder="VD: 0901234567">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Email (không bắt buộc)</label>
                                        <input type="email" name="email"
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                                            placeholder="example@gmail.com">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700"><?= __('username') ?></label>
                                        <input type="text" name="username" required
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700"><?= __('password') ?></label>
                                        <input type="password" name="password" id="reg-password" required minlength="8"
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10">
                                        <div id="pw-hint" class="mt-2 space-y-1 text-xs">
                                            <p id="pw-len" class="flex items-center gap-1 text-slate-400"><i class="fa-solid fa-circle text-[6px]"></i> <?= __('pw_min_8') ?></p>
                                            <p id="pw-letter" class="flex items-center gap-1 text-slate-400"><i class="fa-solid fa-circle text-[6px]"></i> <?= __('pw_has_letter') ?></p>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700"><?= __('confirm_password') ?></label>
                                        <input type="password" name="confirm_password" required
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10">
                                    </div>
                                </div>
                                <button type="submit"
                                    class="mt-5 w-full rounded-xl bg-secondary py-3.5 font-bold text-primary shadow-lg shadow-secondary/20 transition hover:bg-yellow-400"><?= __('register_btn') ?></button>
                            </form>

                            <!-- FORM QUÊN MẬT KHẨU -->
                            <div id="form-forgot" class="auth-panel <?= !$show_forgot_tab ? 'hidden' : '' ?>">

                                <h3 class="text-2xl font-black text-slate-800">Quên mật khẩu</h3>
                                <p class="mt-1 text-sm text-slate-500">Xác minh email để đặt lại mật khẩu.</p>
                                <form method="POST" class="<?= $forgot_step === 1 ? 'mt-5' : 'hidden' ?>" id="forgot-step-1">
                                    <?= csrf_input_field() ?>
                                    <input type="hidden" name="action" value="forgot_password_send_otp">
                                    <div class="mb-4">
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Email tài khoản</label>
                                        <input type="email" name="email" required value="<?= htmlspecialchars($forgot_email) ?>"
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                                            placeholder="example@gmail.com">
                                    </div>
                                    <button type="submit"
                                        class="w-full rounded-xl bg-primary py-3.5 font-bold text-white shadow-lg shadow-primary/20 transition hover:bg-blue-800">Gửi mã OTP</button>
                                </form>

                                <form method="POST" class="<?= $forgot_step === 2 ? 'mt-5' : 'hidden' ?>" id="forgot-step-2">
                                    <?= csrf_input_field() ?>
                                    <input type="hidden" name="action" value="forgot_password_reset">
                                    <div class="mb-3">
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Email tài khoản</label>
                                        <input type="email" name="email" required value="<?= htmlspecialchars($forgot_email) ?>"
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10">
                                    </div>
                                    <div class="mb-3">
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Mã OTP</label>
                                        <input type="text" name="otp" required pattern="\d{6}" maxlength="6"
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                                            placeholder="Nhập mã 6 số">
                                    </div>
                                    <div class="mb-3">
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Mật khẩu mới</label>
                                        <input type="password" name="new_password" required minlength="8"
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10">
                                    </div>
                                    <div class="mb-5">
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Xác nhận mật khẩu mới</label>
                                        <input type="password" name="confirm_password" required minlength="8"
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10">
                                    </div>
                                    <button type="submit"
                                        class="w-full rounded-xl bg-secondary py-3.5 font-bold text-primary shadow-lg shadow-secondary/20 transition hover:bg-yellow-400">Đặt lại mật khẩu</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================
         JS: Chuyển tab + Validate mật khẩu real-time
         ========================================== -->
    <script>
        /**
         * Chuyển đổi giữa tab Đăng nhập và Đăng ký trong modal
         * @param {string} tab - 'login' hoặc 'register'
         */
        function switchAuthTab(tab) {
            const panels = {
                login: document.getElementById('form-login'),
                register: document.getElementById('form-register'),
                forgot: document.getElementById('form-forgot'),
            };
            const tabs = {
                login: document.getElementById('tab-login'),
                register: document.getElementById('tab-register'),
                forgot: document.getElementById('tab-forgot'),
            };

            Object.entries(panels).forEach(([name, panel]) => {
                if (!panel) return;
                if (name === tab) {
                    panel.classList.remove('hidden');
                } else {
                    panel.classList.add('hidden');
                }
            });

            Object.entries(tabs).forEach(([name, btn]) => {
                if (!btn) return;
                if (name === tab) {
                    btn.classList.add('bg-primary', 'text-white', 'shadow-md');
                    btn.classList.remove('text-slate-500', 'text-slate-700');
                } else {
                    btn.classList.remove('bg-primary', 'text-white', 'shadow-md');
                    btn.classList.add('text-slate-500');
                }
            });
        }





        /**
         * Validate mật khẩu real-time khi đăng ký
         * - Hiển thị icon check/x cho từng điều kiện
         * - Chặn submit nếu không đạt yêu cầu
         */
        document.addEventListener('DOMContentLoaded', function () {
            const regPw = document.getElementById('reg-password');
            const pwLen = document.getElementById('pw-len');
            const pwLetter = document.getElementById('pw-letter');
            const regForm = document.getElementById('form-register');

            // Lắng nghe sự kiện input để validate real-time
            if (regPw) {
                regPw.addEventListener('input', function () {
                    const val = this.value;
                    // Kiểm tra độ dài >= 8
                    if (val.length >= 8) {
                        pwLen.classList.remove('text-gray-400', 'text-red-500');
                        pwLen.classList.add('text-green-500');
                        pwLen.innerHTML = '<i class="fa-solid fa-circle-check text-[10px]"></i> Ít nhất 8 ký tự';
                    } else if (val.length > 0) {
                        pwLen.classList.remove('text-gray-400', 'text-green-500');
                        pwLen.classList.add('text-red-500');
                        pwLen.innerHTML = '<i class="fa-solid fa-circle-xmark text-[10px]"></i> Ít nhất 8 ký tự';
                    } else {
                        pwLen.classList.remove('text-green-500', 'text-red-500');
                        pwLen.classList.add('text-gray-400');
                        pwLen.innerHTML = '<i class="fa-solid fa-circle text-[6px]"></i> Ít nhất 8 ký tự';
                    }
                    // Kiểm tra có chữ cái
                    if (/[a-zA-Z]/.test(val)) {
                        pwLetter.classList.remove('text-gray-400', 'text-red-500');
                        pwLetter.classList.add('text-green-500');
                        pwLetter.innerHTML = '<i class="fa-solid fa-circle-check text-[10px]"></i> Ít nhất 1 chữ cái';
                    } else if (val.length > 0) {
                        pwLetter.classList.remove('text-gray-400', 'text-green-500');
                        pwLetter.classList.add('text-red-500');
                        pwLetter.innerHTML = '<i class="fa-solid fa-circle-xmark text-[10px]"></i> Ít nhất 1 chữ cái';
                    } else {
                        pwLetter.classList.remove('text-green-500', 'text-red-500');
                        pwLetter.classList.add('text-gray-400');
                        pwLetter.innerHTML = '<i class="fa-solid fa-circle text-[6px]"></i> Ít nhất 1 chữ cái';
                    }
                });
            }

            // Chặn submit form đăng ký nếu mật khẩu không hợp lệ
            if (regForm) {
                regForm.addEventListener('submit', function (e) {
                    const pw = regPw.value;
                    if (pw.length < 8 || !/[a-zA-Z]/.test(pw)) {
                        e.preventDefault();
                        // Hiện thông báo lỗi inline
                        const oldErr = document.getElementById('pw-inline-error');
                        if (oldErr) oldErr.remove();
                        const errDiv = document.createElement('div');
                        errDiv.id = 'pw-inline-error';
                        errDiv.className = 'bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm text-center border border-red-200 flex items-center justify-center gap-2 animate-fade-in';
                        errDiv.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Mật khẩu phải có ít nhất 8 ký tự và chứa ít nhất 1 chữ cái!';
                        const firstField = regForm.querySelector('.mb-3');
                        regForm.insertBefore(errDiv, firstField);
                        // Highlight input lỗi với animation shake
                        regPw.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                        regPw.style.animation = 'shake 0.4s ease';
                        regPw.focus();
                        // Xóa highlight khi user bắt đầu sửa
                        regPw.addEventListener('input', function handler() {
                            regPw.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
                            regPw.style.animation = '';
                            const err = document.getElementById('pw-inline-error');
                            if (err) err.remove();
                            regPw.removeEventListener('input', handler);
                        });
                    }
                });
            }

            // --- WISHLIST AJAX ---
            window.toggleWishlistAjax = function(productId, btn) {
                const formData = new FormData();
                formData.append('action', 'toggle');
                formData.append('product_id', productId);
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');

                fetch(getApiUrl('api/wishlist.php?action=toggle'), {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const icon = btn.querySelector('i');
                        if (data.data.in_wishlist) {
                            icon.classList.remove('fa-regular');
                            icon.classList.add('fa-solid');
                            btn.classList.add('active');
                        } else {
                            icon.classList.remove('fa-solid');
                            icon.classList.add('fa-regular');
                            btn.classList.remove('active');
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
                        if (data.message.includes('đăng nhập')) {
                            Swal.fire('Thông báo', '<?= __("wishlist_login_required") ?>', 'warning');
                        } else {
                            Swal.fire('Lỗi', data.message, 'error');
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Lỗi', 'Không thể kết nối đến máy chủ', 'error');
                });
            }

            // Đồng bộ trạng thái Heart icon khi load trang
            <?php if (isset($_SESSION['user_id'])): ?>
            function syncWishlistIcons() {
                fetch(getApiUrl('api/wishlist.php?action=list'))
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.data) {
                            data.data.forEach(item => {
                                const btn = document.getElementById(`btn-wishlist-${item.id}`);
                                if (btn) {
                                    const icon = btn.querySelector('i');
                                    icon.classList.remove('fa-regular');
                                    icon.classList.add('fa-solid');
                                    btn.classList.add('active');
                                }
                            });
                        }
                    });
            }
            syncWishlistIcons();
            <?php endif; ?>
        });
    </script>

    <!-- CSS: Animation shake + fadeIn -->
    <style>
        /* Animation lắc input khi có lỗi */
        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            20% {
                transform: translateX(-6px);
            }

            40% {
                transform: translateX(6px);
            }

            60% {
                transform: translateX(-4px);
            }

            80% {
                transform: translateX(4px);
            }
        }

        /* Animation fade in cho thông báo lỗi */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease;
        }
    </style>