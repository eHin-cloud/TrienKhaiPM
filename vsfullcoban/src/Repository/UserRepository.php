<?php

namespace App\Repository;

use PDO;

/**
 * UserRepository
 * Lớp chịu trách nhiệm thực hiện các truy vấn SQL trực tiếp lên bảng users.
 */
class UserRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Lấy thông tin chi tiết của một người dùng theo ID.
     * @param int $id ID của người dùng.
     * @return array|false Thông tin người dùng hoặc false nếu không tìm thấy.
     */
    public function getUserById(int $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Cập nhật thông tin cơ bản của người dùng.
     * @param int $id ID của người dùng.
     * @param array $data Mảng chứa các trường cần cập nhật (fullname, email, phone, address, avatar).
     * @return bool Thành công hoặc thất bại.
     */
    public function updateUserProfile(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "`$key` = ?";
            $values[] = $value;
        }

        if (empty($fields)) return false;

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $values[] = $id;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Cập nhật mật khẩu cho người dùng.
     * @param int $id ID của người dùng.
     * @param string $hashedPassword Mật khẩu đã được hash.
     * @return bool Thành công hoặc thất bại.
     */
    public function updatePassword(int $id, string $hashedPassword): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashedPassword, $id]);
    }

    /**
     * Kiểm tra xem email đã tồn tại trong hệ thống chưa (loại trừ user hiện tại).
     * @param string $email Email cần kiểm tra.
     * @param int $excludeId ID của user hiện tại để không tự check trùng với chính mình.
     * @return bool True nếu email đã tồn tại, False nếu chưa.
     */
    public function checkEmailExists(string $email, int $excludeId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Đếm tổng số đơn hàng của một người dùng.
     * @param int $userId
     * @return int
     */
    public function countUserOrders(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Lấy danh sách đơn hàng của người dùng (có hỗ trợ phân trang).
     * @param int $userId ID của người dùng.
     * @param int|null $limit Giới hạn bản ghi.
     * @param int|null $offset Bắt đầu từ bản ghi thứ mấy.
     * @return array Danh sách đơn hàng.
     */
    public function getUserOrders(int $userId, ?int $limit = null, ?int $offset = null): array
    {
        $sql = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
        $params = [$userId];

        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = (int) $limit;
        }
        if ($offset !== null) {
            $sql .= " OFFSET ?";
            $params[] = (int) $offset;
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
