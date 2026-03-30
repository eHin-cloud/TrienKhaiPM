<?php
session_start();
require_once 'database.php';

// Kiểm tra quyền truy cập (Chỉ Admin và Manager mới được vào)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header("Location: index.php");
    exit;
}

$user_role = $_SESSION['role'];
$page = isset($_GET['p']) ? $_GET['p'] : 'products';

// Chặn Quản lý (Manager) truy cập vào trang Users, Categories, Brands
if ($user_role === 'manager' && in_array($page, ['users', 'categories', 'brands'])) {
    $page = 'products';
}

$msg = '';
$msg_type = '';

// ==========================================
// XỬ LÝ CÁC ACTION (THÊM, SỬA, XÓA)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- XỬ LÝ SẢN PHẨM ---
    if ($action === 'add_product' || $action === 'edit_product') {
        $id = $_POST['id'] ?? null;
        $name = $_POST['name'];
        $category_id = $_POST['category_id'];
        $brand_id = $_POST['brand_id'];
        $price = $_POST['price'];
        $old_price = !empty($_POST['old_price']) ? $_POST['old_price'] : null;
        $gift_text = $_POST['gift_text'] ?? '';
        $tags = $_POST['tags'] ?? '';
        $description = $_POST['description'] ?? '';
        $specifications = $_POST['specifications'] ?? '';
        
        $image = $_POST['image']; 
        
        if (isset($_FILES['image_upload']) && $_FILES['image_upload']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_name = time() . '_' . basename($_FILES['image_upload']['name']);
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['image_upload']['tmp_name'], $target_file)) {
                $image = $target_file; 
            }
        }

        if ($action === 'add_product') {
            $stmt = $db->prepare("INSERT INTO products (name, category_id, brand_id, price, old_price, image, gift_text, tags, description, specifications) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $category_id, $brand_id, $price, $old_price, $image, $gift_text, $tags, $description, $specifications]);
            $msg = "Thêm sản phẩm thành công!";
        } else {
            $stmt = $db->prepare("UPDATE products SET name=?, category_id=?, brand_id=?, price=?, old_price=?, image=?, gift_text=?, tags=?, description=?, specifications=? WHERE id=?");
            $stmt->execute([$name, $category_id, $brand_id, $price, $old_price, $image, $gift_text, $tags, $description, $specifications, $id]);
            $msg = "Cập nhật sản phẩm thành công!";
        }
        $msg_type = 'success';
    } elseif ($action === 'delete_product') {
        $id = $_POST['id'];
        $db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
        $msg = "Đã xóa sản phẩm!";
        $msg_type = 'success';
    }

    // --- XỬ LÝ NGƯỜI DÙNG (CHỈ ADMIN) ---
    if ($user_role === 'admin') {
        if ($action === 'add_user' || $action === 'edit_user') {
            $id = $_POST['id'] ?? null;
            $fullname = $_POST['fullname'];
            $phone = $_POST['phone'];
            $username = $_POST['username'];
            $password = $_POST['password'];
            $role = $_POST['role'];

            $check = $db->prepare("SELECT id FROM users WHERE (username = ? OR phone = ?) AND id != ?");
            $check->execute([$username, $phone, $id ?? 0]);
            if ($check->fetch()) {
                $msg = "Tên đăng nhập hoặc Số điện thoại đã tồn tại!";
                $msg_type = 'error';
            } else {
                if ($action === 'add_user') {
                    $stmt = $db->prepare("INSERT INTO users (fullname, phone, username, password, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$fullname, $phone, $username, $password, $role]);
                    $msg = "Thêm tài khoản thành công!";
                } else {
                    if (!empty($password)) {
                        $stmt = $db->prepare("UPDATE users SET fullname=?, phone=?, username=?, password=?, role=? WHERE id=?");
                        $stmt->execute([$fullname, $phone, $username, $password, $role, $id]);
                    } else {
                        $stmt = $db->prepare("UPDATE users SET fullname=?, phone=?, username=?, role=? WHERE id=?");
                        $stmt->execute([$fullname, $phone, $username, $role, $id]);
                    }
                    $msg = "Cập nhật tài khoản thành công!";
                }
                $msg_type = 'success';
            }
        } elseif ($action === 'delete_user') {
            $id = $_POST['id'];
            if ($id != $_SESSION['user_id']) { 
                $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
                $msg = "Đã xóa tài khoản!";
                $msg_type = 'success';
            } else {
                $msg = "Không thể tự xóa tài khoản của chính mình!";
                $msg_type = 'error';
            }
        }
        
        // --- XỬ LÝ DANH MỤC ---
        elseif ($action === 'add_category' || $action === 'edit_category') {
            $id = $_POST['id'] ?? null;
            $name = $_POST['name'];
            $icon = $_POST['icon'] ?? 'fa-box';
            
            if ($action === 'add_category') {
                $db->prepare("INSERT INTO categories (name, icon) VALUES (?, ?)")->execute([$name, $icon]);
                $msg = "Thêm danh mục thành công!";
            } else {
                $db->prepare("UPDATE categories SET name=?, icon=? WHERE id=?")->execute([$name, $icon, $id]);
                $msg = "Cập nhật danh mục thành công!";
            }
            $msg_type = 'success';
        } elseif ($action === 'delete_category') {
            $db->prepare("DELETE FROM categories WHERE id=?")->execute([$_POST['id']]);
            $msg = "Đã xóa danh mục!";
            $msg_type = 'success';
        }

        // --- XỬ LÝ THƯƠNG HIỆU ---
        elseif ($action === 'add_brand' || $action === 'edit_brand') {
            $id = $_POST['id'] ?? null;
            $name = $_POST['name'];
            
            if ($action === 'add_brand') {
                $db->prepare("INSERT INTO brands (name) VALUES (?)")->execute([$name]);
                $msg = "Thêm hãng thành công!";
            } else {
                $db->prepare("UPDATE brands SET name=? WHERE id=?")->execute([$name, $id]);
                $msg = "Cập nhật hãng thành công!";
            }
            $msg_type = 'success';
        } elseif ($action === 'delete_brand') {
            $db->prepare("DELETE FROM brands WHERE id=?")->execute([$_POST['id']]);
            $msg = "Đã xóa thương hiệu!";
            $msg_type = 'success';
        }
    }
}

// ==========================================
// TẢI DỮ LIỆU ĐỂ HIỂN THỊ
// ==========================================
$categories = getAllCategories($db);
$brands = getAllBrands($db);
$search = $_GET['q'] ?? '';

// Dữ liệu hiển thị theo Tab
if ($page === 'products') {
    $sql = "SELECT p.*, c.name as cat_name, b.name as brand_name FROM products p LEFT JOIN categories c ON p.category_id=c.id LEFT JOIN brands b ON p.brand_id=b.id";
    if ($search) $sql .= " WHERE p.name LIKE '%$search%'";
    $sql .= " ORDER BY p.id DESC";
    $items = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} elseif ($page === 'users' && $user_role === 'admin') {
    $sql = "SELECT * FROM users";
    if ($search) $sql .= " WHERE fullname LIKE '%$search%' OR username LIKE '%$search%' OR phone LIKE '%$search%'";
    $sql .= " ORDER BY id DESC";
    $items = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} elseif ($page === 'categories' && $user_role === 'admin') {
    $sql = "SELECT * FROM categories";
    if ($search) $sql .= " WHERE name LIKE '%$search%'";
    $items = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} elseif ($page === 'brands' && $user_role === 'admin') {
    $sql = "SELECT * FROM brands";
    if ($search) $sql .= " WHERE name LIKE '%$search%'";
    $items = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
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

    <!-- SIDEBAR -->
    <aside class="w-64 bg-slate-900 text-white flex flex-col h-full shadow-2xl z-20 shrink-0">
        <div class="h-16 flex items-center justify-center border-b border-slate-700">
            <a href="index.php" class="text-xl font-bold text-yellow-400 flex items-center gap-2 hover:text-white transition">
                <i class="fa-solid fa-bolt-lightning"></i> DIENMAYPRO
            </a>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <div class="text-xs text-slate-400 font-bold mb-4 uppercase tracking-wider">Quản lý Cửa Hàng</div>
            
            <a href="?p=products" class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?= $page==='products' ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-box w-5"></i> Sản phẩm
            </a>
            
            <?php if ($user_role === 'admin'): ?>
            <div class="text-xs text-slate-400 font-bold mt-6 mb-4 uppercase tracking-wider">Phân loại</div>
            
            <a href="?p=categories" class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?= $page==='categories' ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-list w-5"></i> Danh mục
            </a>
            
            <a href="?p=brands" class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?= $page==='brands' ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-tags w-5"></i> Thương hiệu
            </a>

            <div class="text-xs text-slate-400 font-bold mt-6 mb-4 uppercase tracking-wider">Hệ thống</div>
            
            <a href="?p=users" class="flex items-center gap-3 px-4 py-3 rounded-lg transition <?= $page==='users' ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-users w-5"></i> Tài khoản
            </a>
            <?php endif; ?>
        </nav>
        <div class="p-4 border-t border-slate-700 text-sm shrink-0">
            <div class="flex items-center gap-3 mb-4 text-slate-300">
                <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center"><i class="fa-solid fa-user"></i></div>
                <div>
                    <div class="font-bold text-white"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
                    <div class="text-xs text-green-400"><?= strtoupper($user_role) ?></div>
                </div>
            </div>
            <a href="index.php" class="block w-full text-center py-2 bg-slate-800 hover:bg-slate-700 rounded transition"><i class="fa-solid fa-arrow-left mr-2"></i> Trở về Web</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col h-full overflow-hidden">
        
        <!-- Topbar -->
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-8 z-10 shrink-0">
            <h2 class="text-xl font-bold text-gray-800">
                <?php
                    if($page==='products') echo "Quản lý Sản Phẩm";
                    elseif($page==='users') echo "Quản lý Tài Khoản";
                    elseif($page==='categories') echo "Quản lý Danh Mục";
                    elseif($page==='brands') echo "Quản lý Thương Hiệu";
                ?>
            </h2>
            <form method="GET" class="flex bg-gray-100 rounded-lg border border-gray-200 overflow-hidden">
                <input type="hidden" name="p" value="<?= $page ?>">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm..." class="px-4 py-2 bg-transparent focus:outline-none text-sm w-64">
                <button type="submit" class="px-4 text-gray-500 hover:text-blue-600"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </header>

        <!-- NỘI DUNG BẢNG -->
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                
                <!-- BẢNG SẢN PHẨM -->
                <?php if ($page === 'products'): ?>
                    <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <span class="text-gray-600 font-medium">Tổng cộng: <?= count($items) ?> sản phẩm</span>
                        <button onclick="openProductModal()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition text-sm font-bold flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Thêm Sản Phẩm
                        </button>
                    </div>
                    <table class="w-full text-left border-collapse text-sm">
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
                                <td class="p-4"><img src="<?= $item['image'] ?>" class="w-12 h-12 object-contain border border-gray-200 rounded p-1 bg-white"></td>
                                <td class="p-4 font-medium text-gray-800"><?= htmlspecialchars($item['name']) ?></td>
                                <td class="p-4">
                                    <div class="text-gray-800 font-medium"><?= $item['brand_name'] ?? 'Không rõ' ?></div>
                                    <div class="text-gray-400 text-xs"><?= $item['cat_name'] ?? 'Không rõ' ?></div>
                                </td>
                                <td class="p-4 font-bold text-red-600"><?= number_format($item['price']) ?>đ</td>
                                <td class="p-4 text-center">
                                    <button onclick="editProduct(<?= htmlspecialchars(json_encode($item)) ?>)" class="text-blue-500 hover:bg-blue-50 w-8 h-8 rounded-full transition" title="Sửa"><i class="fa-solid fa-pen"></i></button>
                                    <form method="POST" class="inline-block" onsubmit="return confirmDelete(event)">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="text-red-500 hover:bg-red-50 w-8 h-8 rounded-full transition" title="Xóa"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($items)): ?><tr><td colspan="6" class="p-8 text-center text-gray-500">Không có dữ liệu</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                
                <!-- BẢNG TÀI KHOẢN -->
                <?php elseif ($page === 'users'): ?>
                    <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <span class="text-gray-600 font-medium">Tổng cộng: <?= count($items) ?> tài khoản</span>
                        <button onclick="openUserModal()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition text-sm font-bold flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Thêm Tài Khoản
                        </button>
                    </div>
                    <table class="w-full text-left border-collapse text-sm">
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
                                        if($item['role'] == 'admin') echo '<span class="bg-red-100 text-red-600 px-2 py-1 rounded text-xs font-bold">Admin</span>';
                                        elseif($item['role'] == 'manager') echo '<span class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs font-bold">Quản lý</span>';
                                        else echo '<span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-bold">Khách hàng</span>';
                                    ?>
                                </td>
                                <td class="p-4 text-center">
                                    <button onclick="editUser(<?= htmlspecialchars(json_encode($item)) ?>)" class="text-blue-500 hover:bg-blue-50 w-8 h-8 rounded-full transition"><i class="fa-solid fa-pen"></i></button>
                                    <?php if($item['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="inline-block" onsubmit="return confirmDelete(event)">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="text-red-500 hover:bg-red-50 w-8 h-8 rounded-full transition"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                <!-- BẢNG DANH MỤC -->
                <?php elseif ($page === 'categories'): ?>
                    <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <span class="text-gray-600 font-medium">Tổng cộng: <?= count($items) ?> danh mục</span>
                        <button onclick="openCategoryModal()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition text-sm font-bold flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Thêm Danh Mục
                        </button>
                    </div>
                    <table class="w-full text-left border-collapse text-sm">
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
                                <td class="p-4 text-center text-xl text-blue-600"><i class="fa-solid <?= htmlspecialchars($item['icon']) ?>"></i></td>
                                <td class="p-4 font-bold text-gray-800"><?= htmlspecialchars($item['name']) ?></td>
                                <td class="p-4 text-center">
                                    <button onclick="editCategory(<?= htmlspecialchars(json_encode($item)) ?>)" class="text-blue-500 hover:bg-blue-50 w-8 h-8 rounded-full transition"><i class="fa-solid fa-pen"></i></button>
                                    <form method="POST" class="inline-block" onsubmit="return confirmDelete(event)">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="text-red-500 hover:bg-red-50 w-8 h-8 rounded-full transition"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                <!-- BẢNG THƯƠNG HIỆU -->
                <?php elseif ($page === 'brands'): ?>
                    <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <span class="text-gray-600 font-medium">Tổng cộng: <?= count($items) ?> thương hiệu</span>
                        <button onclick="openBrandModal()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition text-sm font-bold flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Thêm Hãng
                        </button>
                    </div>
                    <table class="w-full text-left border-collapse text-sm">
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
                                <td class="p-4 font-bold text-gray-800 uppercase tracking-wide"><?= htmlspecialchars($item['name']) ?></td>
                                <td class="p-4 text-center">
                                    <button onclick="editBrand(<?= htmlspecialchars(json_encode($item)) ?>)" class="text-blue-500 hover:bg-blue-50 w-8 h-8 rounded-full transition"><i class="fa-solid fa-pen"></i></button>
                                    <form method="POST" class="inline-block" onsubmit="return confirmDelete(event)">
                                        <input type="hidden" name="action" value="delete_brand">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="text-red-500 hover:bg-red-50 w-8 h-8 rounded-full transition"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <!-- ============================================== -->
    <!-- MODALS KHU VỰC DÀNH CHO FORM THÊM / SỬA        -->
    <!-- ============================================== -->

    <!-- MODAL SẢN PHẨM -->
    <div id="productModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto relative">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10">
                <h3 class="text-lg font-bold text-gray-800" id="productModalTitle">Thêm Sản Phẩm Mới</h3>
                <button type="button" onclick="closeModal('productModal')" class="text-gray-400 hover:text-red-500 text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="p-6">
                <input type="hidden" name="action" id="prod_action" value="add_product">
                <input type="hidden" name="id" id="prod_id" value="">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tên sản phẩm *</label>
                        <input type="text" name="name" id="prod_name" required class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Danh mục *</label>
                        <select name="category_id" id="prod_category" required class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                            <?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= $c['name'] ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Thương hiệu *</label>
                        <select name="brand_id" id="prod_brand" required class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                            <?php foreach($brands as $b): ?><option value="<?= $b['id'] ?>"><?= $b['name'] ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Giá bán (VNĐ) *</label>
                        <input type="number" name="price" id="prod_price" required class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Giá gốc (Bỏ trống nếu không giảm)</label>
                        <input type="number" name="old_price" id="prod_old_price" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tải ảnh từ máy tính</label>
                        <input type="file" name="image_upload" accept="image/*" class="w-full px-3 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none text-sm bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hoặc dùng URL Ảnh</label>
                        <input type="text" name="image" id="prod_image" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none" placeholder="https://...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quà Tặng (Cách nhau bằng dấu ; )</label>
                        <input type="text" name="gift_text" id="prod_gift" class="w-full px-3 py-2 border border-gray-300 rounded outline-none" placeholder="Tặng ABC; Giảm XYZ...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nhãn dán (Cách nhau bằng dấu phẩy)</label>
                        <input type="text" name="tags" id="prod_tags" class="w-full px-3 py-2 border border-gray-300 rounded outline-none" placeholder="Trả góp 0%, Mới 2024">
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
                    <button type="button" onclick="closeModal('productModal')" class="px-5 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded font-medium transition">Hủy</button>
                    <button type="submit" onclick="tinymce.triggerSave();" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold transition shadow-md">Lưu Sản Phẩm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL TÀI KHOẢN -->
    <div id="userModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg relative overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800" id="userModalTitle">Thêm Tài Khoản</h3>
                <button type="button" onclick="closeModal('userModal')" class="text-gray-400 hover:text-red-500 text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" id="usr_action" value="add_user">
                <input type="hidden" name="id" id="usr_id" value="">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Họ và tên *</label>
                        <input type="text" name="fullname" id="usr_fullname" required class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Số điện thoại *</label>
                        <input type="text" name="phone" id="usr_phone" required class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tên đăng nhập (Username) *</label>
                        <input type="text" name="username" id="usr_username" required class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" id="lbl_usr_password">Mật khẩu *</label>
                        <input type="password" name="password" id="usr_password" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                        <p class="text-xs text-gray-500 mt-1 hidden" id="hint_usr_password">Bỏ trống nếu không muốn đổi mật khẩu mới.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cấp quyền *</label>
                        <select name="role" id="usr_role" required class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="customer">Khách hàng (Customer)</option>
                            <option value="manager">Quản lý (Manager)</option>
                            <option value="admin">Quản trị viên (Admin)</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeModal('userModal')" class="px-5 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded font-medium transition">Hủy</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold transition shadow-md">Lưu Tài Khoản</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DANH MỤC -->
    <div id="categoryModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md relative overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800" id="categoryModalTitle">Thêm Danh Mục</h3>
                <button type="button" onclick="closeModal('categoryModal')" class="text-gray-400 hover:text-red-500 text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" id="cat_action" value="add_category">
                <input type="hidden" name="id" id="cat_id" value="">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tên danh mục *</label>
                        <input type="text" name="name" id="cat_name" required class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none" placeholder="VD: Lò vi sóng">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Icon FontAwesome (Tùy chọn)</label>
                        <input type="text" name="icon" id="cat_icon" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none" placeholder="fa-tv, fa-box...">
                        <p class="text-xs text-gray-500 mt-1">Xem icon tại <a href="https://fontawesome.com/icons" target="_blank" class="text-blue-500 hover:underline">fontawesome.com</a></p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeModal('categoryModal')" class="px-5 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded font-medium transition">Hủy</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold transition shadow-md">Lưu Danh Mục</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL THƯƠNG HIỆU -->
    <div id="brandModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm relative overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800" id="brandModalTitle">Thêm Thương Hiệu</h3>
                <button type="button" onclick="closeModal('brandModal')" class="text-gray-400 hover:text-red-500 text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" id="brand_action" value="add_brand">
                <input type="hidden" name="id" id="brand_id" value="">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tên Hãng (Brand) *</label>
                    <input type="text" name="name" id="brand_name" required class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 outline-none" placeholder="VD: Apple, LG...">
                </div>
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeModal('brandModal')" class="px-5 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 rounded font-medium transition">Hủy</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold transition shadow-md">Lưu Hãng</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPTS JS -->
    <script>
        <?php if($msg): ?>
            Swal.fire({
                icon: '<?= $msg_type ?>',
                title: '<?= $msg_type === "success" ? "Thành công!" : "Lỗi!" ?>',
                text: '<?= $msg ?>',
                timer: 2000,
                showConfirmButton: false
            });
        <?php endif; ?>

        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

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
            if(tinymce.get('prod_desc')) tinymce.get('prod_desc').setContent('');
            if(tinymce.get('prod_specs')) tinymce.get('prod_specs').setContent('');
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
            if(tinymce.get('prod_desc')) tinymce.get('prod_desc').setContent(product.description || '');
            if(tinymce.get('prod_specs')) tinymce.get('prod_specs').setContent(product.specifications || '');
            document.getElementById('productModal').classList.remove('hidden');
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
    </script>
</body>
</html>