<?php
// session_start() removed by Router
// database.php is auto-loaded by Router
require_once __DIR__ . '/../../core/mail_helper.php';

/**
 * ============================================================
 * ADMIN.PHP - BẢNG ĐIỀU KHIỂN QUẢN TRỊ TRUNG TÂM (DASHBOARD)
 * ============================================================
 * 
 * CHỨC NĂNG:
 * 1. Phân quyền: Admin (toàn quyền) và Manager (chỉ thấy/quản lý Đơn hàng, Sản phẩm).
 * 2. Quản lý Đơn hàng: Xem đơn, cập nhật trạng thái giao hàng, kiểm tra nội dung/mã GD ngân hàng.
 * 3. Quản lý Sản phẩm: Thêm mới, chỉnh sửa thông tin, giá bán, ảnh, mô tả, cấu hình.
 * 4. Quản lý Danh mục & Thương hiệu: Tạo, sửa tên/icon.
 * 5. Quản lý Tài khoản: Tạo user, phân quyền người dùng (chỉ Admin).
 * 
 * @requires database.php
 */

// Kiểm tra quyền truy cập (Chỉ Admin và Manager mới được vào)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header("Location: index.php");
    exit;
}

$user_role = $_SESSION['role'];
$page = isset($_GET['p']) ? $_GET['p'] : 'orders'; // Mặc định vào thẳng trang Đơn hàng

// Chặn Quản lý (Manager) truy cập vào trang Users, Categories, Brands
if ($user_role === 'manager' && in_array($page, ['users', 'categories', 'brands'])) {
    $page = 'orders';
}

$msg = '';
$msg_type = '';

use App\Service\AdminService;
$adminService = new AdminService($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $adminService->handlePostAction($_POST, $_FILES, $user_role, $_SESSION['user_id']);
    $msg = $result['msg'];
    $msg_type = $result['msg_type'];
}

extract($adminService->getPageData($page, $_GET, $user_role));
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị hệ thống - DIENMAYPRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>
    <script>
        tinymce.init({
            selector: '#prod_desc, #prod_specs',
            height: 250,
            menubar: false,
            plugins: ['advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'media', 'table', 'help', 'wordcount'],
            toolbar: 'undo redo | blocks | bold italic textcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
        });
    </script>
</head>

<body class="bg-gray-100 font-sans flex h-screen overflow-hidden">

    <!-- MOBILE OVERLAY (Click để đóng sidebar) -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-slate-900 text-white flex flex-col h-full shadow-2xl z-40 shrink-0 fixed lg:static inset-y-0 left-0 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
        <div class="h-16 flex items-center justify-between px-4 border-b border-slate-700">
            <a href="index.php"
                class="text-xl font-bold text-yellow-400 flex items-center gap-2 hover:text-white transition">
                <i class="fa-solid fa-bolt-lightning"></i> DIENMAYPRO
            </a>
            <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-white text-lg p-1" title="Đóng menu">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <div class="text-xs text-slate-400 font-bold mb-4 uppercase tracking-wider">Quản lý Bán Hàng</div>

            <a href="?p=orders"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?= $page === 'orders' ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-clipboard-list w-5"></i> Đơn hàng
            </a>

            <a href="?p=warranties"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?= $page === 'warranties' ? 'bg-yellow-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-wrench w-5 text-yellow-400"></i> Y/c Bảo hành
            </a>

            <a href="?p=returns"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?= $page === 'returns' ? 'bg-purple-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-right-left w-5 text-purple-400"></i> Y/c Đổi Trả
            </a>

            <a href="?p=products"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?= $page === 'products' ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-box w-5"></i> Sản phẩm
            </a>

            <a href="?p=homepage"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?= $page === 'homepage' ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-palette w-5"></i> Trang chủ
            </a>

            <?php if ($user_role === 'admin'): ?>
                <div class="text-xs text-slate-400 font-bold mt-6 mb-4 uppercase tracking-wider">Phân loại</div>

                <a href="?p=categories"
                    class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?= $page === 'categories' ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                    <i class="fa-solid fa-list w-5"></i> Danh mục
                </a>

                <a href="?p=brands"
                    class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?= $page === 'brands' ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                    <i class="fa-solid fa-tags w-5"></i> Thương hiệu
                </a>

                <a href="?p=vouchers"
                    class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?= $page === 'vouchers' ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                    <i class="fa-solid fa-ticket w-5"></i> Mã giảm giá
                </a>

                <div class="text-xs text-slate-400 font-bold mt-6 mb-4 uppercase tracking-wider">Hệ thống</div>

                <a href="?p=users"
                    class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?= $page === 'users' ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                    <i class="fa-solid fa-users w-5"></i> Tài khoản
                </a>
            <?php endif; ?>
        </nav>
        <div class="p-4 border-t border-slate-700 text-sm shrink-0">
            <div class="flex items-center gap-3 mb-4 text-slate-300">
                <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center"><i
                        class="fa-solid fa-user"></i></div>
                <div>
                    <div class="font-bold text-white"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
                    <div class="text-xs text-green-400"><?= strtoupper($user_role) ?></div>
                </div>
            </div>
            <a href="index.php"
                class="block w-full text-center py-2 bg-slate-800 hover:bg-slate-700 rounded transition"><i
                    class="fa-solid fa-arrow-left mr-2"></i> Trở về Web</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col h-full overflow-hidden">

        <!-- Topbar -->
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-4 sm:px-8 z-10 shrink-0">
            <div class="flex items-center gap-3">
                <!-- Nút Hamburger (chỉ hiện trên mobile/tablet) -->
                <button onclick="toggleSidebar()" class="lg:hidden text-gray-600 hover:text-blue-600 text-xl p-1" title="Menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h2 class="text-base sm:text-xl font-bold text-gray-800 truncate">
                    <?php
                    if ($page === 'orders')
                        echo "Quản lý Đơn Hàng";
                    elseif ($page === 'products')
                        echo "Quản lý Sản Phẩm";
                    elseif ($page === 'users')
                        echo "Quản lý Tài Khoản";
                    elseif ($page === 'categories')
                        echo "Quản lý Danh Mục";
                    elseif ($page === 'brands')
                        echo "Quản lý Thương Hiệu";
                    elseif ($page === 'vouchers')
                        echo "Quản lý Mã Giảm Giá";
                    elseif ($page === 'homepage')
                        echo "Quản lý Giao Diện Trang Chủ";
                    elseif ($page === 'warranties')
                        echo "Quản lý Yêu Cầu Bảo Hành";
                    elseif ($page === 'returns')
                        echo "Quản lý Yêu Cầu Đổi Trả";
                    ?>
                </h2>
            </div>
            <form method="GET" class="flex bg-gray-100 rounded-lg border border-gray-200 overflow-hidden shrink-0">
                <input type="hidden" name="p" value="<?= $page ?>">
                <?php if (isset($status_filter)): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <?php endif; ?>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm..."
                    class="px-3 sm:px-4 py-2 bg-transparent focus:outline-none text-sm w-32 sm:w-48 md:w-64">
                <button type="submit" class="px-3 sm:px-4 text-gray-500 hover:text-blue-600"><i
                        class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </header>

        <!-- =========================================================
             NỘI DUNG CHÍNH (BẢNG DỮ LIỆU THEO TỪNG TAB)
             ========================================================= -->
        <div class="flex-1 p-3 sm:p-4 md:p-8 overflow-y-auto">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

                <!-- BẢNG ĐƠN HÀNG -->
                <?php if ($page === 'orders'): ?>
                    <!-- TABS PHÂN LOẠI BÊN ADMIN -->
                    <div class="bg-gray-50 p-4 border-b border-gray-200">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-gray-600 font-medium">Đang hiển thị: <b
                                    class="text-gray-800"><?= count($items) ?></b> đơn hàng
                                <?= $status_filter !== 'all' ? '(Lọc theo trạng thái)' : '' ?></span>
                        </div>

                        <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-1">
                            <a href="?p=orders&status=all<?= $search ? '&q=' . $search : '' ?>"
                                class="px-4 py-2 text-sm font-bold rounded-lg whitespace-nowrap transition <?= $status_filter === 'all' ? 'bg-gray-800 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-100 shadow-sm' ?>">
                                Tất cả (<?= $total_orders ?>)
                            </a>
                            <a href="?p=orders&status=pending<?= $search ? '&q=' . $search : '' ?>"
                                class="px-4 py-2 text-sm font-bold rounded-lg whitespace-nowrap transition <?= $status_filter === 'pending' ? 'bg-yellow-500 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-100 shadow-sm' ?>">
                                Chờ xử lý (<?= $status_counts['pending'] ?>)
                            </a>
                            <a href="?p=orders&status=processing<?= $search ? '&q=' . $search : '' ?>"
                                class="px-4 py-2 text-sm font-bold rounded-lg whitespace-nowrap transition <?= $status_filter === 'processing' ? 'bg-blue-500 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-100 shadow-sm' ?>">
                                Báo CK chờ duyệt (<?= $status_counts['processing'] ?>)
                            </a>
                            <a href="?p=orders&status=delivering<?= $search ? '&q=' . $search : '' ?>"
                                class="px-4 py-2 text-sm font-bold rounded-lg whitespace-nowrap transition <?= $status_filter === 'delivering' ? 'bg-indigo-500 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-100 shadow-sm' ?>">
                                Đang giao (<?= $status_counts['delivering'] ?>)
                            </a>
                            <a href="?p=orders&status=completed<?= $search ? '&q=' . $search : '' ?>"
                                class="px-4 py-2 text-sm font-bold rounded-lg whitespace-nowrap transition <?= $status_filter === 'completed' ? 'bg-green-500 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-100 shadow-sm' ?>">
                                Hoàn thành (<?= $status_counts['completed'] ?>)
                            </a>
                            <a href="?p=orders&status=cancelled<?= $search ? '&q=' . $search : '' ?>"
                                class="px-4 py-2 text-sm font-bold rounded-lg whitespace-nowrap transition <?= $status_filter === 'cancelled' ? 'bg-red-500 text-white shadow-md' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-100 shadow-sm' ?>">
                                Đã hủy (<?= $status_counts['cancelled'] ?>)
                            </a>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm min-w-[700px]">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
                                <th class="p-4 border-b font-semibold w-16">Mã ĐH</th>
                                <th class="p-4 border-b font-semibold">Khách hàng</th>
                                <th class="p-4 border-b font-semibold">Tổng tiền</th>
                                <th class="p-4 border-b font-semibold">Trạng thái</th>
                                <th class="p-4 border-b font-semibold">Ngày đặt</th>
                                <th class="p-4 border-b font-semibold text-center w-28">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr class="hover:bg-gray-50 border-b border-gray-100 transition">
                                    <td class="p-4 font-bold text-gray-700">#<?= $item['id'] ?></td>
                                    <td class="p-4">
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($item['fullname']) ?> -
                                            <?= htmlspecialchars($item['phone']) ?></div>
                                        <div class="text-gray-500 text-xs truncate max-w-xs">
                                            <?= htmlspecialchars($item['address']) ?></div>
                                        <?php if ($item['note']): ?>
                                            <div
                                                class="text-xs mt-1 <?= strpos($item['note'], 'chuyển khoản') !== false || strpos($item['note'], 'tự hủy') !== false ? 'text-blue-600 font-bold' : 'text-gray-400' ?>">
                                                <i class="fa-solid fa-comment-dots"></i> <?= htmlspecialchars($item['note']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 font-bold text-danger"><?= number_format($item['total_price']) ?>đ</td>
                                    <td class="p-4">
                                        <?php
                                        if ($item['status'] == 'pending')
                                            echo '<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-bold border border-yellow-200">Chờ xử lý COD</span>';
                                        elseif ($item['status'] == 'processing')
                                            echo '<span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-bold border border-blue-200 animate-pulse block w-fit">Báo đã CK - Chờ duyệt</span>';
                                        elseif ($item['status'] == 'delivering')
                                            echo '<span class="bg-indigo-100 text-indigo-700 px-2 py-1 rounded text-xs font-bold border border-indigo-200">Đang giao hàng</span>';
                                        elseif ($item['status'] == 'completed')
                                            echo '<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold border border-green-200">Hoàn thành</span>';
                                        elseif ($item['status'] == 'cancelled')
                                            echo '<span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-bold border border-red-200">Đã hủy</span>';
                                        ?>
                                    </td>
                                    <td class="p-4 text-gray-500 text-xs">
                                        <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></td>
                                    <td class="p-4 text-center">
                                        <button onclick='viewOrder(<?= json_encode($item) ?>)'
                                            class="text-blue-500 hover:bg-blue-50 w-8 h-8 rounded-full transition"
                                            title="Xem chi tiết & Cập nhật"><i class="fa-solid fa-eye"></i></button>
                                        <?php if ($user_role === 'admin'): ?>
                                            <form method="POST" class="inline-block" onsubmit="return confirmDelete(event)">
                                                <input type="hidden" name="action" value="delete_order">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <button type="submit"
                                                    class="text-red-500 hover:bg-red-50 w-8 h-8 rounded-full transition"
                                                    title="Xóa đơn hàng"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-gray-500">Không có đơn hàng nào trong mục này
                                    </td>
                                </tr><?php endif; ?>
                        </tbody>
                    </table>
                    </div>

                    <!-- BẢNG SẢN PHẨM -->
                <?php elseif ($page === 'products'): ?>
                    <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-gray-50">
                        <span class="text-gray-600 font-medium">Tổng cộng: <?= count($items) ?> sản phẩm</span>
                        <button onclick="openProductModal()"
                            class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition text-sm font-bold flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Thêm Sản Phẩm
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm min-w-[700px]">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
                                <th class="p-4 border-b font-semibold w-16">ID</th>
                                <th class="p-4 border-b font-semibold w-24">Ảnh</th>
                                <th class="p-4 border-b font-semibold w-1/3">Tên sản phẩm</th>
                                <th class="p-4 border-b font-semibold">Hãng / Danh mục</th>
                                <th class="p-4 border-b font-semibold">Giá bán</th>
                                <th class="p-4 border-b font-semibold text-center w-28">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr class="hover:bg-gray-50 border-b border-gray-100 transition">
                                    <td class="p-4 text-gray-500">#<?= $item['id'] ?></td>
                                    <td class="p-4"><img src="<?= $item['image'] ?>"
                                            class="w-12 h-12 object-contain border border-gray-200 rounded p-1 bg-white"></td>
                                    <td class="p-4 font-medium text-gray-800"><?= htmlspecialchars($item['name']) ?></td>
                                    <td class="p-4">
                                        <div class="text-gray-800 font-medium"><?= $item['brand_name'] ?? 'Không rõ' ?></div>
                                        <div class="text-gray-400 text-xs"><?= $item['cat_name'] ?? 'Không rõ' ?></div>
                                    </td>
                                    <td class="p-4 font-bold text-red-600"><?= number_format($item['price']) ?>đ</td>
                                    <td class="p-4 text-center">
                                        <button onclick="editProduct(<?= htmlspecialchars(json_encode($item)) ?>)"
                                            class="text-blue-500 hover:bg-blue-50 w-8 h-8 rounded-full transition"
                                            title="Sửa"><i class="fa-solid fa-pen"></i></button>
                                        <form method="POST" class="inline-block" onsubmit="return confirmDelete(event)">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button type="submit"
                                                class="text-red-500 hover:bg-red-50 w-8 h-8 rounded-full transition"
                                                title="Xóa"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-gray-500">Không có dữ liệu</td>
                                </tr><?php endif; ?>
                        </tbody>
                    </table>
                    </div>

                    <!-- BẢNG TÀI KHOẢN -->
                <?php elseif ($page === 'users'): ?>
                    <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-gray-50">
                        <span class="text-gray-600 font-medium">Tổng cộng: <?= count($items) ?> tài khoản</span>
                        <button onclick="openUserModal()"
                            class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition text-sm font-bold flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Thêm Tài Khoản
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm min-w-[600px]">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
                                <th class="p-4 border-b font-semibold w-16">ID</th>
                                <th class="p-4 border-b font-semibold">Tên & Username</th>
                                <th class="p-4 border-b font-semibold">Số điện thoại</th>
                                <th class="p-4 border-b font-semibold">Cấp quyền (Role)</th>
                                <th class="p-4 border-b font-semibold text-center w-28">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr class="hover:bg-gray-50 border-b border-gray-100 transition">
                                    <td class="p-4 text-gray-500">#<?= $item['id'] ?></td>
                                    <td class="p-4">
                                        <div class="font-bold text-gray-800"><?= htmlspecialchars($item['fullname']) ?></div>
                                        <div class="text-gray-500 text-xs">@<?= htmlspecialchars($item['username']) ?></div>
                                    </td>
                                    <td class="p-4 font-medium text-gray-700"><?= htmlspecialchars($item['phone']) ?></td>
                                    <td class="p-4">
                                        <?php
                                        if ($item['role'] == 'admin')
                                            echo '<span class="bg-red-100 text-red-600 px-2 py-1 rounded text-xs font-bold">Admin</span>';
                                        elseif ($item['role'] == 'manager')
                                            echo '<span class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs font-bold">Quản lý</span>';
                                        else
                                            echo '<span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-bold">Khách hàng</span>';
                                        ?>
                                    </td>
                                    <td class="p-4 text-center">
                                        <button onclick="editUser(<?= htmlspecialchars(json_encode($item)) ?>)"
                                            class="text-blue-500 hover:bg-blue-50 w-8 h-8 rounded-full transition"><i
                                                class="fa-solid fa-pen"></i></button>
                                        <?php if ($item['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" class="inline-block" onsubmit="return confirmDelete(event)">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <button type="submit"
                                                    class="text-red-500 hover:bg-red-50 w-8 h-8 rounded-full transition"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <!-- BẢNG DANH MỤC -->
                <?php elseif ($page === 'categories'): ?>
                    <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-gray-50">
                        <span class="text-gray-600 font-medium">Tổng cộng: <?= count($items) ?> danh mục</span>
                        <button onclick="openCategoryModal()"
                            class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition text-sm font-bold flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Thêm Danh Mục
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm min-w-[500px]">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
                                <th class="p-4 border-b font-semibold w-16">ID</th>
                                <th class="p-4 border-b font-semibold w-24 text-center">Icon</th>
                                <th class="p-4 border-b font-semibold">Tên danh mục</th>
                                <th class="p-4 border-b font-semibold text-center w-28">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr class="hover:bg-gray-50 border-b border-gray-100 transition">
                                    <td class="p-4 text-gray-500">#<?= $item['id'] ?></td>
                                    <td class="p-4 text-center text-xl text-blue-600"><i
                                            class="fa-solid <?= htmlspecialchars($item['icon']) ?>"></i></td>
                                    <td class="p-4 font-bold text-gray-800"><?= htmlspecialchars($item['name']) ?></td>
                                    <td class="p-4 text-center">
                                        <button onclick="editCategory(<?= htmlspecialchars(json_encode($item)) ?>)"
                                            class="text-blue-500 hover:bg-blue-50 w-8 h-8 rounded-full transition"><i
                                                class="fa-solid fa-pen"></i></button>
                                        <form method="POST" class="inline-block" onsubmit="return confirmDelete(event)">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button type="submit"
                                                class="text-red-500 hover:bg-red-50 w-8 h-8 rounded-full transition"><i
                                                    class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <!-- BẢNG THƯƠNG HIỆU -->
                <?php elseif ($page === 'brands'): ?>
                    <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-gray-50">
                        <span class="text-gray-600 font-medium">Tổng cộng: <?= count($items) ?> thương hiệu</span>
                        <button onclick="openBrandModal()"
                            class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition text-sm font-bold flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Thêm Hãng
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm min-w-[500px]">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
                                <th class="p-4 border-b font-semibold w-16">ID</th>
                                <th class="p-4 border-b font-semibold">Tên Thương Hiệu</th>
                                <th class="p-4 border-b font-semibold text-center w-28">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr class="hover:bg-gray-50 border-b border-gray-100 transition">
                                    <td class="p-4 text-gray-500">#<?= $item['id'] ?></td>
                                    <td class="p-4 font-bold text-gray-800 uppercase tracking-wide">
                                        <?= htmlspecialchars($item['name']) ?></td>
                                    <td class="p-4 text-center">
                                        <button onclick="editBrand(<?= htmlspecialchars(json_encode($item)) ?>)"
                                            class="text-blue-500 hover:bg-blue-50 w-8 h-8 rounded-full transition"><i
                                                class="fa-solid fa-pen"></i></button>
                                        <form method="POST" class="inline-block" onsubmit="return confirmDelete(event)">
                                            <input type="hidden" name="action" value="delete_brand">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button type="submit"
                                                class="text-red-500 hover:bg-red-50 w-8 h-8 rounded-full transition"><i
                                                    class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php elseif ($page === 'vouchers'): ?>
                    <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-gray-50">
                        <span class="text-gray-600 font-medium">Tổng cộng: <?= count($items) ?> mã giảm giá</span>
                        <button onclick="openVoucherModal()"
                            class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition text-sm font-bold flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Thêm Mã Giảm Giá
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm min-w-[600px]">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
                                <th class="p-4 border-b font-semibold w-16">ID</th>
                                <th class="p-4 border-b font-semibold">Mã Voucher</th>
                                <th class="p-4 border-b font-semibold">Mức giảm</th>
                                <th class="p-4 border-b font-semibold">Đã dùng / Giới hạn</th>
                                <th class="p-4 border-b font-semibold text-center w-28">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr class="hover:bg-gray-50 border-b border-gray-100 transition">
                                    <td class="p-4 text-gray-500">#<?= $item['id'] ?></td>
                                    <td class="p-4 font-bold text-blue-600 uppercase tracking-wide">
                                        <?= htmlspecialchars($item['code']) ?>
                                    </td>
                                    <td class="p-4 font-bold text-danger">
                                        <?= ($item['discount_type'] === 'percent') ? number_format($item['discount_amount']) . '%' : number_format($item['discount_amount']) . 'đ' ?>
                                    </td>
                                    <td class="p-4 text-gray-600">
                                        <?= $item['used_count'] ?> / <?= $item['usage_limit'] == 0 ? 'Vô hạn' : $item['usage_limit'] ?>
                                    </td>
                                    <td class="p-4 text-center">
                                        <button onclick="editVoucher(<?= htmlspecialchars(json_encode($item)) ?>)"
                                            class="text-blue-500 hover:bg-blue-50 w-8 h-8 rounded-full transition"><i
                                                class="fa-solid fa-pen"></i></button>
                                        <form method="POST" class="inline-block" onsubmit="return confirmDelete(event)">
                                            <input type="hidden" name="action" value="delete_voucher">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button type="submit"
                                                class="text-red-500 hover:bg-red-50 w-8 h-8 rounded-full transition"><i
                                                    class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <!-- BẢNG YÊU CẦU BẢO HÀNH -->
                <?php elseif ($page === 'warranties'): ?>
                    <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-gray-50">
                        <span class="text-gray-600 font-medium">Tổng cộng: <?= count($items) ?> yêu cầu bảo hành</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm min-w-[900px]">
                            <thead>
                                <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
                                    <th class="p-4 border-b font-semibold w-16">ID</th>
                                    <th class="p-4 border-b font-semibold">Khách hàng</th>
                                    <th class="p-4 border-b font-semibold">Sản phẩm</th>
                                    <th class="p-4 border-b font-semibold">Lý do lỗi</th>
                                    <th class="p-4 border-b font-semibold">Trạng thái & Ghi chú</th>
                                    <th class="p-4 border-b font-semibold">Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr class="hover:bg-gray-50 border-b border-gray-100 transition align-top">
                                        <td class="p-4 text-gray-500">#<?= $item['id'] ?></td>
                                        <td class="p-4 font-medium text-gray-800"><?= htmlspecialchars($item['fullname']) ?><br><span class="text-xs text-gray-500"><?= htmlspecialchars($item['phone']) ?></span></td>
                                        <td class="p-4 font-bold text-blue-600 text-xs max-w-[150px] truncate"><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td class="p-4 text-gray-600 text-xs max-w-xs break-words">
                                            <?= nl2br(htmlspecialchars($item['reason'])) ?>
                                            <?php $item_media = !empty($item['media']) ? json_decode($item['media'], true) : []; ?>
                                            <?php if (!empty($item_media)): ?>
                                            <div class="flex flex-wrap gap-1 mt-2">
                                                <?php foreach ($item_media as $mf): ?>
                                                <a href="<?= htmlspecialchars($mf) ?>" target="_blank"><img src="<?= htmlspecialchars($mf) ?>" class="w-12 h-12 object-cover rounded border border-gray-200 hover:opacity-75 transition"></a>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4">
                                            <!-- Form cập nhật trạng thái + ghi chú Admin -->
                                            <form method="POST" class="space-y-2">
                                                <input type="hidden" name="action" value="update_warranty_status">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <select name="status" class="text-xs p-1.5 w-full border border-gray-300 rounded font-bold outline-none <?= $item['status']==='pending'?'bg-yellow-50 text-yellow-700':($item['status']==='processing'?'bg-blue-50 text-blue-700':($item['status']==='completed'?'bg-green-50 text-green-700':'bg-red-50 text-red-700')) ?>">
                                                    <option value="pending" <?= $item['status'] == 'pending' ? 'selected' : '' ?>>Chờ duyệt</option>
                                                    <option value="processing" <?= $item['status'] == 'processing' ? 'selected' : '' ?>>Đang xử lý</option>
                                                    <option value="completed" <?= $item['status'] == 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
                                                    <option value="rejected" <?= $item['status'] == 'rejected' ? 'selected' : '' ?>>Từ chối</option>
                                                </select>
                                                <textarea name="admin_note" rows="2" class="w-full text-xs p-2 border border-gray-200 rounded resize-none focus:ring-1 focus:ring-blue-400 outline-none" placeholder="Ghi chú cho khách hàng..."><?= htmlspecialchars($item['admin_note'] ?? '') ?></textarea>
                                                <button type="submit" class="w-full text-xs bg-blue-600 text-white px-3 py-1.5 rounded font-bold hover:bg-blue-700 transition"><i class="fa-solid fa-paper-plane mr-1"></i>Cập nhật</button>
                                            </form>
                                        </td>
                                        <td class="p-4 text-gray-500 text-xs"><?= date('H:i d/m/Y', strtotime($item['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($items)): ?><tr><td colspan="6" class="p-8 text-center text-gray-500">Không có yêu cầu bảo hành nào</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <!-- BẢNG YÊU CẦU ĐỔI TRẢ -->
                <?php elseif ($page === 'returns'): ?>
                    <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-gray-50">
                        <span class="text-gray-600 font-medium">Tổng cộng: <?= count($items) ?> yêu cầu đổi trả</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm min-w-[900px]">
                            <thead>
                                <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
                                    <th class="p-4 border-b font-semibold w-16">ID</th>
                                    <th class="p-4 border-b font-semibold">Khách hàng</th>
                                    <th class="p-4 border-b font-semibold w-24">Link ĐH</th>
                                    <th class="p-4 border-b font-semibold">Lý do trả hàng</th>
                                    <th class="p-4 border-b font-semibold">Trạng thái & Ghi chú</th>
                                    <th class="p-4 border-b font-semibold text-xs">Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr class="hover:bg-gray-50 border-b border-gray-100 transition align-top">
                                        <td class="p-4 text-gray-500">#<?= $item['id'] ?></td>
                                        <td class="p-4 font-medium text-gray-800"><?= htmlspecialchars($item['fullname']) ?><br><span class="text-xs text-gray-500"><?= htmlspecialchars($item['phone']) ?></span></td>
                                        <td class="p-4"><a href="?p=orders&q=<?= $item['order_id'] ?>" target="_blank" class="text-blue-600 font-bold hover:underline">ĐH #<?= $item['order_id'] ?></a><br><span class="text-xs text-danger font-bold"><?= number_format($item['total_price']) ?>đ</span></td>
                                        <td class="p-4 text-gray-600 text-xs max-w-xs break-words">
                                            <?= nl2br(htmlspecialchars($item['reason'])) ?>
                                            <?php $ret_media = !empty($item['media']) ? json_decode($item['media'], true) : []; ?>
                                            <?php if (!empty($ret_media)): ?>
                                            <div class="flex flex-wrap gap-1 mt-2">
                                                <?php foreach ($ret_media as $mf): ?>
                                                <a href="<?= htmlspecialchars($mf) ?>" target="_blank"><img src="<?= htmlspecialchars($mf) ?>" class="w-12 h-12 object-cover rounded border border-gray-200 hover:opacity-75 transition"></a>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4">
                                            <!-- Form cập nhật trạng thái + ghi chú Admin -->
                                            <form method="POST" class="space-y-2">
                                                <input type="hidden" name="action" value="update_return_status">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <select name="status" class="text-xs p-1.5 w-full border border-gray-300 rounded font-bold outline-none <?= $item['status']==='pending'?'bg-yellow-50 text-yellow-700':($item['status']==='approved'?'bg-blue-50 text-blue-700':($item['status']==='refunded'?'bg-green-50 text-green-700':'bg-red-50 text-red-700')) ?>">
                                                    <option value="pending" <?= $item['status'] == 'pending' ? 'selected' : '' ?>>Chờ xử lý</option>
                                                    <option value="approved" <?= $item['status'] == 'approved' ? 'selected' : '' ?>>Đã thu hồi hàng</option>
                                                    <option value="refunded" <?= $item['status'] == 'refunded' ? 'selected' : '' ?>>Đã hoàn tiền</option>
                                                    <option value="rejected" <?= $item['status'] == 'rejected' ? 'selected' : '' ?>>Từ chối</option>
                                                </select>
                                                <textarea name="admin_note" rows="2" class="w-full text-xs p-2 border border-gray-200 rounded resize-none focus:ring-1 focus:ring-purple-400 outline-none" placeholder="Ghi chú cho khách hàng..."><?= htmlspecialchars($item['admin_note'] ?? '') ?></textarea>
                                                <button type="submit" class="w-full text-xs bg-purple-600 text-white px-3 py-1.5 rounded font-bold hover:bg-purple-700 transition"><i class="fa-solid fa-paper-plane mr-1"></i>Cập nhật</button>
                                            </form>
                                        </td>
                                        <td class="p-4 text-gray-500 text-xs"><?= date('H:i d/m/Y', strtotime($item['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($items)): ?><tr><td colspan="6" class="p-8 text-center text-gray-500">Không có yêu cầu đổi trả nào</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($page === 'homepage'): ?>
                    <!-- QUẢN LÝ BANNER TRANG CHỦ -->
                    <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 bg-gray-50">
                        <span class="text-gray-600 font-medium"><i class="fa-solid fa-image mr-1 text-blue-500"></i> Chỉnh sửa nội dung Banner quảng cáo trên trang chủ</span>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="p-6">
                        <input type="hidden" name="action" value="update_banner">

                        <!-- XEM TRƯỚC BANNER -->
                        <div class="mb-6">
                            <label class="block text-sm font-bold text-gray-700 mb-2"><i class="fa-solid fa-eye mr-1"></i> Xem trước Banner</label>
                            <div id="bannerPreview" class="relative rounded-xl overflow-hidden h-[180px] md:h-[280px] shadow-md">
                                <img id="previewBannerImg" src="<?= htmlspecialchars($site_settings['banner_image'] ?? '') ?>" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-r from-[#00388a]/90 to-transparent flex flex-col justify-center px-6 md:px-12 text-white">
                                    <span id="previewBadge" class="bg-red-500 text-white text-[10px] md:text-xs font-bold px-3 py-1 rounded w-fit mb-2"><?= htmlspecialchars($site_settings['banner_badge'] ?? 'SIÊU SALE') ?></span>
                                    <h2 class="text-xl md:text-4xl font-extrabold mb-2 leading-tight">
                                        <span id="previewTitle1"><?= htmlspecialchars($site_settings['banner_title1'] ?? '') ?></span>
                                        <br><span id="previewTitle2" class="text-yellow-300"><?= htmlspecialchars($site_settings['banner_title2'] ?? '') ?></span>
                                    </h2>
                                    <p id="previewSubtitle" class="hidden md:block text-sm text-blue-100 max-w-sm mt-2"><?= htmlspecialchars($site_settings['banner_subtitle'] ?? '') ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fa-solid fa-bookmark mr-1 text-red-400"></i> Nhãn badge (VD: SIÊU SALE)</label>
                                <input type="text" name="banner_badge" id="inp_badge" value="<?= htmlspecialchars($site_settings['banner_badge'] ?? '') ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" oninput="document.getElementById('previewBadge').innerText=this.value">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fa-solid fa-upload mr-1 text-green-500"></i> Tải ảnh nền từ máy tính</label>
                                <input type="file" name="banner_image_upload" accept="image/*" id="inp_banner_file"
                                    class="w-full px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm bg-gray-50"
                                    onchange="previewBannerFile(this)">
                                <p class="text-xs text-gray-400 mt-1">Ưu tiên file upload. Để trống nếu muốn dùng URL bên dưới.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fa-solid fa-link mr-1 text-blue-400"></i> Hoặc dán URL ảnh nền</label>
                                <input type="text" name="banner_image" id="inp_banner_image" value="<?= htmlspecialchars($site_settings['banner_image'] ?? '') ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="https://..." oninput="document.getElementById('previewBannerImg').src=this.value">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fa-solid fa-heading mr-1 text-blue-500"></i> Tiêu đề dòng 1</label>
                                <input type="text" name="banner_title1" id="inp_title1" value="<?= htmlspecialchars($site_settings['banner_title1'] ?? '') ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" oninput="document.getElementById('previewTitle1').innerText=this.value">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fa-solid fa-heading mr-1 text-yellow-500"></i> Tiêu đề dòng 2 (Highlight)</label>
                                <input type="text" name="banner_title2" id="inp_title2" value="<?= htmlspecialchars($site_settings['banner_title2'] ?? '') ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" oninput="document.getElementById('previewTitle2').innerText=this.value">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fa-solid fa-align-left mr-1 text-purple-500"></i> Mô tả phụ</label>
                                <textarea name="banner_subtitle" id="inp_subtitle" rows="2"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none resize-none" oninput="document.getElementById('previewSubtitle').innerText=this.value"><?= htmlspecialchars($site_settings['banner_subtitle'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="flex justify-end mt-6 pt-4 border-t border-gray-200">
                            <button type="submit"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold transition shadow-md flex items-center gap-2">
                                <i class="fa-solid fa-floppy-disk"></i> Lưu Thay Đổi Banner
                            </button>
                        </div>
                    </form>

                <?php endif; ?>

            </div>
        </div>
    </main>

    <!-- =========================================================
         MODALS KHU VỰC DÀNH CHO FORM THÊM / SỬA / XEM CHI TIẾT
         ========================================================= -->

    <!-- MODAL ĐƠN HÀNG (Duyệt & Xem chi tiết) -->
    <div id="orderModal"
        class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto relative">
            <div
                class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10">
                <h3 class="text-lg font-bold text-gray-800" id="orderModalTitle">Chi tiết đơn hàng</h3>
                <button type="button" onclick="closeModal('orderModal')"
                    class="text-gray-400 hover:text-red-500 text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Thông tin khách -->
                <div>
                    <h4 class="font-bold text-gray-700 mb-3 border-b pb-2"><i
                            class="fa-solid fa-user-circle mr-1 text-primary"></i> Thông tin khách hàng</h4>
                    <div class="space-y-2 text-sm">
                        <p><span class="text-gray-500 w-20 inline-block">Họ tên:</span> <b id="detail_fullname"
                                class="text-gray-800"></b></p>
                        <p><span class="text-gray-500 w-20 inline-block">Điện thoại:</span> <b id="detail_phone"
                                class="text-gray-800"></b></p>
                        <p class="flex items-start"><span class="text-gray-500 w-20 inline-block shrink-0">Địa
                                chỉ:</span> <span id="detail_address" class="text-gray-800"></span></p>
                        <p class="flex items-start"><span class="text-gray-500 w-20 inline-block shrink-0">Ghi
                                chú:</span> <span id="detail_note" class="text-red-600 font-medium"></span></p>
                        <p class="flex items-start hidden" id="detail_voucher_container"><span class="text-gray-500 w-20 inline-block shrink-0">Voucher:</span> <span id="detail_voucher" class="text-green-600 font-bold bg-green-50 px-2 py-0.5 rounded border border-green-200"></span></p>

                        <!-- MÃ ĐỐI SOÁT NGÂN HÀNG (THÊM MỚI Ở ĐÂY) -->
                        <div class="bg-yellow-50 border border-yellow-300 p-3 rounded-lg mt-3 shadow-sm">
                            <span class="text-gray-600 text-xs block mb-1 font-bold"><i
                                    class="fa-solid fa-money-check-dollar"></i> Nội dung chuyển khoản (Kiểm tra ngân
                                hàng):</span>
                            <b id="detail_transfer_code"
                                class="text-blue-700 font-mono text-[15px] tracking-wide select-all bg-white px-2 py-1 rounded border border-blue-200 inline-block w-full text-center"></b>
                            <p class="text-[10px] text-gray-500 mt-1 italic text-center"></p>
                        </div>
                    </div>
                </div>

                <!-- Cập nhật trạng thái -->
                <div class="bg-blue-50 p-5 rounded-xl border border-blue-100 shadow-sm">
                    <h4 class="font-bold text-blue-800 mb-3"><i class="fa-solid fa-truck-fast mr-1"></i> Cập nhật trạng
                        thái Đơn hàng</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_order_status">
                        <input type="hidden" name="id" id="detail_order_id">

                        <label class="block text-xs font-bold text-blue-700 mb-1">Trạng thái hiện tại:</label>
                        <select name="status" id="detail_status"
                            class="w-full px-4 py-2.5 border border-blue-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none mb-4 font-bold text-gray-700 bg-white shadow-sm">
                            <option value="pending">Chờ xử lý (COD - Thanh toán tiền mặt)</option>
                            <option value="processing">Báo đã CK QR - Đang chờ kiểm tra tiền</option>
                            <option value="delivering">Đã xác nhận - Đang giao hàng</option>
                            <option value="completed">Giao hàng thành công</option>
                            <option value="cancelled">Khách hủy đơn / Đơn ảo</option>
                        </select>
                        <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-lg transition shadow-md flex justify-center items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i> Lưu Trạng Thái
                        </button>
                    </form>
                </div>

                <!-- Chi tiết sản phẩm -->
                <div class="md:col-span-2 mt-2">
                    <h4 class="font-bold text-gray-700 mb-3 border-b pb-2"><i
                            class="fa-solid fa-box-open mr-1 text-primary"></i> Danh sách sản phẩm đã mua</h4>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-100 text-gray-600 uppercase text-[11px]">
                                    <th class="p-3 border-b">Sản phẩm</th>
                                    <th class="p-3 border-b text-center w-16">SL</th>
                                    <th class="p-3 border-b text-right w-28">Đơn giá</th>
                                    <th class="p-3 border-b text-right w-32">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody id="detail_products">
                                <!-- JS render here -->
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="3" class="p-3 text-right font-bold text-gray-700 uppercase text-xs">
                                        Tổng tiền phải thu:</td>
                                    <td class="p-3 text-right font-extrabold text-danger text-lg"
                                        id="detail_total_price"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL SẢN PHẨM -->
    <div id="productModal"
        class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto relative">
            <div
                class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10">
                <h3 class="text-lg font-bold text-gray-800" id="productModalTitle">Thêm Sản Phẩm Mới</h3>
                <button type="button" onclick="closeModal('productModal')"
                    class="text-gray-400 hover:text-red-500 text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form method="POST" enctype="multipart/form-data" class="p-6">
                <input type="hidden" name="action" id="prod_action" value="add_product">
                <input type="hidden" name="id" id="prod_id" value="">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tên sản phẩm *</label>
                        <input type="text" name="name" id="prod_name" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Danh mục *</label>
                        <select name="category_id" id="prod_category" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Thương hiệu *</label>
                        <select name="brand_id" id="prod_brand" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                            <?php foreach ($brands as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= $b['name'] ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Giá bán (VNĐ) *</label>
                        <input type="number" name="price" id="prod_price" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Giá gốc (Bỏ trống nếu không
                            giảm)</label>
                        <input type="number" name="old_price" id="prod_old_price"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tải ảnh từ máy tính</label>
                        <input type="file" name="image_upload" accept="image/*"
                            class="w-full px-3 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none text-sm bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hoặc dùng URL Ảnh</label>
                        <input type="text" name="image" id="prod_image"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                            placeholder="https://...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quà Tặng (Cách nhau bằng dấu ;
                            )</label>
                        <input type="text" name="gift_text" id="prod_gift"
                            class="w-full px-3 py-2 border border-gray-300 rounded outline-none"
                            placeholder="Tặng ABC; Giảm XYZ...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nhãn dán (Cách nhau bằng dấu
                            phẩy)</label>
                        <input type="text" name="tags" id="prod_tags"
                            class="w-full px-3 py-2 border border-gray-300 rounded outline-none"
                            placeholder="Trả góp 0%, Mới 2024">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả bài viết chi tiết</label>
                        <textarea name="description" id="prod_desc"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Thông số kỹ thuật</label>
                        <textarea name="specifications" id="prod_specs"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeModal('productModal')"
                        class="px-5 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded font-medium transition">Hủy</button>
                    <button type="submit" onclick="tinymce.triggerSave();"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold transition shadow-md">Lưu
                        Sản Phẩm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL TÀI KHOẢN -->
    <div id="userModal"
        class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg relative overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800" id="userModalTitle">Thêm Tài Khoản</h3>
                <button type="button" onclick="closeModal('userModal')"
                    class="text-gray-400 hover:text-red-500 text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" id="usr_action" value="add_user">
                <input type="hidden" name="id" id="usr_id" value="">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Họ và tên *</label>
                        <input type="text" name="fullname" id="usr_fullname" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Số điện thoại *</label>
                        <input type="text" name="phone" id="usr_phone" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tên đăng nhập (Username) *</label>
                        <input type="text" name="username" id="usr_username" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" id="lbl_usr_password">Mật khẩu
                            *</label>
                        <input type="password" name="password" id="usr_password" minlength="8"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none transition">
                        <div id="admin-pw-hints" class="mt-1.5 text-xs space-y-0.5">
                            <p id="admin-pw-len" class="flex items-center gap-1 text-gray-400"><i
                                    class="fa-solid fa-circle text-[6px]"></i> Ít nhất 8 ký tự</p>
                            <p id="admin-pw-letter" class="flex items-center gap-1 text-gray-400"><i
                                    class="fa-solid fa-circle text-[6px]"></i> Ít nhất 1 chữ cái</p>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 hidden" id="hint_usr_password">Bỏ trống nếu không muốn đổi
                            mật khẩu mới.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cấp quyền *</label>
                        <select name="role" id="usr_role" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="customer">Khách hàng (Customer)</option>
                            <option value="manager">Quản lý (Manager)</option>
                            <option value="admin">Quản trị viên (Admin)</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeModal('userModal')"
                        class="px-5 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded font-medium transition">Hủy</button>
                    <button type="submit"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold transition shadow-md">Lưu
                        Tài Khoản</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DANH MỤC -->
    <div id="categoryModal"
        class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md relative overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800" id="categoryModalTitle">Thêm Danh Mục</h3>
                <button type="button" onclick="closeModal('categoryModal')"
                    class="text-gray-400 hover:text-red-500 text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" id="cat_action" value="add_category">
                <input type="hidden" name="id" id="cat_id" value="">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tên danh mục *</label>
                        <input type="text" name="name" id="cat_name" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                            placeholder="VD: Lò vi sóng">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Icon FontAwesome (Tùy chọn)</label>
                        <input type="text" name="icon" id="cat_icon"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                            placeholder="fa-tv, fa-box...">
                        <p class="text-xs text-gray-500 mt-1">Xem icon tại <a href="https://fontawesome.com/icons"
                                target="_blank" class="text-blue-500 hover:underline">fontawesome.com</a></p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeModal('categoryModal')"
                        class="px-5 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded font-medium transition">Hủy</button>
                    <button type="submit"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold transition shadow-md">Lưu
                        Danh Mục</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL THƯƠNG HIỆU -->
    <div id="brandModal"
        class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm relative overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800" id="brandModalTitle">Thêm Thương Hiệu</h3>
                <button type="button" onclick="closeModal('brandModal')"
                    class="text-gray-400 hover:text-red-500 text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" id="brand_action" value="add_brand">
                <input type="hidden" name="id" id="brand_id" value="">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tên Hãng (Brand) *</label>
                    <input type="text" name="name" id="brand_name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                        placeholder="VD: Apple, LG...">
                </div>
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeModal('brandModal')"
                        class="px-5 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded font-medium transition">Hủy</button>
                    <button type="submit"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold transition shadow-md">Lưu
                        Hãng</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL VOUCHER -->
    <div id="voucherModal"
        class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm relative overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800" id="voucherModalTitle">Thêm Mã Giảm Giá</h3>
                <button type="button" onclick="closeModal('voucherModal')"
                    class="text-gray-400 hover:text-red-500 text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" id="voucher_action" value="add_voucher">
                <input type="hidden" name="id" id="voucher_id" value="">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mã Voucher *</label>
                        <input type="text" name="code" id="voucher_code" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none uppercase"
                            placeholder="VD: GIAM10K, PRO10...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Loại giảm giá *</label>
                        <select name="discount_type" id="voucher_discount_type" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="fixed">Giảm số tiền cố định (đ)</option>
                            <option value="percent">Giảm theo phần trăm (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mức giảm *</label>
                        <input type="number" name="discount_amount" id="voucher_discount_amount" required min="1"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                            placeholder="10000 hoặc 10">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Giới hạn sử dụng (0 = vô hạn)</label>
                        <input type="number" name="usage_limit" id="voucher_usage_limit" value="0" min="0" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeModal('voucherModal')"
                        class="px-5 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded font-medium transition">Hủy</button>
                    <button type="submit"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold transition shadow-md">Lưu
                        Mã</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPTS JS -->
    <script>
        <?php if ($msg): ?>
            Swal.fire({
                icon: '<?= $msg_type ?>',
                title: '<?= $msg_type === "success" ? "Thành công!" : "Lỗi!" ?>',
                text: '<?= $msg ?>',
                timer: 2000,
                showConfirmButton: false
            });
        <?php endif; ?>

        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

        // --- SIDEBAR TOGGLE (Responsive) ---
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
        // Tự đóng sidebar khi click vào link menu trên thiết bị nhỏ
        document.querySelectorAll('#sidebar nav a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    toggleSidebar();
                }
            });
        });

        function confirmDelete(e) {
            e.preventDefault();
            const form = e.target;
            Swal.fire({
                title: 'Bạn có chắc chắn muốn xóa?',
                text: "Dữ liệu sau khi xóa sẽ không thể phục hồi!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Xóa ngay!',
                cancelButtonText: 'Hủy'
            }).then((result) => { if (result.isConfirmed) form.submit(); })
        }

        // --- SCRIPTS CHO ĐƠN HÀNG ---
        function viewOrder(order) {
            document.getElementById('orderModalTitle').innerText = 'Chi tiết đơn hàng #' + order.id;
            document.getElementById('detail_order_id').value = order.id;
            document.getElementById('detail_fullname').innerText = order.fullname;
            document.getElementById('detail_phone').innerText = order.phone;
            document.getElementById('detail_address').innerText = order.address;
            document.getElementById('detail_note').innerText = order.note || 'Không có';
            
            if (order.voucher_code) {
                document.getElementById('detail_voucher').innerText = order.voucher_code + ' (-' + new Intl.NumberFormat('vi-VN').format(order.discount_amount) + 'đ)';
                document.getElementById('detail_voucher_container').classList.remove('hidden');
            } else {
                document.getElementById('detail_voucher_container').classList.add('hidden');
            }

            document.getElementById('detail_status').value = order.status;
            document.getElementById('detail_total_price').innerText = new Intl.NumberFormat('vi-VN').format(order.total_price) + 'đ';

            // Cập nhật Mã đối soát thanh toán
            const transferCodeEl = document.getElementById('detail_transfer_code');
            if (transferCodeEl) {
                transferCodeEl.innerText = 'Thanh toan don hang ' + order.id;
            }

            const tbody = document.getElementById('detail_products');
            tbody.innerHTML = '';

            if (order.details && order.details.length > 0) {
                order.details.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.className = 'border-b border-gray-100 hover:bg-gray-50';
                    tr.innerHTML = `
                        <td class="p-3 flex items-center gap-3">
                            <img src="${item.image}" class="w-10 h-10 object-contain border rounded bg-white shrink-0 p-0.5">
                            <span class="font-medium text-gray-800 line-clamp-2">${item.name}</span>
                        </td>
                        <td class="p-3 text-center font-bold text-gray-600">${item.quantity}</td>
                        <td class="p-3 text-right text-gray-600">${new Intl.NumberFormat('vi-VN').format(item.price)}đ</td>
                        <td class="p-3 text-right font-bold text-gray-800">${new Intl.NumberFormat('vi-VN').format(item.price * item.quantity)}đ</td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-gray-500 italic">Không tìm thấy chi tiết sản phẩm.</td></tr>`;
            }

            document.getElementById('orderModal').classList.remove('hidden');
        }

        // --- SCRIPTS CHO SẢN PHẨM ---
        function openProductModal() {
            document.getElementById('prod_action').value = 'add_product';
            document.getElementById('productModalTitle').innerText = 'Thêm Sản Phẩm Mới';
            document.getElementById('prod_id').value = '';
            document.getElementById('prod_name').value = '';
            document.getElementById('prod_price').value = '';
            document.getElementById('prod_old_price').value = '';
            document.getElementById('prod_image').value = '';
            document.getElementById('prod_gift').value = '';
            document.getElementById('prod_tags').value = '';
            if (tinymce.get('prod_desc')) tinymce.get('prod_desc').setContent('');
            if (tinymce.get('prod_specs')) tinymce.get('prod_specs').setContent('');
            document.getElementById('productModal').classList.remove('hidden');
        }

        function editProduct(product) {
            document.getElementById('prod_action').value = 'edit_product';
            document.getElementById('productModalTitle').innerText = 'Sửa Sản Phẩm #' + product.id;
            document.getElementById('prod_id').value = product.id;
            document.getElementById('prod_name').value = product.name;
            document.getElementById('prod_category').value = product.category_id;
            document.getElementById('prod_brand').value = product.brand_id;
            document.getElementById('prod_price').value = product.price;
            document.getElementById('prod_old_price').value = product.old_price;
            document.getElementById('prod_image').value = product.image;
            document.getElementById('prod_gift').value = product.gift_text;
            document.getElementById('prod_tags').value = product.tags;
            if (tinymce.get('prod_desc')) tinymce.get('prod_desc').setContent(product.description || '');
            if (tinymce.get('prod_specs')) tinymce.get('prod_specs').setContent(product.specifications || '');
            document.getElementById('productModal').classList.remove('hidden');
        }

        function resetAdminPwHints() {
            const pwLen = document.getElementById('admin-pw-len');
            const pwLetter = document.getElementById('admin-pw-letter');
            pwLen.className = 'flex items-center gap-1 text-gray-400';
            pwLen.innerHTML = '<i class="fa-solid fa-circle text-[6px]"></i> Ít nhất 8 ký tự';
            pwLetter.className = 'flex items-center gap-1 text-gray-400';
            pwLetter.innerHTML = '<i class="fa-solid fa-circle text-[6px]"></i> Ít nhất 1 chữ cái';
            const oldErr = document.getElementById('admin-pw-error');
            if (oldErr) oldErr.remove();
            const pwInput = document.getElementById('usr_password');
            pwInput.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
            pwInput.value = '';
        }

        function openUserModal() {
            document.getElementById('usr_action').value = 'add_user';
            document.getElementById('userModalTitle').innerText = 'Thêm Tài Khoản Mới';
            document.getElementById('usr_id').value = '';
            document.getElementById('usr_fullname').value = '';
            document.getElementById('usr_phone').value = '';
            document.getElementById('usr_username').value = '';
            document.getElementById('usr_password').required = true;
            document.getElementById('lbl_usr_password').innerText = 'Mật khẩu *';
            document.getElementById('hint_usr_password').classList.add('hidden');
            resetAdminPwHints();
            document.getElementById('userModal').classList.remove('hidden');
        }

        function editUser(user) {
            document.getElementById('usr_action').value = 'edit_user';
            document.getElementById('userModalTitle').innerText = 'Sửa Tài Khoản #' + user.id;
            document.getElementById('usr_id').value = user.id;
            document.getElementById('usr_fullname').value = user.fullname;
            document.getElementById('usr_phone').value = user.phone;
            document.getElementById('usr_username').value = user.username;
            document.getElementById('usr_role').value = user.role;
            document.getElementById('usr_password').required = false;
            document.getElementById('lbl_usr_password').innerText = 'Mật khẩu mới (Tùy chọn)';
            document.getElementById('hint_usr_password').classList.remove('hidden');
            resetAdminPwHints();
            document.getElementById('userModal').classList.remove('hidden');
        }

        function openCategoryModal() {
            document.getElementById('cat_action').value = 'add_category';
            document.getElementById('categoryModalTitle').innerText = 'Thêm Danh Mục';
            document.getElementById('cat_id').value = '';
            document.getElementById('cat_name').value = '';
            document.getElementById('cat_icon').value = '';
            document.getElementById('categoryModal').classList.remove('hidden');
        }

        function editCategory(cat) {
            document.getElementById('cat_action').value = 'edit_category';
            document.getElementById('categoryModalTitle').innerText = 'Sửa Danh Mục';
            document.getElementById('cat_id').value = cat.id;
            document.getElementById('cat_name').value = cat.name;
            document.getElementById('cat_icon').value = cat.icon;
            document.getElementById('categoryModal').classList.remove('hidden');
        }

        function openBrandModal() {
            document.getElementById('brand_action').value = 'add_brand';
            document.getElementById('brandModalTitle').innerText = 'Thêm Thương Hiệu';
            document.getElementById('brand_id').value = '';
            document.getElementById('brand_name').value = '';
            document.getElementById('brandModal').classList.remove('hidden');
        }

        function editBrand(brand) {
            document.getElementById('brand_action').value = 'edit_brand';
            document.getElementById('brandModalTitle').innerText = 'Sửa Thương Hiệu';
            document.getElementById('brand_id').value = brand.id;
            document.getElementById('brand_name').value = brand.name;
            document.getElementById('brandModal').classList.remove('hidden');
        }

        function openVoucherModal() {
            document.getElementById('voucher_action').value = 'add_voucher';
            document.getElementById('voucherModalTitle').innerText = 'Thêm Mã Giảm Giá';
            document.getElementById('voucher_id').value = '';
            document.getElementById('voucher_code').value = '';
            document.getElementById('voucher_discount_type').value = 'fixed';
            document.getElementById('voucher_discount_amount').value = '';
            document.getElementById('voucher_usage_limit').value = '0';
            document.getElementById('voucherModal').classList.remove('hidden');
        }

        function editVoucher(voucher) {
            document.getElementById('voucher_action').value = 'edit_voucher';
            document.getElementById('voucherModalTitle').innerText = 'Sửa Mã Giảm Giá';
            document.getElementById('voucher_id').value = voucher.id;
            document.getElementById('voucher_code').value = voucher.code;
            document.getElementById('voucher_discount_type').value = voucher.discount_type;
            document.getElementById('voucher_discount_amount').value = voucher.discount_amount;
            document.getElementById('voucher_usage_limit').value = voucher.usage_limit;
            document.getElementById('voucherModal').classList.remove('hidden');
        }

        // ===== ADMIN PASSWORD VALIDATION =====
        (function () {
            const pwInput = document.getElementById('usr_password');
            const pwLen = document.getElementById('admin-pw-len');
            const pwLetter = document.getElementById('admin-pw-letter');
            const userForm = document.querySelector('#userModal form');

            if (pwInput && pwLen && pwLetter) {
                pwInput.addEventListener('input', function () {
                    const val = this.value;
                    pwInput.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
                    pwInput.style.animation = '';
                    const oldErr = document.getElementById('admin-pw-error');
                    if (oldErr) oldErr.remove();

                    if (val.length >= 8) {
                        pwLen.className = 'flex items-center gap-1 text-green-500';
                        pwLen.innerHTML = '<i class="fa-solid fa-circle-check text-[10px]"></i> Ít nhất 8 ký tự';
                    } else if (val.length > 0) {
                        pwLen.className = 'flex items-center gap-1 text-red-500';
                        pwLen.innerHTML = '<i class="fa-solid fa-circle-xmark text-[10px]"></i> Ít nhất 8 ký tự';
                    } else {
                        pwLen.className = 'flex items-center gap-1 text-gray-400';
                        pwLen.innerHTML = '<i class="fa-solid fa-circle text-[6px]"></i> Ít nhất 8 ký tự';
                    }

                    if (/[a-zA-Z]/.test(val)) {
                        pwLetter.className = 'flex items-center gap-1 text-green-500';
                        pwLetter.innerHTML = '<i class="fa-solid fa-circle-check text-[10px]"></i> Ít nhất 1 chữ cái';
                    } else if (val.length > 0) {
                        pwLetter.className = 'flex items-center gap-1 text-red-500';
                        pwLetter.innerHTML = '<i class="fa-solid fa-circle-xmark text-[10px]"></i> Ít nhất 1 chữ cái';
                    } else {
                        pwLetter.className = 'flex items-center gap-1 text-gray-400';
                        pwLetter.innerHTML = '<i class="fa-solid fa-circle text-[6px]"></i> Ít nhất 1 chữ cái';
                    }
                });
            }

            if (userForm) {
                userForm.addEventListener('submit', function (e) {
                    const pw = pwInput.value;
                    const isAdding = document.getElementById('usr_action').value === 'add_user';

                    if (pw.length > 0 && (pw.length < 8 || !/[a-zA-Z]/.test(pw))) {
                        e.preventDefault();
                        const oldErr = document.getElementById('admin-pw-error');
                        if (oldErr) oldErr.remove();
                        const errDiv = document.createElement('div');
                        errDiv.id = 'admin-pw-error';
                        errDiv.className = 'bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm text-center border border-red-200 flex items-center justify-center gap-2';
                        errDiv.style.animation = 'fadeIn 0.3s ease';
                        errDiv.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Mật khẩu phải có ít nhất 8 ký tự và chứa ít nhất 1 chữ cái!';
                        const formContent = userForm.querySelector('.space-y-4');
                        userForm.insertBefore(errDiv, formContent);
                        pwInput.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                        pwInput.style.animation = 'shake 0.4s ease';
                        pwInput.focus();
                        return;
                    }

                    if (isAdding && pw.length === 0) {
                        e.preventDefault();
                        const oldErr = document.getElementById('admin-pw-error');
                        if (oldErr) oldErr.remove();
                        const errDiv = document.createElement('div');
                        errDiv.id = 'admin-pw-error';
                        errDiv.className = 'bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm text-center border border-red-200 flex items-center justify-center gap-2';
                        errDiv.style.animation = 'fadeIn 0.3s ease';
                        errDiv.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Vui lòng nhập mật khẩu!';
                        const formContent = userForm.querySelector('.space-y-4');
                        userForm.insertBefore(errDiv, formContent);
                        pwInput.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                        pwInput.style.animation = 'shake 0.4s ease';
                        pwInput.focus();
                    }
                });
            }
        })();
    </script>

    <style>
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

        /* Ẩn thanh cuộn ngang cho tabs bộ lọc */
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Cuộn bảng mượt trên mobile */
        .overflow-x-auto::-webkit-scrollbar {
            height: 4px;
        }
        .overflow-x-auto::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .overflow-x-auto::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 999px;
        }

        /* Đảm bảo modal responsive trên mobile */
        @media (max-width: 640px) {
            .max-w-3xl, .max-w-4xl, .max-w-lg, .max-w-md, .max-w-sm {
                max-width: calc(100vw - 2rem) !important;
            }
        }

        /* Fix cho TinyMCE trên mobile */
        @media (max-width: 768px) {
            .tox-tinymce {
                min-height: 180px !important;
            }
        }
    </style>
</body>

</html>