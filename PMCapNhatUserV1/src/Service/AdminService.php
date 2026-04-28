<?php

namespace App\Service;

use PDO;

class AdminService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * XỬ LÝ CÁC ACTION POST (THU THẬP TỪ DỮ LIỆU CÁC FORM)
     */
    public function handlePostAction(array $post, array $files, string $userRole, int $userId): array {
        $msg = '';
        $msg_type = '';
        $action = $post['action'] ?? '';

        // --- XỬ LÝ ĐƠN HÀNG ---
        if ($action === 'update_order_status') {
            $id = $post['id'];
            $status = $post['status'];
            $this->db->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status, $id]);
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
            if ($wInfo && !empty($wInfo['email'])) {
                sendWarrantyStatusEmail($wInfo['email'], $wInfo['fullname'], $id, $wInfo['product_name'], $status, $admin_note);
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
            if ($rInfo && !empty($rInfo['email'])) {
                sendReturnStatusEmail($rInfo['email'], $rInfo['fullname'], $id, $rInfo['order_id'], $status, $admin_note);
            }
            $msg = "Cập nhật trạng thái trả hàng thành công!";
            $msg_type = 'success';
        }

        // --- XỬ LÝ SẢN PHẨM ---
        elseif ($action === 'add_product' || $action === 'edit_product') {
            $id = $post['id'] ?? null;
            $name = $post['name'];
            $category_id = $post['category_id'];
            $brand_id = $post['brand_id'];
            $price = $post['price'];
            $old_price = !empty($post['old_price']) ? $post['old_price'] : null;
            $gift_text = $post['gift_text'] ?? '';
            $tags = $post['tags'] ?? '';
            $description = $post['description'] ?? '';
            $specifications = $post['specifications'] ?? '';
            $image = $post['image'];

            if (isset($files['image_upload']) && $files['image_upload']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $file_name = time() . '_' . basename($files['image_upload']['name']);
                $target_file = $upload_dir . $file_name;
                if (move_uploaded_file($files['image_upload']['tmp_name'], $target_file)) {
                    $image = $target_file;
                }
            }

            if ($action === 'add_product') {
                $stmt = $this->db->prepare("INSERT INTO products (name, category_id, brand_id, price, old_price, image, gift_text, tags, description, specifications) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $category_id, $brand_id, $price, $old_price, $image, $gift_text, $tags, $description, $specifications]);
                $msg = "Thêm sản phẩm thành công!";
            } else {
                $stmt = $this->db->prepare("UPDATE products SET name=?, category_id=?, brand_id=?, price=?, old_price=?, image=?, gift_text=?, tags=?, description=?, specifications=? WHERE id=?");
                $stmt->execute([$name, $category_id, $brand_id, $price, $old_price, $image, $gift_text, $tags, $description, $specifications, $id]);
                $msg = "Cập nhật sản phẩm thành công!";
            }
            $msg_type = 'success';
        } elseif ($action === 'delete_product') {
            $id = $post['id'];
            $this->db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
            $msg = "Đã xóa sản phẩm!";
            $msg_type = 'success';
        }

        // --- XỬ LÝ TÀI KHOẢN NGƯỜI DÙNG ---
        if ($userRole === 'admin') {
            if ($action === 'add_user' || $action === 'edit_user') {
                $id = $post['id'] ?? null;
                $fullname = $post['fullname'];
                $phone = $post['phone'];
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
                            $stmt = $this->db->prepare("INSERT INTO users (fullname, phone, username, password, role) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$fullname, $phone, $username, $password, $role]);
                            $msg = "Thêm tài khoản thành công!";
                            $msg_type = 'success';
                        }
                    } else {
                        if (!empty($password)) {
                            $stmt = $this->db->prepare("UPDATE users SET fullname=?, phone=?, username=?, password=?, role=? WHERE id=?");
                            $stmt->execute([$fullname, $phone, $username, $password, $role, $id]);
                        } else {
                            $stmt = $this->db->prepare("UPDATE users SET fullname=?, phone=?, username=?, role=? WHERE id=?");
                            $stmt->execute([$fullname, $phone, $username, $role, $id]);
                        }
                        $msg = "Cập nhật tài khoản thành công!";
                        $msg_type = 'success';
                    }
                }
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
                $banner_fields = ['banner_badge', 'banner_title1', 'banner_title2', 'banner_subtitle', 'banner_image'];
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
    public function getPageData(string $page, array $getParams, string $userRole): array {
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

            if ($search) {
                $conditions[] = "(fullname LIKE '%$search%' OR phone LIKE '%$search%' OR id = '$search')";
            }
            if ($status_filter !== 'all') {
                $conditions[] = "status = '$status_filter'";
            }

            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }
            $sql .= " ORDER BY id DESC";

            $items = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

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
            if ($search) $sql .= " WHERE p.name LIKE '%$search%'";
            $sql .= " ORDER BY p.id DESC";
            $items = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($page === 'users' && $userRole === 'admin') {
            $sql = "SELECT * FROM users";
            if ($search) $sql .= " WHERE fullname LIKE '%$search%' OR username LIKE '%$search%' OR phone LIKE '%$search%'";
            $sql .= " ORDER BY id DESC";
            $items = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($page === 'categories' && $userRole === 'admin') {
            $sql = "SELECT * FROM categories";
            if ($search) $sql .= " WHERE name LIKE '%$search%'";
            $items = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($page === 'brands' && $userRole === 'admin') {
            $sql = "SELECT * FROM brands";
            if ($search) $sql .= " WHERE name LIKE '%$search%'";
            $items = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($page === 'vouchers' && $userRole === 'admin') {
            $sql = "SELECT * FROM vouchers";
            if ($search) $sql .= " WHERE code LIKE '%$search%'";
            $items = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($page === 'homepage') {
            $site_settings = getSiteSettings($this->db);

        } elseif ($page === 'warranties') {
            $items = getAllWarranties($this->db);

        } elseif ($page === 'returns') {
            $items = getAllReturns($this->db);
        }

        return [
            'categories' => $categories,
            'brands' => $brands,
            'search' => $search,
            'items' => $items,
            'status_filter' => $status_filter,
            'status_counts' => $status_counts,
            'total_orders' => $total_orders,
            'site_settings' => $site_settings
        ];
    }
}
