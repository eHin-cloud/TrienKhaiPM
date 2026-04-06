<?php
$auth_error = '';
$show_register_tab = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $auth_error = 'Sai tài khoản hoặc mật khẩu!';
        }
    } elseif ($_POST['action'] === 'register') {
        $fullname = trim($_POST['fullname']);
        $phone = trim($_POST['phone']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $show_register_tab = true;

        if (strlen($password) < 8 || !preg_match('/[a-zA-Z]/', $password)) {
            $auth_error = 'Mật khẩu phải có ít nhất 8 ký tự và chứa ít nhất 1 chữ cái!';
        } elseif ($password !== $confirm_password) {
            $auth_error = 'Mật khẩu xác nhận không khớp!';
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR phone = ?");
            $stmt->execute([$username, $phone]);
            if ($stmt->fetch()) {
                $auth_error = 'Tên tài khoản hoặc Số điện thoại đã tồn tại!';
            } else {
                $stmt = $db->prepare("INSERT INTO users (phone, username, password, fullname, role) VALUES (?, ?, ?, ?, 'customer')");
                $stmt->execute([$phone, $username, $password, $fullname]);
                $_SESSION['user_id'] = $db->lastInsertId();
                $_SESSION['fullname'] = $fullname;
                $_SESSION['role'] = 'customer';
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
    } elseif ($_POST['action'] === 'logout') {
        session_destroy();
        header("Location: index.php");
        exit;
    }
}

// Lấy danh mục cho menu
$categories = getAllCategories($db);
$current_page = basename($_SERVER['PHP_SELF']);
$cat_id_filter = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$search_query = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Điện Máy PRO - Chuyên nghiệp & Tận tâm</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMjAgNTEyIj48cGF0aCBmaWxsPSIjZmZjZjAwIiBkPSJNMjQwLjUgMjI0SDM1MkMzNjUuMyAyMjQgMzc3LjMgMjMyLjMgMzgxLjEgMjQ0LjdDMzg2LjYgMjU3LjIgMzgzLjEgMjcxLjMgMzczLjEgMjgwLjFMMTE3LjEgNTA0LjFDMTA1LjggNTEzLjkgODkuMjcgNTE0LjcgNzcuMTkgNTA1LjlDNjUuMSA0OTcuMSA2MC43IDQ4MS4xIDY2LjM2 NDY3LjRMMTMxLjYgMzA0LjVINDhDMzQuNzMgMzA0LjUgMjIuNjcgMjk2LjIgMTguMTEgMjgzLjdDMTMuNTQgMjcxLjIgMTcuMSAyNTcuMSAyNy4xIDI0OC4xTDI4My4xIDI0LjFDMjk0LjIgMTQuMjggMzEwLjcgMTMuNTMgMzIyLjggMjIuMzRDMzM0LjkgMzEuMTUgMzM5LjMgNDcuMSAzMzMuNiA2MC44MUwyNDAuNSAyMjR6Ii8+PC9zdmc+">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: { colors: { primary: '#0046ab', secondary: '#ffcf00', danger: '#d70018', navBg: '#00388a' } }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f1f2f6; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .sale-badge { background: linear-gradient(to right, #d70018, #ff4d4f); }
    </style>
</head>
<body class="antialiased pb-20 md:pb-0">

    <header class="bg-primary text-white sticky top-0 z-50">
        <div class="container mx-auto px-4 h-[60px] flex items-center justify-between gap-4">
            <a href="index.php" class="text-2xl font-extrabold flex items-center gap-1.5 shrink-0 hover:opacity-90 transition">
                <i class="fa-solid fa-bolt-lightning text-secondary text-3xl"></i>
                <span class="tracking-tight">DIENMAY<span class="text-secondary">PRO</span></span>
            </a>

            <form action="index.php" method="GET" class="hidden md:flex flex-1 max-w-[600px] h-10 bg-white rounded-md shadow-sm items-center">
                <div class="relative h-full" id="cat-dropdown-btn">
                    <button type="button" class="px-3 h-full bg-gray-50 border-r border-gray-200 cursor-pointer flex items-center gap-2 text-gray-700 text-[13px] font-medium hover:bg-gray-100 transition rounded-l-md">
                        <i class="fa-solid fa-bars"></i> Danh mục <i class="fa-solid fa-chevron-down text-[10px]"></i>
                    </button>
                    <div id="cat-dropdown-menu" class="hidden absolute left-0 top-full mt-1 w-[200px] bg-white border border-gray-200 rounded shadow-lg z-50">
                        <ul class="py-2 text-sm text-gray-700">
                            <li><a href="index.php" class="block px-4 py-2 hover:bg-gray-100">Tất cả sản phẩm</a></li>
                            <?php foreach ($categories as $cat): ?>
                                <li><a href="index.php?cat_id=<?= $cat['id'] ?>" class="block px-4 py-2 hover:bg-gray-100"><i class="fa-solid <?= $cat['icon'] ?> w-5"></i> <?= htmlspecialchars($cat['name']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <input type="text" name="search" value="<?= $search_query ?>" placeholder="Hôm nay bạn cần tìm gì?" class="flex-1 h-full px-3 text-gray-800 text-[13px] focus:outline-none bg-transparent">
                <button type="submit" class="h-full px-5 bg-secondary text-primary hover:bg-yellow-400 transition rounded-r-md">
                    <i class="fa-solid fa-magnifying-glass font-bold"></i>
                </button>
            </form>

            <div class="flex items-center gap-4 text-[12px] font-medium h-full">
                <a href="#" class="hidden lg:flex flex-col items-center justify-center h-full px-2 hover:bg-white/10 rounded transition gap-1">
                    <i class="fa-solid fa-truck-fast text-xl"></i> <span>Tra cứu đơn</span>
                </a>
                
                <!-- NÚT GIỎ HÀNG TÍCH HỢP BỘ ĐẾM -->
                <?php
                $cart_count = 0;
                if (isset($_SESSION['user_id']) && function_exists('getCartCount')) {
                    $cart_count = getCartCount($db, $_SESSION['user_id']);
                }
                ?>
                <a href="cart.php" class="flex flex-col items-center justify-center h-full px-2 hover:bg-white/10 rounded transition gap-1 relative">
                    <i class="fa-solid fa-cart-shopping text-xl"></i>
                    <span class="hidden md:block">Giỏ hàng</span>
                    <span id="cart-count-badge" class="absolute top-1 right-0 md:top-1 md:right-1 bg-secondary text-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none <?= $cart_count > 0 ? '' : 'hidden' ?>">
                        <?= $cart_count ?>
                    </span>
                </a>

                <div class="w-px h-8 bg-white/20 hidden md:block mx-1"></div>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="flex flex-col items-center justify-center h-full px-2 gap-1 relative group cursor-pointer hover:bg-white/10 rounded transition">
                        <i class="fa-solid fa-circle-user text-xl"></i>
                        <span class="truncate max-w-[80px] text-secondary"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                        <div class="absolute right-0 top-full mt-0 w-[150px] bg-white border border-gray-200 rounded shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50 overflow-hidden">
                            <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                                <a href="admin.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-shield-halved text-danger mr-2"></i>Quản trị</a>
                            <?php endif; ?>
                            <form method="POST" class="m-0">
                                <input type="hidden" name="action" value="logout">
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-right-from-bracket mr-2"></i>Đăng xuất</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <button onclick="document.getElementById('loginModal').classList.remove('hidden')" class="flex flex-col items-center justify-center h-[40px] px-3 bg-white/10 border border-white/20 rounded-lg hover:bg-white/20 transition gap-1">
                        <i class="fa-solid fa-circle-user text-sm"></i>
                        <span class="hidden md:block leading-none">Đăng nhập</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <form action="index.php" method="GET" class="md:hidden px-4 pb-3">
            <div class="h-10 bg-white rounded-md shadow-sm flex items-center w-full relative">
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

        <nav class="bg-navBg hidden md:block text-[13px] font-semibold border-t border-blue-800">
            <div class="container mx-auto flex justify-between">
                <ul class="flex">
                    <li><a href="index.php" class="h-10 px-4 flex items-center gap-1.5 hover:bg-white/10 transition <?= ($cat_id_filter==0 && $current_page=='index.php' && $search_query=='') ? 'bg-white/10' : '' ?>"><i class="fa-solid fa-house"></i> TRANG CHỦ</a></li>
                    <?php foreach ($categories as $cat): ?>
                        <li><a href="index.php?cat_id=<?= $cat['id'] ?>" class="h-10 px-4 flex items-center gap-1.5 hover:bg-white/10 transition <?= $cat_id_filter==$cat['id'] ? 'bg-white/10' : '' ?>"><i class="fa-solid <?= $cat['icon'] ?>"></i> <?= mb_strtoupper($cat['name'], 'UTF-8') ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <button class="h-10 px-4 flex items-center gap-1.5 text-secondary hover:bg-white/10 transition"><i class="fa-solid fa-wand-magic-sparkles"></i> AI TƯ VẤN</button>
            </div>
        </nav>
    </header>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btnDesktop = document.getElementById('cat-dropdown-btn');
            const menuDesktop = document.getElementById('cat-dropdown-menu');
            if (btnDesktop && menuDesktop) {
                btnDesktop.addEventListener('click', function(e) {
                    menuDesktop.classList.toggle('hidden'); e.stopPropagation();
                });
            }

            const btnMobile = document.getElementById('mobile-cat-dropdown-btn');
            const menuMobile = document.getElementById('mobile-cat-dropdown-menu');
            if (btnMobile && menuMobile) {
                btnMobile.addEventListener('click', function(e) {
                    menuMobile.classList.toggle('hidden'); e.stopPropagation();
                });
            }

            document.addEventListener('click', function(e) {
                if (btnDesktop && menuDesktop && !btnDesktop.contains(e.target)) menuDesktop.classList.add('hidden');
                if (btnMobile && menuMobile && !btnMobile.contains(e.target)) menuMobile.classList.add('hidden');
            });
        });
    </script>

    <div class="md:hidden bg-white border-b border-gray-200 overflow-x-auto hide-scrollbar shadow-sm">
        <div class="flex gap-4 px-4 py-3 min-w-max">
            <a href="index.php" class="flex flex-col items-center gap-1.5 w-16 group">
                <div class="w-12 h-12 <?= $cat_id_filter == 0 && $current_page=='index.php' && $search_query=='' ? 'bg-primary text-white' : 'bg-blue-50 text-primary' ?> rounded-2xl flex items-center justify-center text-lg shadow-sm"><i class="fa-solid fa-house"></i></div>
                <span class="text-[10px] font-medium text-gray-700 text-center leading-tight">Tất cả</span>
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="index.php?cat_id=<?= $cat['id'] ?>" class="flex flex-col items-center gap-1.5 w-16 group">
                    <div class="w-12 h-12 <?= $cat_id_filter == $cat['id'] ? 'bg-primary text-white' : 'bg-blue-50 text-primary group-hover:bg-blue-100' ?> rounded-2xl flex items-center justify-center text-lg transition shadow-sm"><i class="fa-solid <?= $cat['icon'] ?>"></i></div>
                    <span class="text-[10px] font-medium text-gray-700 text-center leading-tight whitespace-normal"><?= htmlspecialchars($cat['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MODAL ĐĂNG NHẬP / ĐĂNG KÝ -->
    <div id="loginModal" class="<?= $auth_error ? '' : 'hidden' ?> fixed inset-0 bg-black/60 z-[100] flex items-center justify-center backdrop-blur-sm px-4">
        <div class="bg-white rounded-2xl w-full max-w-[400px] p-6 relative shadow-2xl">
            <button onclick="document.getElementById('loginModal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-800 text-xl"><i class="fa-solid fa-xmark"></i></button>

            <div class="flex mb-6 border-b border-gray-200">
                <button id="tab-login" onclick="switchAuthTab('login')" class="flex-1 pb-3 text-center font-bold text-lg <?= !$show_register_tab ? 'text-primary border-b-2 border-primary' : 'text-gray-400 border-b-2 border-transparent' ?> transition">Đăng nhập</button>
                <button id="tab-register" onclick="switchAuthTab('register')" class="flex-1 pb-3 text-center font-bold text-lg <?= $show_register_tab ? 'text-primary border-b-2 border-primary' : 'text-gray-400 border-b-2 border-transparent' ?> transition">Đăng ký</button>
            </div>

            <?php if ($auth_error): ?>
                <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm text-center border border-red-200">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i><?= $auth_error ?>
                </div>
            <?php endif; ?>

            <form id="form-login" method="POST" class="<?= $show_register_tab ? 'hidden' : '' ?>">
                <input type="hidden" name="action" value="login">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tên đăng nhập</label>
                    <input type="text" name="username" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none transition">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mật khẩu</label>
                    <input type="password" name="password" required minlength="8" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none transition">
                </div>
                <button type="submit" class="w-full bg-primary text-white font-bold py-3 rounded-lg hover:bg-blue-800 transition shadow-md">Đăng Nhập</button>
            </form>

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
    <script>
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

        // Real-time password validation for register form
        document.addEventListener('DOMContentLoaded', function() {
            const regPw = document.getElementById('reg-password');
            const pwLen = document.getElementById('pw-len');
            const pwLetter = document.getElementById('pw-letter');
            const regForm = document.getElementById('form-register');

            if (regPw) {
                regPw.addEventListener('input', function() {
                    const val = this.value;
                    // Check length >= 8
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
                    // Check at least 1 letter
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

            // Prevent form submit if password is invalid
            if (regForm) {
                regForm.addEventListener('submit', function(e) {
                    const pw = regPw.value;
                    if (pw.length < 8 || !/[a-zA-Z]/.test(pw)) {
                        e.preventDefault();

                        // Remove old inline error if exists
                        const oldErr = document.getElementById('pw-inline-error');
                        if (oldErr) oldErr.remove();

                        // Create inline error banner
                        const errDiv = document.createElement('div');
                        errDiv.id = 'pw-inline-error';
                        errDiv.className = 'bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm text-center border border-red-200 flex items-center justify-center gap-2 animate-fade-in';
                        errDiv.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Mật khẩu phải có ít nhất 8 ký tự và chứa ít nhất 1 chữ cái!';

                        // Insert error at top of form (after the hidden input)
                        const firstField = regForm.querySelector('.mb-3');
                        regForm.insertBefore(errDiv, firstField);

                        // Highlight password input with red border + shake
                        regPw.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                        regPw.style.animation = 'shake 0.4s ease';
                        regPw.focus();

                        // Remove red border when user starts typing again
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

    <style>
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-6px); }
            40% { transform: translateX(6px); }
            60% { transform: translateX(-4px); }
            80% { transform: translateX(4px); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.3s ease; }
    </style>