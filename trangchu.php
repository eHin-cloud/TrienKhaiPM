<?php
session_start();

// Nhúng file kết nối CSDL MySQL (Nhớ giữ file database.php cùng thư mục)
require_once 'database.php';

// ==========================================
// XỬ LÝ ĐĂNG NHẬP / ĐĂNG KÝ / ĐĂNG XUẤT
// ==========================================
$auth_error = '';
$show_register_tab = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // XỬ LÝ ĐĂNG NHẬP
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $auth_error = 'Sai tài khoản hoặc mật khẩu!';
        }
    } 
    // XỬ LÝ ĐĂNG KÝ (Có SĐT)
    elseif (isset($_POST['action']) && $_POST['action'] === 'register') {
        $fullname = trim($_POST['fullname']);
        $phone = trim($_POST['phone']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $show_register_tab = true;

        if ($password !== $confirm_password) {
            $auth_error = 'Mật khẩu xác nhận không khớp!';
        } else {
            // Kiểm tra username hoặc số điện thoại trùng
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR phone = ?");
            $stmt->execute([$username, $phone]);
            if ($stmt->fetch()) {
                $auth_error = 'Tên tài khoản hoặc Số điện thoại đã tồn tại!';
            } else {
                // Thêm user mới
                $stmt = $db->prepare("INSERT INTO users (phone, username, password, fullname, role) VALUES (?, ?, ?, ?, 'customer')");
                $stmt->execute([$phone, $username, $password, $fullname]);
                
                // Tự động đăng nhập
                $_SESSION['user_id'] = $db->lastInsertId();
                $_SESSION['fullname'] = $fullname;
                $_SESSION['role'] = 'customer';
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    } 
    // XỬ LÝ ĐĂNG XUẤT
    elseif (isset($_POST['action']) && $_POST['action'] === 'logout') {
        session_destroy();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Lấy danh sách danh mục để hiển thị Menu
$stmtCat = $db->query("SELECT * FROM categories ORDER BY id ASC");
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

// Xử lý lọc theo danh mục
$cat_id_filter = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$query_products = "SELECT * FROM products";
if ($cat_id_filter > 0) {
    $query_products .= " WHERE category_id = " . $cat_id_filter;
}
$query_products .= " ORDER BY id DESC";

$stmtProd = $db->query($query_products);
$products = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

// Lấy tên danh mục đang lọc
$current_category_name = "Tất Cả Sản Phẩm Nổi Bật";
if ($cat_id_filter > 0) {
    foreach ($categories as $c) {
        if ($c['id'] == $cat_id_filter) {
            $current_category_name = "Sản phẩm: " . htmlspecialchars($c['name']);
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Điện Máy PRO - Chuyên nghiệp & Tận tâm</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: '#0046ab', secondary: '#ffcf00', danger: '#d70018', hoverBg: '#f3f4f6' }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f1f2f6; }
        .product-card { transition: all 0.3s ease; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15); border-color: #0046ab; z-index: 10; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .sale-badge { background: linear-gradient(to right, #d70018, #ff4d4f); }
    </style>
</head>
<body class="antialiased pb-20 md:pb-0">

    <header class="bg-primary text-white sticky top-0 z-50 shadow-lg">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <a href="index.php" class="text-2xl md:text-3xl font-extrabold flex items-center gap-2 tracking-tight hover:scale-105 transition">
                <i class="fa-solid fa-bolt-lightning text-secondary"></i>
                <span>DIENMAY<span class="text-secondary">PRO</span></span>
            </a>

            <div class="hidden md:flex flex-1 max-w-2xl mx-10 relative bg-white rounded-lg shadow-inner border border-gray-200 h-11">
                <div class="relative group h-full">
                    <button class="h-full px-4 bg-gray-50 hover:bg-gray-100 text-gray-700 text-sm font-medium rounded-l-lg border-r border-gray-300 flex items-center gap-2 transition cursor-pointer">
                        <i class="fa-solid fa-bars"></i> Danh mục <i class="fa-solid fa-chevron-down text-[10px] ml-1"></i>
                    </button>
                    <div class="absolute left-0 top-full mt-1 w-56 bg-white border border-gray-100 rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <ul class="py-2 text-sm text-gray-700 font-medium">
                            <li><a href="index.php" class="block px-4 py-2 hover:bg-blue-50 hover:text-primary transition"><i class="fa-solid fa-border-all w-5 text-center"></i> Tất cả sản phẩm</a></li>
                            <li class="border-t border-gray-100 my-1"></li>
                            <?php foreach($categories as $cat): ?>
                                <li><a href="index.php?cat_id=<?= $cat['id'] ?>" class="block px-4 py-2 hover:bg-blue-50 hover:text-primary transition"><i class="fa-solid <?= $cat['icon'] ?> w-5 text-center"></i> <?= htmlspecialchars($cat['name']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <input type="text" id="main-search" placeholder="Hôm nay bạn cần tìm gì?" class="flex-1 py-2 px-4 text-gray-800 focus:outline-none bg-transparent h-full">
                <button onclick="searchAssistant('main-search')" class="px-6 h-full bg-secondary text-primary font-bold rounded-r-lg hover:bg-yellow-400 transition flex items-center gap-2">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>

            <div class="flex items-center gap-4 md:gap-6 text-sm font-medium">
                <a href="#" class="hidden lg:flex flex-col items-center hover:text-secondary transition">
                    <i class="fa-solid fa-truck-fast text-xl mb-1"></i> Tra cứu đơn
                </a>
                <a href="#" class="flex flex-col items-center relative hover:text-secondary transition">
                    <i class="fa-solid fa-cart-shopping text-xl md:mb-1"></i> 
                    <span class="hidden md:inline">Giỏ hàng</span>
                    <span class="absolute -top-2 -right-2 md:top-0 md:right-0 bg-secondary text-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full">3</span>
                </a>

                <div class="border-l border-blue-400 h-8 mx-2 hidden md:block"></div>

                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="flex items-center gap-3">
                        <div class="hidden md:flex flex-col text-right">
                            <span class="text-xs text-blue-200">Xin chào,</span>
                            <span class="font-bold text-secondary"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                        </div>
                        <?php if($_SESSION['role'] === 'admin'): ?>
                            <a href="#" class="bg-danger hover:bg-red-700 text-white px-2 py-1.5 md:px-3 rounded flex items-center gap-1 transition shadow-lg text-xs md:text-sm">
                                <i class="fa-solid fa-shield-halved"></i> <span class="hidden md:inline">Quản trị</span>
                            </a>
                        <?php endif; ?>
                        <form method="POST" class="m-0">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="text-gray-300 hover:text-white" title="Đăng xuất"><i class="fa-solid fa-right-from-bracket text-lg md:text-xl"></i></button>
                        </form>
                    </div>
                <?php else: ?>
                    <button onclick="document.getElementById('loginModal').classList.remove('hidden')" class="flex flex-col items-center hover:text-secondary transition bg-white/10 px-3 py-1.5 rounded-lg border border-white/20 hover:bg-white/20">
                        <i class="fa-solid fa-circle-user text-xl md:mb-1"></i>
                        <span class="hidden md:inline">Đăng nhập</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div id="mobile-search-wrapper" class="md:hidden px-4 pb-3 w-full relative">
            <div class="relative bg-white rounded-lg shadow-inner h-10 flex border border-gray-200 overflow-hidden">
                <button onclick="toggleMobileCategory(event)" class="px-3 bg-gray-50 text-gray-700 text-sm font-medium border-r border-gray-300 flex items-center transition">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <input type="text" id="mobile-search" placeholder="Bạn cần tìm gì?" class="flex-1 py-2 px-3 text-sm text-gray-800 focus:outline-none bg-transparent h-full">
                <button onclick="searchAssistant('mobile-search')" class="px-4 h-full bg-secondary text-primary font-bold hover:bg-yellow-400 transition">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>
            <div id="mobile-category-dropdown" class="hidden absolute left-4 top-[46px] w-[200px] bg-white border border-gray-100 rounded-lg shadow-2xl z-50">
                <ul class="py-2 text-sm text-gray-700 font-medium">
                    <li><a href="index.php" class="block px-4 py-2 hover:bg-blue-50"><i class="fa-solid fa-border-all w-5 text-center"></i> Tất cả SP</a></li>
                    <li class="border-t border-gray-100 my-1"></li>
                    <?php foreach($categories as $cat): ?>
                        <li><a href="index.php?cat_id=<?= $cat['id'] ?>" class="block px-4 py-2 hover:bg-blue-50"><i class="fa-solid <?= $cat['icon'] ?> w-5 text-center"></i> <?= htmlspecialchars($cat['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <nav class="bg-[#00388a] hidden md:block border-t border-blue-700">
            <div class="container mx-auto px-4 flex justify-between">
                <ul class="flex font-medium text-sm">
                    <li><a href="index.php" class="hover:bg-blue-800 px-4 py-3 flex items-center gap-2 transition <?= $cat_id_filter==0?'bg-blue-800':'' ?>"><i class="fa-solid fa-house"></i> TRANG CHỦ</a></li>
                    <?php foreach($categories as $cat): ?>
                        <li><a href="index.php?cat_id=<?= $cat['id'] ?>" class="hover:bg-blue-800 px-4 py-3 flex items-center gap-2 transition <?= $cat_id_filter==$cat['id']?'bg-blue-800':'' ?>"><i class="fa-solid <?= $cat['icon'] ?>"></i> <?= mb_strtoupper($cat['name'], 'UTF-8') ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <button class="hover:text-secondary text-secondary flex items-center gap-2 px-4 py-3 font-bold bg-white/10 rounded-t-lg ml-4"><i class="fa-solid fa-wand-magic-sparkles"></i> AI TƯ VẤN</button>
            </div>
        </nav>
    </header>

    <div class="md:hidden bg-white border-b border-gray-200 overflow-x-auto hide-scrollbar shadow-sm">
        <div class="flex gap-4 px-4 py-3 min-w-max">
            <a href="index.php" class="flex flex-col items-center gap-1.5 w-16 group">
                <div class="w-12 h-12 <?= $cat_id_filter==0?'bg-primary text-white':'bg-blue-50 text-primary' ?> rounded-2xl flex items-center justify-center text-lg shadow-sm"><i class="fa-solid fa-house"></i></div>
                <span class="text-[10px] font-medium text-gray-700 text-center leading-tight">Tất cả</span>
            </a>
            <?php foreach($categories as $cat): ?>
            <a href="index.php?cat_id=<?= $cat['id'] ?>" class="flex flex-col items-center gap-1.5 w-16 group">
                <div class="w-12 h-12 <?= $cat_id_filter==$cat['id']?'bg-primary text-white':'bg-blue-50 text-primary group-hover:bg-blue-100' ?> rounded-2xl flex items-center justify-center text-lg transition shadow-sm"><i class="fa-solid <?= $cat['icon'] ?>"></i></div>
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
            
            <?php if($auth_error): ?>
                <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm text-center border border-red-200">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i> <?= $auth_error ?>
                </div>
            <?php endif; ?>

            <form id="form-login" method="POST" class="<?= $show_register_tab ? 'hidden' : '' ?>">
                <input type="hidden" name="action" value="login">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tên đăng nhập</label>
                    <input type="text" name="username" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white outline-none transition">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mật khẩu</label>
                    <input type="password" name="password" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white outline-none transition">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số điện thoại <span class="text-red-500">*</span></label>
                    <input type="tel" name="phone" required pattern="[0-9]{10}" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none text-sm" placeholder="VD: 0901234567">
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tên đăng nhập</label>
                    <input type="text" name="username" required class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3 mb-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mật khẩu</label>
                        <input type="password" name="password" required class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none text-sm">
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

    <!-- BANNERS -->
    <?php if($cat_id_filter == 0): ?>
    <section class="container mx-auto px-4 mt-4 md:mt-6">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            <div class="lg:col-span-3 relative rounded-2xl overflow-hidden h-[200px] md:h-[350px] shadow-sm group">
                <img src="https://images.unsplash.com/photo-1584622650111-993a426fbf0a?auto=format&fit=crop&q=80&w=1200" alt="Banner" class="w-full h-full object-cover group-hover:scale-105 transition duration-700">
                <div class="absolute inset-0 bg-gradient-to-r from-[#00388a]/90 to-transparent flex flex-col justify-center px-6 md:px-12 text-white">
                    <span class="bg-danger text-white text-[10px] md:text-xs font-bold px-3 py-1 rounded-full w-fit mb-2 md:mb-4 inline-block animate-pulse">SIÊU SALE</span>
                    <h1 class="text-2xl md:text-5xl font-extrabold mb-2 md:mb-4 leading-tight">Mùa Hè Sôi Động <br><span class="text-secondary">Giảm Khủng 50%</span></h1>
                    <p class="hidden md:block mb-8 text-blue-100 max-w-md">Mua sắm thông minh, giao hàng lắp đặt tận nhà 0Đ. Trợ lý AI hỗ trợ 24/7.</p>
                </div>
            </div>
            <div class="hidden lg:grid grid-rows-2 gap-4">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 shadow-sm border border-blue-200 flex flex-col justify-center items-center text-center relative overflow-hidden group">
                    <i class="fa-solid fa-headset text-4xl text-primary mb-2"></i>
                    <h3 class="font-bold text-primary text-lg">Tư vấn chọn mua</h3>
                    <p class="text-sm text-gray-600 mt-1">Trợ lý AI giúp bạn chọn đúng sản phẩm phù hợp nhu cầu.</p>
                    <button class="mt-3 bg-white text-primary text-sm font-semibold py-1.5 px-4 rounded-full border border-primary hover:bg-primary hover:text-white transition">Hỏi AI ngay</button>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- DANH SÁCH SẢN PHẨM -->
    <section class="container mx-auto px-4 mt-8">
        <div class="flex justify-between items-center mb-4 md:mb-6 bg-white p-3 md:p-4 rounded-xl shadow-sm border border-gray-100">
            <h2 class="text-lg md:text-2xl font-bold uppercase text-gray-800 flex items-center gap-2">
                <i class="fa-solid <?= $cat_id_filter == 0 ? 'fa-fire text-danger' : 'fa-list text-primary' ?>"></i> 
                <?= $current_category_name ?>
            </h2>
            <?php if($cat_id_filter > 0): ?>
                <a href="index.php" class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-lg text-gray-700 transition"><i class="fa-solid fa-xmark"></i> Xóa lọc</a>
            <?php endif; ?>
        </div>
        
        <?php if(empty($products)): ?>
            <div class="text-center py-12 bg-white rounded-xl shadow-sm">
                <img src="https://cdn-icons-png.flaticon.com/512/7486/7486747.png" class="w-32 mx-auto mb-4 opacity-50" alt="Empty">
                <p class="text-gray-500 font-medium text-lg">Không tìm thấy sản phẩm nào trong danh mục này.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 md:gap-4">
                <?php foreach($products as $p): 
                    $discount = $p['old_price'] ? round((($p['old_price'] - $p['price']) / $p['old_price']) * 100) : 0;
                    $tags = array_filter(explode(',', $p['tags']));
                    $productJson = htmlspecialchars(json_encode([
                        'id' => $p['id'],
                        'name' => $p['name'],
                        'price' => $p['price'],
                        'image' => $p['image']
                    ]));
                ?>
                <div class="product-card bg-white p-3 md:p-4 rounded-xl border border-gray-100 flex flex-col relative group cursor-pointer" onclick="viewProduct(<?= $productJson ?>)">
                    <div class="absolute top-2 left-2 flex flex-col gap-1 z-10">
                        <?php if($discount > 0): ?>
                            <span class="sale-badge text-white text-[10px] md:text-xs font-bold px-2 py-0.5 rounded shadow-sm">-<?= $discount ?>%</span>
                        <?php endif; ?>
                        <?php if(strpos($p['gift_text'], 'Trả góp') !== false): ?>
                            <span class="bg-[#f1f2f6] text-primary border border-primary/20 text-[9px] font-bold px-1.5 py-0.5 rounded">Trả góp 0%</span>
                        <?php endif; ?>
                    </div>

                    <div class="h-36 md:h-48 bg-white mb-3 overflow-hidden flex items-center justify-center p-2">
                        <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="max-w-full max-h-full object-contain group-hover:scale-110 transition duration-500">
                    </div>

                    <h3 class="font-medium text-xs md:text-sm text-gray-800 line-clamp-2 mb-2 group-hover:text-primary transition h-8 md:h-10 leading-snug">
                        <?= htmlspecialchars($p['name']) ?>
                    </h3>
                    
                    <?php if(!empty($tags)): ?>
                    <div class="flex items-center gap-1 mb-2 md:mb-3 flex-wrap">
                        <?php foreach($tags as $tag): ?>
                            <span class="text-[9px] md:text-[10px] bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded text-gray-600"><?= trim(htmlspecialchars($tag)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="mt-auto">
                        <div class="text-danger font-bold text-base md:text-lg"><?= number_format($p['price'], 0, ',', '.') ?>đ</div>
                        <?php if($p['old_price']): ?>
                            <div class="text-gray-400 text-[10px] md:text-xs line-through"><?= number_format($p['old_price'], 0, ',', '.') ?>đ</div>
                        <?php else: ?>
                            <div class="h-3 md:h-4"></div>
                        <?php endif; ?>
                        
                        <?php if($p['gift_text']): ?>
                            <div class="mt-2 text-[10px] md:text-xs bg-red-50 text-danger px-2 py-1 rounded border border-red-100 flex items-start gap-1">
                                <i class="fa-solid fa-gift mt-0.5"></i> <span><?= htmlspecialchars($p['gift_text']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-3 grid grid-cols-5 gap-2 opacity-100 lg:opacity-0 group-hover:opacity-100 transition duration-300 relative z-20">
                        <button class="col-span-4 bg-primary text-white py-2 rounded text-[11px] md:text-sm font-bold hover:bg-blue-800 transition" onclick="event.stopPropagation(); alert('Đã thêm vào giỏ hàng!');">Mua ngay</button>
                        <button onclick="event.stopPropagation(); askAIAboutProduct('<?= htmlspecialchars($p['name']) ?>')" class="col-span-1 border border-primary text-primary rounded flex items-center justify-center hover:bg-blue-50 transition" title="Hỏi AI tư vấn">
                            <i class="fa-solid fa-wand-magic-sparkles text-xs md:text-base"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- DANH SÁCH SẢN PHẨM ĐÃ XEM -->
    <section id="recently-viewed-section" class="container mx-auto px-4 mt-10 mb-10 hidden">
        <div class="bg-white p-4 md:p-5 rounded-2xl shadow-sm border border-gray-200">
            <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-primary"></i> Sản phẩm bạn vừa xem
                </h2>
                <button onclick="clearAllViewed()" class="text-xs text-red-500 hover:text-red-700 font-medium bg-red-50 px-3 py-1.5 rounded-lg transition">Xóa tất cả</button>
            </div>
            <!-- Nơi render thẻ sản phẩm từ Javascript -->
            <div id="viewed-products-container" class="flex gap-4 overflow-x-auto pb-2 hide-scrollbar">
            </div>
        </div>
    </section>

    <!-- AI CHAT WINDOW -->
    <style>
        #ai-chat-window { display: none; position: fixed; bottom: 80px; right: 10px; width: calc(100% - 20px); max-width: 360px; height: 480px; max-height: 80vh; background: white; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); z-index: 1001; flex-direction: column; overflow: hidden; border: 1px solid #e5e7eb; }
        @media (min-width: 768px) { #ai-chat-window { bottom: 90px; right: 20px; height: 520px; } }
        #ai-chat-window.active { display: flex; animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .chat-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; background: #f9fafb; }
        .message { max-width: 85%; padding: 10px 14px; font-size: 13px; line-height: 1.5; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .message.user { align-self: flex-end; background: #0046ab; color: white; border-radius: 18px 18px 4px 18px; }
        .message.ai { align-self: flex-start; background: white; color: #1f2937; border-radius: 18px 18px 18px 4px; border: 1px solid #e5e7eb; }
        .loading-dots span { display: inline-block; width: 6px; height: 6px; background: #999; border-radius: 50%; margin: 0 2px; animation: bounce 1.4s infinite ease-in-out both; }
        .loading-dots span:nth-child(1) { animation-delay: -0.32s; }
        .loading-dots span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1.0); } }
    </style>

    <div id="ai-chat-window">
        <div class="bg-gradient-to-r from-primary to-blue-600 text-white p-3 md:p-4 flex justify-between items-center shadow-md z-10">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 md:w-10 md:h-10 bg-white rounded-full flex items-center justify-center text-primary md:text-xl shadow-inner"><i class="fa-solid fa-robot"></i></div>
                <div>
                    <div class="font-bold text-sm md:text-base">Trợ lý AI PRO</div>
                    <div class="text-[9px] md:text-[10px] text-blue-200 flex items-center gap-1"><span class="w-1.5 h-1.5 bg-green-400 rounded-full inline-block"></span> Đang trực tuyến</div>
                </div>
            </div>
            <button onclick="toggleAIChat()" class="hover:bg-white/20 w-8 h-8 rounded-full transition flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="chat-messages" id="chat-messages">
            <div class="message ai">Xin chào! Tôi là AI của DIENMAYPRO. Bạn đang phân vân chọn mua sản phẩm nào, hãy hỏi tôi nhé!</div>
        </div>
        <div class="p-3 bg-white border-t border-gray-100 flex gap-2 items-center">
            <input type="text" id="ai-input" placeholder="Nhập câu hỏi..." class="flex-1 text-sm bg-gray-100 border-transparent rounded-full px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary focus:bg-white transition">
            <button onclick="sendMessage()" class="bg-primary text-white w-10 h-10 rounded-full flex items-center justify-center hover:bg-blue-800 transition shadow-md"><i class="fa-solid fa-paper-plane text-sm"></i></button>
        </div>
    </div>

    <div onclick="toggleAIChat()" class="fixed bottom-4 right-4 md:bottom-6 md:right-6 z-50 group">
        <div class="bg-gradient-to-r from-secondary to-yellow-500 text-primary p-3 md:p-4 rounded-full shadow-2xl flex items-center justify-center cursor-pointer relative hover:scale-110 transition duration-300 w-12 h-12 md:w-16 md:h-16">
            <i class="fa-solid fa-robot text-xl md:text-3xl"></i>
            <span class="absolute 0 top-0 right-0 flex h-3 w-3 md:h-4 md:w-4"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 md:h-4 md:w-4 bg-red-500 border-2 border-white"></span></span>
        </div>
    </div>

    <footer class="bg-white py-6 md:py-8 border-t border-gray-200 mt-auto text-center">
        <div class="container mx-auto px-4">
            <div class="font-bold text-xl text-primary mb-2">DIENMAY<span class="text-secondary">PRO</span></div>
            <p class="text-gray-500 text-xs md:text-sm font-medium">© 2026 DIENMAYPRO. Hệ thống siêu thị điện máy hàng đầu.</p>
        </div>
    </footer>

    <!-- SCRIPTS -->
    <script>
        // --- 1. SẢN PHẨM ĐÃ XEM (SỬA LỖI XÓA) ---
        const VIEWED_KEY = 'dienmay_viewed_products';

        function viewProduct(product) {
            let viewed = JSON.parse(localStorage.getItem(VIEWED_KEY)) || [];
            
            // Ép kiểu ID về String để so sánh an toàn
            viewed = viewed.filter(p => String(p.id) !== String(product.id));
            viewed.unshift(product);
            
            if (viewed.length > 10) viewed.pop();
            
            localStorage.setItem(VIEWED_KEY, JSON.stringify(viewed));
            renderViewedProducts();
            
            // Code mở trang chi tiết (nếu có): window.location = 'detail.php?id=' + product.id;
        }

        function renderViewedProducts() {
            let viewed = JSON.parse(localStorage.getItem(VIEWED_KEY)) || [];
            const container = document.getElementById('viewed-products-container');
            const section = document.getElementById('recently-viewed-section');
            
            if (viewed.length === 0) {
                section.classList.add('hidden');
                return;
            }
            
            section.classList.remove('hidden');
            container.innerHTML = '';
            
            viewed.forEach(p => {
                let priceFmt = new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
                // Thay thế ngoặc kép bằng &quot; để tránh lỗi khi gán vào onclick
                let pJson = JSON.stringify(p).replace(/"/g, '&quot;');
                
                const card = document.createElement('div');
                card.className = 'min-w-[140px] md:min-w-[180px] w-[140px] md:w-[180px] flex-shrink-0 bg-white border border-gray-200 hover:border-primary rounded-xl p-2 md:p-3 relative group transition cursor-pointer shadow-sm';
                card.setAttribute('onclick', `viewProduct(${pJson})`);
                
                // HTML bao gồm Nút Xóa có event.stopPropagation()
                card.innerHTML = `
                    <button onclick="event.stopPropagation(); removeViewedProduct('${p.id}')" class="absolute top-1 right-1 bg-red-100 hover:bg-red-500 text-red-500 hover:text-white border border-red-200 w-6 h-6 rounded-full flex items-center justify-center text-[10px] transition z-20 shadow-sm" title="Xóa khỏi lịch sử">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <div class="h-24 md:h-32 mb-2 flex items-center justify-center overflow-hidden bg-gray-50 rounded-lg">
                        <img src="${p.image}" class="max-w-full max-h-full object-contain mix-blend-multiply group-hover:scale-110 transition">
                    </div>
                    <h4 class="text-[11px] md:text-xs text-gray-700 line-clamp-2 h-7 md:h-8 leading-snug group-hover:text-primary transition">${p.name}</h4>
                    <div class="text-danger font-bold text-xs md:text-sm mt-1">${priceFmt}</div>
                `;
                container.appendChild(card);
            });
        }

        // Đã sửa lỗi: Dùng String(p.id) !== String(id) để tránh khác biệt kiểu dữ liệu giữa Text và Số
        function removeViewedProduct(id) {
            let viewed = JSON.parse(localStorage.getItem(VIEWED_KEY)) || [];
            viewed = viewed.filter(p => String(p.id) !== String(id));
            localStorage.setItem(VIEWED_KEY, JSON.stringify(viewed));
            renderViewedProducts();
        }

        function clearAllViewed() {
            if(confirm('Bạn có chắc muốn xóa toàn bộ lịch sử xem?')) {
                localStorage.removeItem(VIEWED_KEY);
                renderViewedProducts();
            }
        }

        document.addEventListener('DOMContentLoaded', renderViewedProducts);

        // --- 2. UI & CÁC CHỨC NĂNG KHÁC ---
        function toggleMobileCategory(event) {
            event.stopPropagation();
            document.getElementById('mobile-category-dropdown').classList.toggle('hidden');
        }

        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('mobile-category-dropdown');
            if (dropdown && !dropdown.classList.contains('hidden') && !event.target.closest('#mobile-search-wrapper')) {
                dropdown.classList.add('hidden');
            }
        });

        function switchAuthTab(tab) {
            const formLogin = document.getElementById('form-login');
            const formRegister = document.getElementById('form-register');
            const tabLogin = document.getElementById('tab-login');
            const tabRegister = document.getElementById('tab-register');

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

        // --- 3. AI CHAT ---
        const apiKey = ""; const modelName = "gemini-2.5-flash-preview-09-2025";
        const chatWindow = document.getElementById('ai-chat-window'), chatMessages = document.getElementById('chat-messages'), aiInput = document.getElementById('ai-input');
        function toggleAIChat() { chatWindow.classList.toggle('active'); if (chatWindow.classList.contains('active')) aiInput.focus(); }
        function appendMessage(text, role) { const msgDiv = document.createElement('div'); msgDiv.className = `message ${role}`; msgDiv.innerText = text; chatMessages.appendChild(msgDiv); chatMessages.scrollTop = chatMessages.scrollHeight; }
        function showLoading() { const loader = document.createElement('div'); loader.className = 'message ai loading-indicator'; loader.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div>'; loader.id = 'ai-loading'; chatMessages.appendChild(loader); chatMessages.scrollTop = chatMessages.scrollHeight; }
        function removeLoading() { const loader = document.getElementById('ai-loading'); if (loader) loader.remove(); }
        
        async function callGemini(prompt) {
            const systemPrompt = "Bạn là chuyên gia tư vấn điện máy của hệ thống DienMayPRO. Trả lời nhiệt tình, phân tích ưu nhược điểm rõ ràng.";
            let retries = 0; const maxRetries = 3;
            while (retries < maxRetries) {
                try {
                    const response = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${modelName}:generateContent?key=${apiKey}`, {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }], systemInstruction: { parts: [{ text: systemPrompt }] } })
                    });
                    if (!response.ok) throw new Error('API failed'); const data = await response.json(); return data.candidates?.[0]?.content?.parts?.[0]?.text;
                } catch (e) { retries++; if (retries === maxRetries) throw e; await new Promise(r => setTimeout(r, Math.pow(2, retries) * 500)); }
            }
        }
        
        async function sendMessage() {
            const text = aiInput.value.trim(); if (!text) return;
            appendMessage(text, 'user'); aiInput.value = ''; showLoading();
            try { const aiResponse = await callGemini(text); removeLoading(); appendMessage(aiResponse || "Xin lỗi, tôi gặp chút trục trặc.", 'ai'); } 
            catch (error) { removeLoading(); appendMessage("Hệ thống AI đang bảo trì. Vui lòng thử lại sau.", 'ai'); }
        }
        
        function askAIAboutProduct(productName) { if (!chatWindow.classList.contains('active')) toggleAIChat(); aiInput.value = `Tư vấn giúp tôi về sản phẩm: ${productName}. Có nên mua không?`; sendMessage(); }
        function searchAssistant(inputId) { const query = document.getElementById(inputId).value; if (query) askAIAboutProduct(query); }
        aiInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });
    </script>
</body>
</html>