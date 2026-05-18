<?php

namespace App\Service;

use PDO;

class AdminService
{
    private PDO $db;
    private \App\Repository\UserRepository $userRepo;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->userRepo = new \App\Repository\UserRepository($db);
    }

    /**
     * XỬ LÝ CÁC ACTION POST (THU THẬP TỪ DỮ LIỆU CÁC FORM)
     */
    public function handlePostAction(array $post, array $files, string $userRole, int $userId): array
    {
        $msg = '';
        $msg_type = '';
        $action = $post['action'] ?? '';

        // --- XỬ LÝ ĐƠN HÀNG ---
        if ($action === 'update_order_status') {
            $id = $post['id'];
            $status = $post['status'];
            $this->db->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status, $id]);

            // Thông báo web
            $stmtOrder = $this->db->prepare("SELECT user_id FROM orders WHERE id = ?");
            $stmtOrder->execute([$id]);
            $order = $stmtOrder->fetch();
            if ($order) {
                $statusText = ['pending' => 'chờ xử lý', 'processing' => 'đã được xác nhận', 'delivering' => 'đang giao', 'completed' => 'đã hoàn thành', 'cancelled' => 'đã hủy'][$status] ?? $status;
                $this->createNotification((int) $order['user_id'], "Đơn hàng #$id", "Đơn hàng của bạn $statusText.", 'order');
            }

            $msg = "Cập nhật trạng thái đơn hàng thành công!";
            $msg_type = 'success';
        } elseif ($action === 'delete_order' && $userRole === 'admin') {
            $id = $post['id'];
            $this->db->prepare("DELETE FROM order_details WHERE order_id=?")->execute([$id]);
            $this->db->prepare("DELETE FROM orders WHERE id=?")->execute([$id]);
            $msg = "Đã xóa đơn hàng và chi tiết!";
            $msg_type = 'success';
        }

        // --- XỬ LÝ YÊU CẦU BẢO HÀNH ---
        elseif ($action === 'update_warranty_status') {
            $id = $post['id'];
            $status = $post['status'];
            $admin_note = isset($post['admin_note']) ? trim($post['admin_note']) : '';
            $this->db->prepare("UPDATE warranties SET status=?, admin_note=? WHERE id=?")->execute([$status, $admin_note, $id]);

            $wData = $this->db->prepare("SELECT w.*, u.fullname, u.email, p.name as product_name FROM warranties w JOIN users u ON w.user_id = u.id JOIN products p ON w.product_id = p.id WHERE w.id = ?");
            $wData->execute([$id]);
            $wInfo = $wData->fetch(PDO::FETCH_ASSOC);
            if ($wInfo) {
                if (!empty($wInfo['email'])) {
                    sendWarrantyStatusEmail($wInfo['email'], $wInfo['fullname'], $id, $wInfo['product_name'], $status, $admin_note);
                }
                $st = ['pending' => 'chờ duyệt', 'processing' => 'đang xử lý', 'completed' => 'đã xong', 'rejected' => 'bị từ chối'][$status] ?? $status;
                $this->createNotification($wInfo['user_id'], "Bảo hành sản phẩm", "Yêu cầu bảo hành $wInfo[product_name] $st." . ($admin_note ? " Ghi chú: $admin_note" : ""), 'system');
            }
            $msg = "Cập nhật trạng thái bảo hành thành công!";
            $msg_type = 'success';
        }

        // --- XỬ LÝ YÊU CẦU TRẢ HÀNG ---
        elseif ($action === 'update_return_status') {
            $id = $post['id'];
            $status = $post['status'];
            $admin_note = isset($post['admin_note']) ? trim($post['admin_note']) : '';
            $this->db->prepare("UPDATE returns SET status=?, admin_note=? WHERE id=?")->execute([$status, $admin_note, $id]);

            $rData = $this->db->prepare("SELECT r.*, u.fullname, u.email FROM returns r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
            $rData->execute([$id]);
            $rInfo = $rData->fetch(PDO::FETCH_ASSOC);
            if ($rInfo) {
                if (!empty($rInfo['email'])) {
                    sendReturnStatusEmail($rInfo['email'], $rInfo['fullname'], $id, $rInfo['order_id'], $status, $admin_note);
                }
                $st = ['pending' => 'chờ duyệt', 'processing' => 'đang xử lý', 'completed' => 'xong', 'rejected' => 'từ chối'][$status] ?? $status;
                $this->createNotification($rInfo['user_id'], "Đổi trả đơn hàng", "Yêu cầu đổi trả ĐH #$rInfo[order_id] $st." . ($admin_note ? " Ghi chú: $admin_note" : ""), 'system');
            }
            $msg = "Cập nhật trạng thái trả hàng thành công!";
            $msg_type = 'success';
        }

        // --- XỬ LÝ SẢN PHẨM ---
        elseif ($action === 'add_product' || $action === 'edit_product') {
            $id = $post['id'] ?? null;
            $name = trim($post['name'] ?? '');
            $category_id = $post['category_id'] ?? null;
            $brand_id = $post['brand_id'] ?? null;
            $price = $post['price'] ?? 0;
            $old_price = !empty($post['old_price']) ? $post['old_price'] : null;
            $gift_text = $post['gift_text'] ?? '';
            $tags = $post['tags'] ?? '';
            $description = $post['description'] ?? '';
            $specifications = $post['specifications'] ?? '';
            $image = trim($post['image'] ?? '');

            // 1. Validate dữ liệu cơ bản
            if (empty($name)) {
                return ['msg' => 'Tên sản phẩm không được bỏ trống!', 'msg_type' => 'error'];
            }
            if (empty($category_id) || empty($brand_id)) {
                return ['msg' => 'Danh mục và Thương hiệu là bắt buộc!', 'msg_type' => 'error'];
            }
            if ($price < 0) {
                return ['msg' => 'Giá bán sản phẩm không được là số âm!', 'msg_type' => 'error'];
            }

            // 2. Xử lý upload ảnh chính
            if (isset($files['image_upload']) && $files['image_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($files['image_upload']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/';
                    if (!file_exists($upload_dir))
                        mkdir($upload_dir, 0777, true);

                    $file_name = time() . '_' . basename($files['image_upload']['name']);
                    $target_file = $upload_dir . $file_name;
                    if (move_uploaded_file($files['image_upload']['tmp_name'], $target_file)) {
                        $image = $target_file;
                    } else {
                        return ['msg' => 'Không thể lưu file ảnh đại diện tải lên. Vui lòng kiểm tra quyền thư mục máy chủ!', 'msg_type' => 'error'];
                    }
                } else {
                    $upload_errors = [
                        UPLOAD_ERR_INI_SIZE => 'Ảnh đại diện tải lên vượt quá dung lượng cho phép của máy chủ (upload_max_filesize).',
                        UPLOAD_ERR_FORM_SIZE => 'Ảnh đại diện tải lên vượt quá dung lượng cho phép của form.',
                        UPLOAD_ERR_PARTIAL => 'Ảnh đại diện chỉ được tải lên một phần.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Máy chủ thiếu thư mục tạm để tải ảnh.',
                        UPLOAD_ERR_CANT_WRITE => 'Không thể ghi ảnh đại diện vào đĩa máy chủ.',
                        UPLOAD_ERR_EXTENSION => 'Một extension PHP đã chặn việc tải ảnh đại diện.'
                    ];
                    $err_msg = $upload_errors[$files['image_upload']['error']] ?? 'Lỗi upload ảnh đại diện không xác định.';
                    return ['msg' => $err_msg, 'msg_type' => 'error'];
                }
            }

            // Đối với sản phẩm mới, ảnh đại diện là bắt buộc
            if ($action === 'add_product' && empty($image)) {
                return ['msg' => 'Vui lòng chọn ảnh tải lên từ máy tính hoặc nhập URL ảnh chính cho sản phẩm mới!', 'msg_type' => 'error'];
            }

            // 3. Xử lý upload nhiều ảnh phụ (more_images)
            $more_images_arr = [];

            // Lấy từ URL ảnh phụ (nếu có nhập)
            if (!empty($post['more_images_urls'])) {
                $urls = explode("\n", str_replace("\r", "", $post['more_images_urls']));
                foreach ($urls as $u) {
                    $u = trim($u);
                    if ($u)
                        $more_images_arr[] = $u;
                }
            }

            // Xử lý upload file hàng loạt
            if (isset($files['more_images_upload'])) {
                $upload_dir = 'uploads/';
                if (!file_exists($upload_dir))
                    mkdir($upload_dir, 0777, true);

                foreach ($files['more_images_upload']['tmp_name'] as $key => $tmp_name) {
                    if ($files['more_images_upload']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                        if ($files['more_images_upload']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_name = time() . '_more_' . $key . '_' . basename($files['more_images_upload']['name'][$key]);
                            $target_file = $upload_dir . $file_name;
                            if (move_uploaded_file($tmp_name, $target_file)) {
                                $more_images_arr[] = $target_file;
                            } else {
                                return ['msg' => 'Không thể lưu file ảnh phụ tải lên. Vui lòng kiểm tra quyền thư mục máy chủ!', 'msg_type' => 'error'];
                            }
                        } else {
                            return ['msg' => 'Lỗi xảy ra khi tải lên một số ảnh phụ!', 'msg_type' => 'error'];
                        }
                    }
                }
            }
            $more_images_json = !empty($more_images_arr) ? json_encode($more_images_arr, JSON_UNESCAPED_SLASHES) : null;

            // 4. Lưu thông tin vào Cơ sở dữ liệu
            try {
                if ($action === 'add_product') {
                    $stmt = $this->db->prepare("INSERT INTO products (name, category_id, brand_id, price, old_price, image, more_images, gift_text, tags, description, specifications) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $category_id, $brand_id, $price, $old_price, $image, $more_images_json, $gift_text, $tags, $description, $specifications]);
                    $msg = "Thêm sản phẩm mới thành công!";
                } else {
                    $stmt = $this->db->prepare("UPDATE products SET name=?, category_id=?, brand_id=?, price=?, old_price=?, image=?, more_images=?, gift_text=?, tags=?, description=?, specifications=? WHERE id=?");
                    $stmt->execute([$name, $category_id, $brand_id, $price, $old_price, $image, $more_images_json, $gift_text, $tags, $description, $specifications, $id]);
                    $msg = "Cập nhật sản phẩm thành công!";
                }
                $msg_type = 'success';
            } catch (\PDOException $e) {
                $msg = "Lỗi CSDL khi lưu sản phẩm: " . $e->getMessage();
                $msg_type = 'error';
            }
        } elseif ($action === 'delete_product') {
            $id = $post['id'];
            try {
                $this->db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
                $msg = "Đã xóa sản phẩm thành công!";
                $msg_type = 'success';
            } catch (\PDOException $e) {
                $msg = "Không thể xóa sản phẩm do ràng buộc dữ liệu: " . $e->getMessage();
                $msg_type = 'error';
            }
        }

        // --- XỬ LÝ TÀI KHOẢN NGƯỜI DÙNG ---
        if ($userRole === 'admin') {
            if ($action === 'add_user' || $action === 'edit_user') {
                $id = $post['id'] ?? null;
                $fullname = $post['fullname'];
                $phone = $post['phone'];
                $email = $post['email'] ?? null;
                $address = $post['address'] ?? null;
                $username = $post['username'];
                $password = $post['password'];
                $role = $post['role'];

                $check = $this->db->prepare("SELECT id FROM users WHERE (username = ? OR phone = ?) AND id != ?");
                $check->execute([$username, $phone, $id ?? 0]);
                if ($check->fetch()) {
                    $msg = "Tên đăng nhập hoặc Số điện thoại đã tồn tại!";
                    $msg_type = 'error';
                } elseif (!empty($password) && (strlen($password) < 8 || !preg_match('/[a-zA-Z]/', $password))) {
                    $msg = 'Mật khẩu phải có ít nhất 8 ký tự và chứa ít nhất 1 chữ cái!';
                    $msg_type = 'error';
                } else {
                    if ($action === 'add_user') {
                        if (empty($password)) {
                            $msg = 'Vui lòng nhập mật khẩu!';
                            $msg_type = 'error';
                        } else {
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $this->db->prepare("INSERT INTO users (fullname, phone, email, address, username, password, role, auth_provider) VALUES (?, ?, ?, ?, ?, ?, ?, 'local')");
                            $stmt->execute([$fullname, $phone, $email, $address, $username, $hashedPassword, $role]);
                            $msg = "Thêm tài khoản thành công!";
                            $msg_type = 'success';
                        }
                    } else {
                        if (!empty($password)) {
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $this->db->prepare("UPDATE users SET fullname=?, phone=?, email=?, address=?, username=?, password=?, role=?, auth_provider='local' WHERE id=?");
                            $stmt->execute([$fullname, $phone, $email, $address, $username, $hashedPassword, $role, $id]);
                        } else {
                            $stmt = $this->db->prepare("UPDATE users SET fullname=?, phone=?, email=?, address=?, username=?, role=?, auth_provider='local' WHERE id=?");
                            $stmt->execute([$fullname, $phone, $email, $address, $username, $role, $id]);
                        }
                        $msg = "Cập nhật tài khoản thành công!";
                        $msg_type = 'success';
                    }
                }
            } elseif ($action === 'toggle_user_lock') {
                $id = (int) $post['id'];
                $status = (int) $post['status'];
                $this->db->prepare("UPDATE users SET is_banned = ? WHERE id = ?")->execute([$status, $id]);
                $msg = $status ? "Tài khoản đã bị khóa tạm thời." : "Đã mở khóa tài khoản.";
                $msg_type = 'success';
            } elseif ($action === 'delete_user') {
                $id = $post['id'];
                if ($id != $userId) {
                    $this->db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
                    $msg = "Đã xóa tài khoản!";
                    $msg_type = 'success';
                } else {
                    $msg = "Không thể tự xóa tài khoản của chính mình!";
                    $msg_type = 'error';
                }
            }

            // --- XỬ LÝ DANH MỤC ---
            elseif ($action === 'add_category' || $action === 'edit_category') {
                $id = $post['id'] ?? null;
                $name = $post['name'];
                $icon = $post['icon'] ?? 'fa-box';

                if ($action === 'add_category') {
                    $this->db->prepare("INSERT INTO categories (name, icon) VALUES (?, ?)")->execute([$name, $icon]);
                    $msg = "Thêm danh mục thành công!";
                } else {
                    $this->db->prepare("UPDATE categories SET name=?, icon=? WHERE id=?")->execute([$name, $icon, $id]);
                    $msg = "Cập nhật danh mục thành công!";
                }
                $msg_type = 'success';
            } elseif ($action === 'delete_category') {
                $this->db->prepare("DELETE FROM categories WHERE id=?")->execute([$post['id']]);
                $msg = "Đã xóa danh mục!";
                $msg_type = 'success';
            }

            // --- XỬ LÝ THƯƠNG HIỆU ---
            elseif ($action === 'add_brand' || $action === 'edit_brand') {
                $id = $post['id'] ?? null;
                $name = $post['name'];

                if ($action === 'add_brand') {
                    $this->db->prepare("INSERT INTO brands (name) VALUES (?)")->execute([$name]);
                    $msg = "Thêm hãng thành công!";
                } else {
                    $this->db->prepare("UPDATE brands SET name=? WHERE id=?")->execute([$name, $id]);
                    $msg = "Cập nhật hãng thành công!";
                }
                $msg_type = 'success';
            } elseif ($action === 'delete_brand') {
                $this->db->prepare("DELETE FROM brands WHERE id=?")->execute([$post['id']]);
                $msg = "Đã xóa thương hiệu!";
                $msg_type = 'success';
            }

            // --- XỬ LÝ VOUCHER ---
            elseif ($action === 'add_voucher' || $action === 'edit_voucher') {
                $id = $post['id'] ?? null;
                $code = strtoupper($post['code']);
                $discount_amount = $post['discount_amount'];
                $discount_type = $post['discount_type'];
                $usage_limit = $post['usage_limit'];

                if ($action === 'add_voucher') {
                    $this->db->prepare("INSERT INTO vouchers (code, discount_amount, discount_type, usage_limit) VALUES (?, ?, ?, ?)")->execute([$code, $discount_amount, $discount_type, $usage_limit]);
                    $msg = "Thêm mã giảm giá thành công!";
                } else {
                    $this->db->prepare("UPDATE vouchers SET code=?, discount_amount=?, discount_type=?, usage_limit=? WHERE id=?")->execute([$code, $discount_amount, $discount_type, $usage_limit, $id]);
                    $msg = "Cập nhật mã giảm giá thành công!";
                }
                $msg_type = 'success';
            } elseif ($action === 'delete_voucher') {
                $this->db->prepare("DELETE FROM vouchers WHERE id=?")->execute([$post['id']]);
                $msg = "Đã xóa mã giảm giá!";
                $msg_type = 'success';
            }

            // --- XỬ LÝ BANNER ---
            elseif ($action === 'update_banner') {
                $banner_fields = ['banner_badge', 'banner_title1', 'banner_title2', 'banner_subtitle', 'banner_image', 'banner_link', 'banner_product_1', 'banner_product_2', 'banner_product_3', 'banner_product_4'];
                foreach ($banner_fields as $field) {
                    if (isset($post[$field])) {
                        updateSiteSetting($this->db, $field, trim($post[$field]));
                    }
                }
                $msg = "Cập nhật banner trang chủ thành công!";
                $msg_type = 'success';
            }
        }

        return ['msg' => $msg, 'msg_type' => $msg_type];
    }

    /**
     * TRUY VẤN DỮ LIỆU ĐỂ HIỂN THỊ THEO TRANG VÀ LỌC 
     */
    public function getPageData(string $page, array $getParams, string $userRole): array
    {
        // Chúng ta vẫn cần gọi getAllCategories/Brands. Nếu không muốn inject repo, 
        // ta gọi hàm toàn cục getAllCategories() được include từ database.php
        $categories = getAllCategories($this->db);
        $brands = getAllBrands($this->db);
        $search = $getParams['q'] ?? '';
        $items = [];
        $status_filter = null;
        $status_counts = null;
        $total_orders = null;
        $site_settings = null;

        if ($page === 'orders') {
            $status_filter = $getParams['status'] ?? 'all';
            $sql = "SELECT * FROM orders";
            $conditions = [];
            $params = [];

            if ($search) {
                $conditions[] = "(fullname LIKE ? OR phone LIKE ? OR id = ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = $search;
            }
            if ($status_filter !== 'all') {
                $conditions[] = "status = ?";
                $params[] = $status_filter;
            }

            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }
            $sql .= " ORDER BY id DESC";

            $stmtItems = $this->db->prepare($sql);
            $stmtItems->execute($params);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            // Nạp chi tiết các sản phẩm
            $all_details = $this->db->query("SELECT od.*, p.name, p.image FROM order_details od JOIN products p ON od.product_id = p.id")->fetchAll(PDO::FETCH_ASSOC);
            $details_by_order = [];
            foreach ($all_details as $d) {
                $details_by_order[$d['order_id']][] = $d;
            }
            foreach ($items as &$item) {
                $item['details'] = $details_by_order[$item['id']] ?? [];
            }

            $stmtCount = $this->db->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
            $status_counts = ['pending' => 0, 'processing' => 0, 'delivering' => 0, 'completed' => 0, 'cancelled' => 0];
            $total_orders = 0;
            while ($row = $stmtCount->fetch(PDO::FETCH_ASSOC)) {
                $status_counts[$row['status']] = $row['count'];
                $total_orders += $row['count'];
            }

        } elseif ($page === 'products') {
            $sql = "SELECT p.*, c.name as cat_name, b.name as brand_name FROM products p LEFT JOIN categories c ON p.category_id=c.id LEFT JOIN brands b ON p.brand_id=b.id";
            $params = [];
            if ($search) {
                $sql .= " WHERE p.name LIKE ?";
                $params[] = "%$search%";
            }
            $sql .= " ORDER BY p.id DESC";
            $stmtProducts = $this->db->prepare($sql);
            $stmtProducts->execute($params);
            $items = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($page === 'users' && $userRole === 'admin') {
            $items = $this->userRepo->findAll($search);

        } elseif ($page === 'login_history' && $userRole === 'admin') {
            $page_num = isset($getParams['page']) && (int) $getParams['page'] > 0 ? (int) $getParams['page'] : 1;
            $limit = 20;
            $offset = ($page_num - 1) * $limit;

            $whereSql = "";
            $params = [];
            if ($search) {
                $whereSql = " WHERE u.username LIKE ? OR u.fullname LIKE ? OR DATE(lh.login_time) = ?";
                $params = ["%$search%", "%$search%", $search];
            }

            $countSql = "SELECT COUNT(*) FROM login_history lh JOIN users u ON lh.user_id = u.id" . $whereSql;
            $stmtCount = $this->db->prepare($countSql);
            $stmtCount->execute($params);
            $total_records = (int) $stmtCount->fetchColumn();

            $sql = "SELECT lh.*, u.username, u.fullname, u.is_banned FROM login_history lh JOIN users u ON lh.user_id = u.id" . $whereSql . " ORDER BY lh.login_time DESC LIMIT $limit OFFSET $offset";
            $stmtItems = $this->db->prepare($sql);
            $stmtItems->execute($params);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            $pagination = [
                'current_page' => $page_num,
                'limit' => $limit,
                'total_records' => $total_records,
                'total_pages' => max(1, ceil($total_records / $limit))
            ];


        } elseif ($page === 'categories' && $userRole === 'admin') {
            $sql = "SELECT * FROM categories";
            $params = [];
            if ($search) {
                $sql .= " WHERE name LIKE ?";
                $params[] = "%$search%";
            }
            $stmtCat = $this->db->prepare($sql);
            $stmtCat->execute($params);
            $items = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($page === 'brands' && $userRole === 'admin') {
            $sql = "SELECT * FROM brands";
            $params = [];
            if ($search) {
                $sql .= " WHERE name LIKE ?";
                $params[] = "%$search%";
            }
            $stmtBrand = $this->db->prepare($sql);
            $stmtBrand->execute($params);
            $items = $stmtBrand->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($page === 'vouchers' && $userRole === 'admin') {
            $sql = "SELECT * FROM vouchers";
            $params = [];
            if ($search) {
                $sql .= " WHERE code LIKE ?";
                $params[] = "%$search%";
            }
            $stmtVoucher = $this->db->prepare($sql);
            $stmtVoucher->execute($params);
            $items = $stmtVoucher->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($page === 'homepage') {
            $site_settings = getSiteSettings($this->db);
            $products_list = $this->db->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($page === 'warranties') {
            $items = getAllWarranties($this->db);

        } elseif ($page === 'returns') {
            $items = getAllReturns($this->db);
        } elseif ($page === 'dashboard') {
            return $this->getDashboardData();
        } elseif ($page === 'revenue') {
            return $this->getRevenueHistory($getParams);
        }

        // Tự động phân trang cho tất cả các tab (nếu chưa được phân trang bằng SQL như login_history)
        if ($page !== 'homepage' && !isset($pagination)) {
            $page_num = isset($getParams['page']) && (int) $getParams['page'] > 0 ? (int) $getParams['page'] : 1;
            $limit = 10; // Mặc định 10 dòng/trang cho các tab khác
            $total_records = count($items);
            $total_pages = max(1, ceil($total_records / $limit));
            $offset = ($page_num - 1) * $limit;

            $items = array_slice($items, $offset, $limit);

            $pagination = [
                'current_page' => $page_num,
                'limit' => $limit,
                'total_records' => $total_records,
                'total_pages' => $total_pages
            ];
        }

        return [
            'categories' => $categories,
            'brands' => $brands,
            'search' => $search,
            'items' => $items,
            'status_filter' => $status_filter,
            'status_counts' => $status_counts,
            'total_orders' => $total_orders,
            'site_settings' => $site_settings,
            'products_list' => $products_list ?? [],
            'pagination' => $pagination ?? null
        ];
    }

    private function createNotification(int $userId, string $title, string $message, string $type = 'system'): void
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$userId, $title, $message, $type]);

            // Gửi FCM Push Notification cho thiết bị di động
            $stmtUser = $this->db->prepare("SELECT fcm_token FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch();
            if ($user && !empty($user['fcm_token'])) {
                $this->sendPushNotification($user['fcm_token'], $title, $message);
            }
        } catch (\Exception $e) {
        }
    }

    private function sendPushNotification(string $fcmToken, string $title, string $message): void
    {
        try {
            $logPath = __DIR__ . '/../../scratch/fcm_push_logs.txt';
            if (!file_exists(dirname($logPath))) {
                mkdir(dirname($logPath), 0777, true);
            }
            $logMessage = "[" . date('Y-m-d H:i:s') . "] FCM PUSH TO: $fcmToken\nTITLE: $title\nBODY: $message\n---------------------------------\n";
            file_put_contents($logPath, $logMessage, FILE_APPEND);

            // Đoạn mã chuẩn bị cho Production gửi lên Google FCM v1 API
            /*
            $url = 'https://fcm.googleapis.com/v1/projects/YOUR_PROJECT_ID/messages:send';
            $headers = [
                'Authorization: Bearer ' . $this->getGoogleAccessToken(),
                'Content-Type: application/json'
            ];
            $payload = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $message
                    ]
                ]
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $result = curl_exec($ch);
            curl_close($ch);
            */
        } catch (\Exception $e) {
        }
    }

    /**
     * LẤY DỮ LIỆU TỔNG QUAN (DASHBOARD)
     */
    public function getDashboardData(): array
    {
        $stats = [
            'total_revenue' => $this->db->query("SELECT SUM(total_price) FROM orders WHERE status = 'completed'")->fetchColumn() ?: 0,
            'total_orders' => $this->db->query("SELECT COUNT(*) FROM orders")->fetchColumn() ?: 0,
            'total_users' => $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn() ?: 0,
            'total_products' => $this->db->query("SELECT COUNT(*) FROM products")->fetchColumn() ?: 0,
        ];

        // Doanh thu theo ngày (7 ngày gần nhất)
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $stmtRev = $this->db->prepare("SELECT SUM(total_price) FROM orders WHERE status = 'completed' AND DATE(created_at) = ?");
            $stmtRev->execute([$date]);
            $revenue = $stmtRev->fetchColumn() ?: 0;
            $chartData[] = [
                'date' => date('d/m', strtotime($date)),
                'revenue' => (int) $revenue
            ];
        }

        return [
            'stats' => $stats,
            'chartData' => $chartData
        ];
    }

    /**
     * LẤY LỊCH SỬ THU NHẬP
     */
    public function getRevenueHistory(array $getParams = []): array
    {
        $conditions = ["status = 'completed'"];
        $params = [];

        $month = isset($getParams['month']) && (int) $getParams['month'] >= 1 && (int) $getParams['month'] <= 12 ? (int) $getParams['month'] : null;
        $startDate = !empty($getParams['start_date']) ? $getParams['start_date'] : null;
        $endDate = !empty($getParams['end_date']) ? $getParams['end_date'] : null;

        if ($month) {
            $conditions[] = "MONTH(completed_at) = ? AND YEAR(completed_at) = YEAR(NOW())";
            $params[] = $month;
        } elseif ($startDate && $endDate) {
            $conditions[] = "DATE(completed_at) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $whereClause = "WHERE " . implode(" AND ", $conditions);

        // Thống kê theo tháng trong năm nay
        $monthlyRevenue = $this->db->query("
            SELECT 
                MONTH(completed_at) as month, 
                SUM(total_price) as total 
            FROM orders 
            WHERE status = 'completed' AND YEAR(completed_at) = YEAR(NOW())
            GROUP BY MONTH(completed_at)
            ORDER BY month ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Danh sách các giao dịch được lọc (không limit để khi in/xem hiển thị đầy đủ, hoặc có thể giới hạn)
        $sqlTransactions = "
            SELECT id, fullname, total_price, payment_method, completed_at 
            FROM orders 
            $whereClause
            ORDER BY completed_at DESC
        ";

        $stmtTransactions = $this->db->prepare($sqlTransactions);
        $stmtTransactions->execute($params);
        $recentTransactions = $stmtTransactions->fetchAll(PDO::FETCH_ASSOC);

        return [
            'monthly' => $monthlyRevenue,
            'transactions' => $recentTransactions,
            'filtered_month' => $month,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
    }
}
