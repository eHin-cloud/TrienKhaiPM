<?php

namespace App\Repository;

use PDO;
use PDOException;

/**
 * CartRepository
 * Lớp này chịu trách nhiệm quản lý tất cả các thao tác liên quan đến giỏ hàng (cart).
 * Nó tương tác với bảng `cart_items` và đảm bảo tính toàn vẹn dữ liệu bằng cách sử dụng các giao dịch (transactions).
 */
class CartRepository {
    private PDO $db;

    /**
     * Constructor nhận PDO object qua Dependency Injection.
     * @param PDO $db Kết nối cơ sở dữ liệu PDO.
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Cập nhật giỏ hàng hàng loạt (Batch Update).
     * Thuật toán: Sử dụng Transaction (BEGIN/COMMIT/ROLLBACK) để đảm bảo tính nguyên tử (Atomicity)
     * của các thao tác ghi dữ liệu. Sử dụng cú pháp `ON DUPLICATE KEY UPDATE` để
     * xử lý trường hợp sản phẩm đã tồn tại trong giỏ hàng, chỉ cần cập nhật số lượng.
     * @param int $userId ID của người dùng.
     * @param array $items Danh sách các mặt hàng cần cập nhật (mỗi item: ['product_id', 'quantity', 'price']).
     * @return bool Trả về true nếu thành công, false nếu có lỗi.
     */
    public function updateCart(int $userId, array $items): bool {
        $this->db->beginTransaction();
        try {
            foreach ($items as $item) {
                // Giả định bảng cart_items có các cột: user_id, product_id, quantity
                // Nếu dự án có thêm price, hãy thêm vào. Cấu trúc hiện tại có vẻ chỉ dùng user_id, product_id, quantity.
                $stmt = $this->db->prepare("
                    INSERT INTO cart_items (user_id, product_id, quantity) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)
                ");
                $stmt->execute([
                    $userId, 
                    $item['product_id'], 
                    $item['quantity']
                ]);
            }
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Lỗi khi cập nhật giỏ hàng: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lấy toàn bộ các mặt hàng trong giỏ hàng của người dùng.
     * Thuật toán: Sử dụng JOIN giữa bảng `cart_items` và `products` để lấy thông tin chi tiết
     * của sản phẩm (tên, giá, ảnh) cùng với số lượng trong giỏ hàng.
     * @param int $userId ID của người dùng.
     * @return array Danh sách các mặt hàng trong giỏ hàng.
     */
    public function getCartItems(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT c.cart_id, c.quantity, p.id as product_id, p.name, p.price, p.image 
            FROM cart_items c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Cập nhật giỏ hàng bằng cách thay đổi số lượng hoặc xóa sản phẩm.
     * Phương thức này được gọi khi người dùng thực hiện các hành động đơn lẻ (tăng/giảm/xóa).
     * @param int $cartId ID của mục hàng trong giỏ hàng.
     * @param int $userId ID của người dùng.
     * @param string $action Hành động: 'delete', 'increase', hoặc 'decrease'.
     */
    public function modifyCartItem(int $cartId, int $userId, string $action): void {
        if ($action === 'delete') {
            // Xóa sản phẩm khỏi giỏ hàng.
            $stmt = $this->db->prepare("DELETE FROM cart_items WHERE cart_id = ? AND user_id = ?");
            $stmt->execute([$cartId, $userId]);
        } elseif ($action === 'increase') {
            // Tăng số lượng sản phẩm.
            $stmt = $this->db->prepare("UPDATE cart_items SET quantity = quantity + 1 WHERE cart_id = ? AND user_id = ?");
            $stmt->execute([$cartId, $userId]);
        } elseif ($action === 'decrease') {
            // Giảm số lượng sản phẩm, đảm bảo số lượng không bị âm.
            $stmt = $this->db->prepare("UPDATE cart_items SET quantity = quantity - 1 WHERE cart_id = ? AND user_id = ? AND quantity > 1");
            $stmt->execute([$cartId, $userId]);
        }
    }

    /**
     * Thêm sản phẩm vào giỏ hàng (tăng số lượng nếu đã có).
     * Thuật toán: Kiểm tra sự tồn tại của sản phẩm trước. Nếu có, thực hiện UPDATE; nếu không, thực hiện INSERT.
     * @param int $userId ID của người dùng.
     * @param int $productId ID của sản phẩm.
     * @param int $quantity Số lượng sản phẩm cần thêm.
     */
    public function addToCart(int $userId, int $productId, int $quantity = 1): void {
        // Bước 1: Kiểm tra xem sản phẩm đã có trong giỏ hàng chưa.
        $stmt = $this->db->prepare("SELECT cart_id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        $item = $stmt->fetch();

        if ($item) {
            // Bước 2: Nếu đã có, cập nhật số lượng.
            $newQty = $item['quantity'] + $quantity;
            $this->db->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ?")->execute([$newQty, $item['cart_id']]);
        } else {
            // Bước 3: Nếu chưa có, thêm mới.
            $this->db->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)")->execute([$userId, $productId, $quantity]);
        }
    }
    
    /**
     * Đếm tổng số sản phẩm trong giỏ hàng.
     * Thuật toán: Sử dụng hàm tổng hợp (SUM) của SQL để tính tổng số lượng sản phẩm.
     * @param int $userId ID của người dùng.
     * @return int Tổng số lượng sản phẩm.
     */
    public function getCartCount(int $userId): int {
        $stmt = $this->db->prepare("
            SELECT SUM(c.quantity) 
            FROM cart_items c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?
        ");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Xoá toàn bộ giỏ hàng của User.
     * Được gọi sau khi quá trình thanh toán thành công.
     * @param int $userId ID của người dùng.
     */
    public function clearCart(int $userId): void {
        $stmt = $this->db->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
}