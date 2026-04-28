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
$auth_error = '';           // Thông báo lỗi xác thực (nếu có)
$show_register_tab = false; // Cờ đánh dấu hiện tab Đăng ký (khi đăng ký lỗi)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- ĐĂNG NHẬP ---
    if ($_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        // Truy vấn so khớp username + password trong DB
        // LƯU Ý: Mật khẩu lưu dạng plain text (nên dùng password_hash() ở production)
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Đăng nhập thành công -> Lưu thông tin vào session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            // Redirect về đúng trang hiện tại (bao gồm các tham số)
            $redirect = $_SERVER['REQUEST_URI'];
            header("Location: " . ($redirect ?: 'index.php'));
            exit;
        } else {
            $auth_error = 'Sai tài khoản hoặc mật khẩu!';
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
$cat_id_filter = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;       // ID danh mục đang lọc
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
    <title>Điện Máy PRO - Chuyên nghiệp & Tận tâm</title>
    <!-- Favicon hình sét vàng (inline SVG base64) -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMjAgNTEyIj48cGF0aCBmaWxsPSIjZmZjZjAwIiBkPSJNMjQwLjUgMjI0SDM1MkMzNjUuMyAyMjQgMzc3LjMgMjMyLjMgMzgxLjEgMjQ0LjdDMzg2LjYgMjU3LjIgMzgzLjEgMjcxLjMgMzczLjEgMjgwLjFMMTE3LjEgNTA0LjFDMTA1LjggNTEzLjkgODkuMjcgNTE0LjcgNzcuMTkgNTA1LjlDNjUuMSA0OTcuMSA2MC43IDQ4MS4xIDY2LjM2 NDY3LjRMMTMxLjYgMzA0LjVINDhDMzQuNzMgMzA0LjUgMjIuNjcgMjk2LjIgMTguMTEgMjgzLjdDMTMuNTQgMjcxLjIgMTcuMSAyNTcuMSAyNy4xIDI0OC4xTDI4My4xIDI0LjFDMjk0LjIgMTQuMjggMzEwLjcgMTMuNTMgMzIyLjggMjIuMzRDMzM0LjkgMzEuMTUgMzM5LjMgNDcuMSAzMzMuNiA2MC44MUwyNDAuNSAyMjR6Ii8+PC9zdmc+">
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
        body { font-family: 'Inter', sans-serif; background-color: #f1f2f6; }
        /* Ẩn scrollbar nhưng vẫn scroll được */
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        /* Badge sale gradient đỏ */
        .sale-badge { background: linear-gradient(to right, #d70018, #ff4d4f); }
    </style>
</head>
<body class="antialiased pb-20 md:pb-0">

    <!-- ==========================================
         HEADER CHÍNH - Sticky ở đầu trang
         ========================================== -->
    <header class="bg-primary text-white sticky top-0 z-50">
        <!-- Container chính chứa Logo, Search, Actions -->
        <div class="container mx-auto px-4 h-[60px] flex items-center justify-between gap-4">
            
            <!-- LOGO -->
            <a href="index.php" class="text-2xl font-extrabold flex items-center gap-1.5 shrink-0 hover:opacity-90 transition">
                <i class="fa-solid fa-bolt-lightning text-secondary text-3xl"></i>
                <span class="tracking-tight">DIENMAY<span class="text-secondary">PRO</span></span>
            </a>

            <!-- THANH TÌM KIẾM (Desktop - ẩn trên mobile) -->
            <form action="index.php" method="GET" class="hidden md:flex flex-1 max-w-[600px] h-10 bg-white rounded-md shadow-sm items-center">
                <!-- Dropdown danh mục trong search bar -->
                <div class="relative h-full" id="cat-dropdown-btn">
                    <button type="button" class="px-3 h-full bg-gray-50 border-r border-gray-200 cursor-pointer flex items-center gap-2 text-gray-700 text-[13px] font-medium hover:bg-gray-100 transition rounded-l-md">
                        <i class="fa-solid fa-bars"></i> Danh mục <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </button>
                    <!-- Menu dropdown danh mục (ẩn mặc định, toggle bằng JS) -->
                    <div id="cat-dropdown-menu" class="hidden absolute left-0 top-full mt-1 w-[200px] bg-white border border-gray-200 rounded shadow-lg z-50">
                        <ul class="py-2 text-sm text-gray-700">
                            <li><a href="index.php" class="block px-4 py-2 hover:bg-gray-100">Tất cả sản phẩm</a></li>
                            <?php foreach ($categories as $cat): ?>
                                <li><a href="index.php?cat_id=<?= $cat['id'] ?>" class="block px-4 py-2 hover:bg-gray-100"><i class="fa-solid <?= $cat['icon'] ?> w-5"></i> <?= htmlspecialchars($cat['name']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <!-- Input tìm kiếm -->
                <input type="text" name="search" value="<?= $search_query ?>" placeholder="Hôm nay bạn cần tìm gì?" class="flex-1 h-full px-3 text-gray-800 text-[13px] focus:outline-none bg-transparent">
                <!-- Nút tìm kiếm -->
                <button type="submit" class="h-full px-5 bg-secondary text-primary hover:bg-yellow-400 transition rounded-r-md">
                    <i class="fa-solid fa-magnifying-glass font-bold"></i>
                </button>
            </form>

            <!-- CÁC NÚT HÀNH ĐỘNG BÊN PHẢI -->
            <div class="flex items-center gap-4 text-[12px] font-medium h-full">
                <?php
                // Đếm tổng số đơn hàng của user đang đăng nhập (hiển thị badge)
                $order_count = 0;
                if (isset($_SESSION['user_id'])) {
                    $stmt_oc = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                    $stmt_oc->execute([$_SESSION['user_id']]);
                    $order_count = (int)$stmt_oc->fetchColumn();
                }
                ?>
                <!-- Nút Tra cứu đơn hàng (chỉ hiện desktop) -->
                <a href="track_order.php" class="hidden lg:flex flex-col items-center justify-center h-full px-2 hover:bg-white/10 rounded transition gap-1 relative">
                    <i class="fa-solid fa-truck-fast text-xl"></i> 
                    <span>Tra cứu đơn</span>
                    <!-- Badge đếm số đơn (ẩn nếu = 0) -->
                    <span class="absolute top-1 right-0 md:top-1 md:right-1 bg-secondary text-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none <?= $order_count > 0 ? '' : 'hidden' ?>">
                        <?= $order_count ?>
                    </span>
                </a>
                
                <!-- NÚT GIỎ HÀNG với badge đếm số lượng -->
                <?php
                // Đếm tổng SP trong giỏ hàng
                $cart_count = 0;
                if (isset($_SESSION['user_id']) && function_exists('getCartCount')) {
                    $cart_count = getCartCount($db, $_SESSION['user_id']);
                }
                ?>
                <a href="cart.php" class="flex flex-col items-center justify-center h-full px-2 hover:bg-white/10 rounded transition gap-1 relative">
                    <i class="fa-solid fa-cart-shopping text-xl"></i>
                    <span class="hidden md:block">Giỏ hàng</span>
                    <!-- Badge đếm giỏ hàng (id để JS cập nhật khi addToCartAjax) -->
                    <span id="cart-count-badge" class="absolute top-1 right-0 md:top-1 md:right-1 bg-secondary text-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none <?= $cart_count > 0 ? '' : 'hidden' ?>">
                        <?= $cart_count ?>
                    </span>
                </a>

                <!-- Đường phân cách dọc (desktop) -->
                <div class="w-px h-8 bg-white/20 hidden md:block mx-1"></div>

                <!-- MENU USER: Hiện thị khác nhau khi đã/chưa đăng nhập -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- ĐÃ ĐĂNG NHẬP: Hiện tên user + dropdown menu -->
                    <div class="flex flex-col items-center justify-center h-full px-2 gap-1 relative group cursor-pointer hover:bg-white/10 rounded transition">
                        <a href="profile.php" class="flex flex-col items-center justify-center">
                            <i class="fa-solid fa-circle-user text-xl"></i>
                            <span class="truncate max-w-[80px] text-secondary"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                        </a>
                        <!-- Dropdown menu (hiện khi hover) -->
                        <div class="absolute right-0 top-full mt-0 w-[180px] bg-white border border-gray-200 rounded shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50 overflow-hidden">
                            <!-- Link Quản trị (chỉ hiện cho admin/manager) -->
                            <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                                <a href="admin.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-shield-halved text-danger mr-2"></i> Quản trị</a>
                            <?php endif; ?>
                            <!-- Link Đơn mua của tôi -->
                            <a href="track_order.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-clipboard-list text-primary mr-2"></i> Đơn mua của tôi</a>
                            <!-- Link Tài khoản của tôi -->
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-user-gear text-primary mr-2"></i> Tài khoản của tôi</a>
                            <!-- Nút Đăng xuất -->
                            <form method="POST" class="m-0">
                                <input type="hidden" name="action" value="logout">
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 border-t border-gray-100"><i class="fa-solid fa-right-from-bracket mr-2 text-gray-500"></i> Đăng xuất</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- CHƯA ĐĂNG NHẬP: Hiện nút mở modal đăng nhập -->
                    <button onclick="document.getElementById('loginModal').classList.remove('hidden')" class="flex flex-col items-center justify-center h-[40px] px-3 bg-white/10 border border-white/20 rounded-lg hover:bg-white/20 transition gap-1">
                        <i class="fa-solid fa-circle-user text-sm"></i>
                        <span class="hidden md:block leading-none">Đăng nhập</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- THANH TÌM KIẾM MOBILE (ẩn trên desktop) -->
        <form action="index.php" method="GET" class="md:hidden px-4 pb-3">
            <div class="h-10 bg-white rounded-md shadow-sm flex items-center w-full relative">
                <!-- Dropdown danh mục mobile -->
                <div class="relative h-full" id="mobile-cat-dropdown-btn">
                    <button type="button" class="h-full px-3 bg-gray-50 border-r border-gray-200 flex items-center hover:bg-gray-100 transition rounded-l-md">
                        <i class="fa-solid fa-bars text-gray-700"></i>
                    </button>
                    <div id="mobile-cat-dropdown-menu" class="hidden absolute left-0 top-full mt-1 w-[200px] bg-white border border-gray-200 rounded shadow-lg z-50">
                        <ul class="py-2 text-sm text-gray-700">
                            <li><a href="index.php" class="block px-4 py-2 hover:bg-gray-100">Tất cả sản phẩm</a></li>
                            <?php foreach ($categories as $cat): ?>
                                <li><a href="index.php?cat_id=<?= $cat['id'] ?>" class="block px-4 py-2 hover:bg-gray-100"><i class="fa-solid <?= $cat['icon'] ?> w-5"></i> <?= htmlspecialchars($cat['name']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <input type="text" name="search" value="<?= $search_query ?>" placeholder="Bạn cần tìm gì?" class="flex-1 h-full px-3 text-gray-800 text-[13px] focus:outline-none bg-transparent">
                <button type="submit" class="h-full px-4 bg-secondary text-primary rounded-r-md">
                    <i class="fa-solid fa-magnifying-glass font-bold"></i>
                </button>
            </div>
        </form>

        <!-- NAVIGATION BAR DANH MỤC (Desktop - ẩn trên mobile) -->
        <nav class="bg-navBg hidden md:block text-[13px] font-semibold border-t border-blue-800">
            <div class="container mx-auto flex justify-between">
                <ul class="flex">
                    <!-- Link Trang chủ -->
                    <li><a href="index.php" class="h-10 px-4 flex items-center gap-1.5 hover:bg-white/10 transition <?= ($cat_id_filter==0 && $current_page=='index.php' && $search_query=='') ? 'bg-white/10' : '' ?>"><i class="fa-solid fa-house"></i> TRANG CHỦ</a></li>
                    <!-- Các danh mục -->
                    <?php foreach ($categories as $cat): ?>
                        <li><a href="index.php?cat_id=<?= $cat['id'] ?>" class="h-10 px-4 flex items-center gap-1.5 hover:bg-white/10 transition <?= $cat_id_filter==$cat['id'] ? 'bg-white/10' : '' ?>"><i class="fa-solid <?= $cat['icon'] ?>"></i> <?= mb_strtoupper($cat['name'], 'UTF-8') ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <!-- Nút AI Tư vấn -->
                <button class="h-10 px-4 flex items-center gap-1.5 text-secondary hover:bg-white/10 transition"><i class="fa-solid fa-wand-magic-sparkles"></i> AI TƯ VẤN</button>
            </div>
        </nav>
    </header>

    <!-- ==========================================
         JS: Toggle dropdown danh mục (Desktop + Mobile)
         ========================================== -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle dropdown danh mục Desktop
            const btnDesktop = document.getElementById('cat-dropdown-btn');
            const menuDesktop = document.getElementById('cat-dropdown-menu');
            if (btnDesktop && menuDesktop) {
                btnDesktop.addEventListener('click', function(e) {
                    menuDesktop.classList.toggle('hidden'); e.stopPropagation();
                });
            }

            // Toggle dropdown danh mục Mobile
            const btnMobile = document.getElementById('mobile-cat-dropdown-btn');
            const menuMobile = document.getElementById('mobile-cat-dropdown-menu');
            if (btnMobile && menuMobile) {
                btnMobile.addEventListener('click', function(e) {
                    menuMobile.classList.toggle('hidden'); e.stopPropagation();
                });
            }

            // Đóng dropdown khi click ra ngoài
            document.addEventListener('click', function(e) {
                if (btnDesktop && menuDesktop && !btnDesktop.contains(e.target)) menuDesktop.classList.add('hidden');
                if (btnMobile && menuMobile && !btnMobile.contains(e.target)) menuMobile.classList.add('hidden');
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
                <div class="w-12 h-12 <?= $cat_id_filter == 0 && $current_page=='index.php' && $search_query=='' ? 'bg-primary text-white' : 'bg-blue-50 text-primary' ?> rounded-2xl flex items-center justify-center text-lg shadow-sm"><i class="fa-solid fa-house"></i></div>
                <span class="text-[10px] font-medium text-gray-700 text-center leading-tight">Tất cả</span>
            </a>
            <!-- Icons từng danh mục -->
            <?php foreach ($categories as $cat): ?>
                <a href="index.php?cat_id=<?= $cat['id'] ?>" class="flex flex-col items-center gap-1.5 w-16 group">
                    <div class="w-12 h-12 <?= $cat_id_filter == $cat['id'] ? 'bg-primary text-white' : 'bg-blue-50 text-primary group-hover:bg-blue-100' ?> rounded-2xl flex items-center justify-center text-lg transition shadow-sm"><i class="fa-solid <?= $cat['icon'] ?>"></i></div>
                    <span class="text-[10px] font-medium text-gray-700 text-center leading-tight whitespace-normal"><?= htmlspecialchars($cat['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ==========================================
         MODAL ĐĂNG NHẬP / ĐĂNG KÝ
         ========================================== -->
    <!-- Modal tự động hiện khi có $auth_error (đăng nhập/đăng ký lỗi) -->
    <div id="loginModal" class="<?= $auth_error ? '' : 'hidden' ?> fixed inset-0 bg-black/60 z-[100] flex items-center justify-center backdrop-blur-sm px-4">
        <div class="bg-white rounded-2xl w-full max-w-[400px] p-6 relative shadow-2xl">
            <!-- Nút đóng modal -->
            <button onclick="document.getElementById('loginModal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-800 text-xl"><i class="fa-solid fa-xmark"></i></button>

            <!-- Tabs: Đăng nhập / Đăng ký -->
            <div class="flex mb-6 border-b border-gray-200">
                <button id="tab-login" onclick="switchAuthTab('login')" class="flex-1 pb-3 text-center font-bold text-lg <?= !$show_register_tab ? 'text-primary border-b-2 border-primary' : 'text-gray-400 border-b-2 border-transparent' ?> transition">Đăng nhập</button>
                <button id="tab-register" onclick="switchAuthTab('register')" class="flex-1 pb-3 text-center font-bold text-lg <?= $show_register_tab ? 'text-primary border-b-2 border-primary' : 'text-gray-400 border-b-2 border-transparent' ?> transition">Đăng ký</button>
            </div>

            <!-- Thông báo lỗi (nếu có) -->
            <?php if ($auth_error): ?>
                <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm text-center border border-red-200">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i><?= $auth_error ?>
                </div>
            <?php endif; ?>
            <!-- FORM ĐĂNG NHẬP -->
            <form id="form-login" method="POST" class="<?= $show_register_tab ? 'hidden' : '' ?>">
                <input type="hidden" name="action" value="login">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tên đăng nhập</label>
                    <input type="text" name="username" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none transition">
                </div>
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mật khẩu</label>
                    <input type="password" name="password" required minlength="8" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none transition">
                </div>
                <button type="submit" class="w-full bg-primary text-white font-bold py-3 rounded-lg hover:bg-blue-800 transition shadow-md">Đăng Nhập</button>
            </form>

            <!-- FORM ĐĂNG KÝ -->
            <form id="form-register" method="POST" class="<?= !$show_register_tab ? 'hidden' : '' ?>">
                <input type="hidden" name="action" value="register">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Họ và tên</label>
                    <input type="text" name="fullname" required class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none text-sm">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số điện thoại *</label>
                    <input type="tel" name="phone" required pattern="[0-9]{10}" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none text-sm" placeholder="VD: 0901234567">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tên đăng nhập</label>
                    <input type="text" name="username" required class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3 mb-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mật khẩu</label>
                        <input type="password" name="password" id="reg-password" required minlength="8" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none text-sm">
                        <!-- Gợi ý validate mật khẩu real-time -->
                        <div id="pw-hint" class="mt-1.5 text-xs space-y-0.5">
                            <p id="pw-len" class="flex items-center gap-1 text-gray-400"><i class="fa-solid fa-circle text-[6px]"></i> Ít nhất 8 ký tự</p>
                            <p id="pw-letter" class="flex items-center gap-1 text-gray-400"><i class="fa-solid fa-circle text-[6px]"></i> Ít nhất 1 chữ cái</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Xác nhận</label>
                        <input type="password" name="confirm_password" required class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none text-sm">
                    </div>
                </div>
                <button type="submit" class="w-full bg-secondary text-primary font-bold py-3 rounded-lg hover:bg-yellow-400 transition shadow-md">Đăng Ký Ngay</button>
            </form>
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
            const formLogin = document.getElementById('form-login'), formRegister = document.getElementById('form-register');
            const tabLogin = document.getElementById('tab-login'), tabRegister = document.getElementById('tab-register');
            if (tab === 'login') {
                formLogin.classList.remove('hidden'); formRegister.classList.add('hidden');
                tabLogin.classList.add('text-primary', 'border-primary'); tabLogin.classList.remove('text-gray-400', 'border-transparent');
                tabRegister.classList.remove('text-primary', 'border-primary'); tabRegister.classList.add('text-gray-400', 'border-transparent');
            } else {
                formRegister.classList.remove('hidden'); formLogin.classList.add('hidden');
                tabRegister.classList.add('text-primary', 'border-primary'); tabRegister.classList.remove('text-gray-400', 'border-transparent');
                tabLogin.classList.remove('text-primary', 'border-primary'); tabLogin.classList.add('text-gray-400', 'border-transparent');
            }
        }



        /**
         * Validate mật khẩu real-time khi đăng ký
         * - Hiển thị icon check/x cho từng điều kiện
         * - Chặn submit nếu không đạt yêu cầu
         */
        document.addEventListener('DOMContentLoaded', function() {
            const regPw = document.getElementById('reg-password');
            const pwLen = document.getElementById('pw-len');
            const pwLetter = document.getElementById('pw-letter');
            const regForm = document.getElementById('form-register');

            // Lắng nghe sự kiện input để validate real-time
            if (regPw) {
                regPw.addEventListener('input', function() {
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
                regForm.addEventListener('submit', function(e) {
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
        });
    </script>

    <!-- CSS: Animation shake + fadeIn -->
    <style>
        /* Animation lắc input khi có lỗi */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-6px); }
            40% { transform: translateX(6px); }
            60% { transform: translateX(-4px); }
            80% { transform: translateX(4px); }
        }
        /* Animation fade in cho thông báo lỗi */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.3s ease; }
    </style>