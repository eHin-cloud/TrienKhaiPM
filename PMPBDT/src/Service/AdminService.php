<?php

namespace App\Service;

use PDO;

class AdminService {
    private PDO $db;
    private \App\Repository\UserRepository $userRepo;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->userRepo = new \App\Repository\UserRepository($db);
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
            
            // Thông báo web
            $order = $this->db->query("SELECT user_id FROM orders WHERE id = $id")->fetch();
            if ($order) {
                $statusText = ['pending'=>'chờ xử lý','processing'=>'đã được xác nhận','delivering'=>'đang giao','completed'=>'đã hoàn thành','cancelled'=>'đã hủy'][$status] ?? $status;
                $this->createNotification($order['user_id'], "Đơn hàng #$id", "Đơn hàng của bạn $statusText.", 'order');
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
                $st = ['pending'=>'chờ duyệt','processing'=>'đang xử lý','completed'=>'đã xong','rejected'=>'bị từ chối'][$status] ?? $status;
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
                $st = ['pending'=>'chờ duyệt','processing'=>'đang xử lý','completed'=>'xong','rejected'=>'từ chối'][$status] ?? $status;
                $this->createNotification($rInfo['user_id'], "Đổi trả đơn hàng", "Yêu cầu đổi trả ĐH #$rInfo[order_id] $st." . ($admin_note ? " Ghi chú: $admin_note" : ""), 'system');
            }
            $msg = "Cập nhật trạng thái trả hàng thành công!";
            $msg_type = 'success';
        }

        // --- XỬ LÝ YÊU CẦU TRẢ GÓP ---
        elseif ($action === 'update_installment_status') {
            $id = $post['id'];
            $status = $post['status'];
            $admin_note = isset($post['admin_note']) ? trim($post['admin_note']) : '';
            $this->db->prepare("UPDATE installment_requests SET status=?, admin_note=? WHERE id=?")->execute([$status, $admin_note, $id]);
            
            $stmt = $this->db->prepare("SELECT ir.*, p.name as product_name FROM installment_requests ir JOIN products p ON ir.product_id = p.id WHERE ir.id = ?");
            $stmt->execute([$id]);
            $ir = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ir && $ir['user_id']) {
                $st = ['pending'=>'chờ duyệt','approved'=>'được chấp nhận','rejected'=>'bị từ chối'][$status] ?? $status;
                $this->createNotification($ir['user_id'], "Yêu cầu Trả Góp", "Yêu cầu trả góp cho sản phẩm $ir[product_name] đã $st." . ($admin_note ? " Ghi chú: $admin_note" : ""), 'system');
            }

            $msg = "Cập nhật yêu cầu trả góp thành công!";
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
                $productId = $this->db->lastInsertId();
                $msg = "Thêm sản phẩm thành công!";
            } else {
                $stmt = $this->db->prepare("UPDATE products SET name=?, category_id=?, brand_id=?, price=?, old_price=?, image=?, gift_text=?, tags=?, description=?, specifications=? WHERE id=?");
                $stmt->execute([$name, $category_id, $brand_id, $price, $old_price, $image, $gift_text, $tags, $description, $specifications, $id]);
                $productId = $id;
                $msg = "Cập nhật sản phẩm thành công!";
            }

            // Tự động dịch song ngữ sang Tiếng Anh thông qua API Google dịch & lưu cache ngay lập tức
            if ($productId) {
                // Đảm bảo nạp file lang.php để có sẵn hàm force_translate_to_english
                if (!function_exists('force_translate_to_english')) {
                    require_once __DIR__ . '/../../core/lang.php';
                }
                
                // Dịch các trường văn bản và HTML
                force_translate_to_english($name, 'prod_name_' . $productId, false);
                force_translate_to_english($description, 'prod_desc_' . $productId, true);
                force_translate_to_english($specifications, 'prod_specs_' . $productId, true);
                force_translate_to_english($gift_text, 'prod_gift_card_' . $productId, false);
                
                // Dịch chi tiết các dòng quà tặng con cách nhau bằng dấu chấm phẩy
                if (!empty($gift_text)) {
                    $gifts = array_filter(explode(';', $gift_text));
                    foreach ($gifts as $idx => $g) {
                        force_translate_to_english(trim($g), 'prod_gift_' . $productId . '_' . $idx, false);
                    }
                }
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
                $id = (int)$post['id'];
                $status = (int)$post['status'];
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
                    $this->autoTranslateCategory($name);
                } else {
                    $this->db->prepare("UPDATE categories SET name=?, icon=? WHERE id=?")->execute([$name, $icon, $id]);
                    $msg = "Cập nhật danh mục thành công!";
                    $this->autoTranslateCategory($name);
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
            
            // --- XỬ LÝ ĐĂNG KÝ NHẬN ƯU ĐÃI (NEWSLETTER) ---
            elseif ($action === 'approve_newsletter') {
                $id = $post['id'];
                $this->db->prepare("UPDATE newsletters SET status='approved' WHERE id=?")->execute([$id]);
                
                // Lấy thông tin người đăng ký
                $sub = $this->db->query("SELECT user_id, email FROM newsletters WHERE id=$id")->fetch();
                if ($sub && $sub['user_id']) {
                    // Tạo một mã giảm giá ngẫu nhiên 50K
                    $code = 'NEWS' . strtoupper(substr(md5(time() . $sub['email']), 0, 5));
                    $this->db->prepare("INSERT INTO vouchers (code, discount_amount, discount_type, usage_limit) VALUES (?, 50000, 'fixed', 1)")->execute([$code]);
                    
                    // Gửi thông báo trực tiếp cho người dùng
                    $this->createNotification($sub['user_id'], "Quà tặng Đăng ký Ưu đãi", "Cảm ơn bạn đã đăng ký! Tặng bạn mã giảm giá 50.000đ: " . $code . ". Nhập mã này ở bước thanh toán nhé!", 'system');
                }
                
                $msg = "Đã duyệt và gửi mã giảm giá thành công!";
                $msg_type = 'success';
            } elseif ($action === 'delete_newsletter') {
                $this->db->prepare("DELETE FROM newsletters WHERE id=?")->execute([$post['id']]);
                $msg = "Đã xóa lượt đăng ký!";
                $msg_type = 'success';
            } elseif ($action === 'delete_all_newsletters') {
                $this->db->query("DELETE FROM newsletters");
                $msg = "Đã xóa toàn bộ danh sách đăng ký nhận ưu đãi!";
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
            $items = $this->userRepo->findAll($search);

        } elseif ($page === 'login_history' && $userRole === 'admin') {
            $page_num = isset($getParams['page']) && (int)$getParams['page'] > 0 ? (int)$getParams['page'] : 1;
            $limit = 20;
            $offset = ($page_num - 1) * $limit;

            $whereSql = "";
            if ($search) {
                $whereSql = " WHERE u.username LIKE '%$search%' OR u.fullname LIKE '%$search%' OR DATE(lh.login_time) = '$search'";
            }

            $countSql = "SELECT COUNT(*) FROM login_history lh JOIN users u ON lh.user_id = u.id" . $whereSql;
            $total_records = (int)$this->db->query($countSql)->fetchColumn();
            
            $sql = "SELECT lh.*, u.username, u.fullname, u.is_banned FROM login_history lh JOIN users u ON lh.user_id = u.id" . $whereSql . " ORDER BY lh.login_time DESC LIMIT $limit OFFSET $offset";
            $items = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            $pagination = [
                'current_page' => $page_num,
                'limit' => $limit,
                'total_records' => $total_records,
                'total_pages' => max(1, ceil($total_records / $limit))
            ];


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

        } elseif ($page === 'newsletters' && $userRole === 'admin') {
            $sql = "SELECT * FROM newsletters";
            if ($search) $sql .= " WHERE email LIKE '%$search%'";
            $sql .= " ORDER BY id DESC";
            $items = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($page === 'homepage') {
            $site_settings = getSiteSettings($this->db);
            $products_list = $this->db->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($page === 'warranties') {
            $items = getAllWarranties($this->db);

        } elseif ($page === 'returns') {
            $items = getAllReturns($this->db);
        } elseif ($page === 'installments') {
            $sql = "SELECT ir.*, p.name as product_name, p.image as product_image FROM installment_requests ir JOIN products p ON ir.product_id = p.id";
            if ($search) {
                $sql .= " WHERE ir.fullname LIKE '%$search%' OR ir.phone LIKE '%$search%' OR p.name LIKE '%$search%'";
            }
            $sql .= " ORDER BY ir.id DESC";
            $items = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($page === 'dashboard') {
            return $this->getDashboardData();
        } elseif ($page === 'revenue') {
            return $this->getRevenueHistory();
        }

        // Tự động phân trang cho tất cả các tab (nếu chưa được phân trang bằng SQL như login_history)
        if ($page !== 'homepage' && !isset($pagination)) {
            $page_num = isset($getParams['page']) && (int)$getParams['page'] > 0 ? (int)$getParams['page'] : 1;
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

    private function createNotification(int $userId, string $title, string $message, string $type = 'system'): void {
        try {
            $stmt = $this->db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$userId, $title, $message, $type]);
        } catch (\Exception $e) {}
    }

    /**
     * LẤY DỮ LIỆU TỔNG QUAN (DASHBOARD)
     */
    public function getDashboardData(): array {
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
            $revenue = $this->db->query("SELECT SUM(total_price) FROM orders WHERE status = 'completed' AND DATE(created_at) = '$date'")->fetchColumn() ?: 0;
            $chartData[] = [
                'date' => date('d/m', strtotime($date)),
                'revenue' => (int)$revenue
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
    public function getRevenueHistory(): array {
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

        // Danh sách các giao dịch lớn gần đây
        $recentTransactions = $this->db->query("
            SELECT id, fullname, total_price, payment_method, completed_at 
            FROM orders 
            WHERE status = 'completed' 
            ORDER BY completed_at DESC 
            LIMIT 50
        ")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'monthly' => $monthlyRevenue,
            'transactions' => $recentTransactions
        ];
    }

    /**
     * TỰ ĐỘNG DỊCH DANH MỤC SANG TIẾNG ANH & CẬP NHẬT FILE NGÔN NGỮ
     */
    private function autoTranslateCategory(string $viName): void {
        try {
            // 1. Dịch từ Tiếng Việt sang Tiếng Anh bằng Google Translate API miễn phí
            $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=vi&tl=en&dt=t&q=" . urlencode($viName);
            
            $options = [
                "http" => [
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.36\r\n"
                ]
            ];
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return;
            }
            
            $resData = json_decode($response, true);
            $enName = $resData[0][0][0] ?? $viName;
            
            // 2. Đường dẫn tới file ngôn ngữ
            $viFile = __DIR__ . '/../../core/lang/vi.php';
            $enFile = __DIR__ . '/../../core/lang/en.php';
            
            // Cập nhật file vi.php
            if (file_exists($viFile)) {
                $viContent = file_get_contents($viFile);
                if (strpos($viContent, "'$viName'") === false && strpos($viContent, '"' . $viName . '"') === false) {
                    $pattern = "/'categories_map'\s*=>\s*\[/";
                    $replacement = "'categories_map' => [\n        '$viName' => '$viName',";
                    $newContent = preg_replace($pattern, $replacement, $viContent, 1);
                    if ($newContent !== null) {
                        file_put_contents($viFile, $newContent);
                    }
                }
            }
            
            // Cập nhật file en.php
            if (file_exists($enFile)) {
                $enContent = file_get_contents($enFile);
                if (strpos($enContent, "'$viName'") === false && strpos($enContent, '"' . $viName . '"') === false) {
                    $pattern = "/'categories_map'\s*=>\s*\[/";
                    $replacement = "'categories_map' => [\n        '$viName' => '$enName',";
                    $newContent = preg_replace($pattern, $replacement, $enContent, 1);
                    if ($newContent !== null) {
                        file_put_contents($enFile, $newContent);
                    }
                }
            }
        } catch (\Exception $e) {
            // Tránh làm lỗi luồng chính nếu API lỗi
        }
    }
}
