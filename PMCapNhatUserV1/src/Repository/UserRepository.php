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

        if (empty($fields))
            return false;

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
        return (int) $stmt->fetchColumn() > 0;
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

    /**
     * Tạo tài khoản khách hàng mới.
     * @param array $data Dữ liệu người dùng [phone, username, password, fullname].
     * @return int|false ID của user mới tạo hoặc false nếu thất bại.
     */
    public function createCustomer(array $data)
    {
        $sql = "INSERT INTO users (phone, username, password, fullname, role) VALUES (?, ?, ?, ?, 'customer')";
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute([$data['phone'], $data['username'], $data['password'], $data['fullname']])) {
            return (int) $this->db->lastInsertId();
        }
        return false;
    }

    /**
     * Cập nhật thông tin hồ sơ người dùng vào cơ sở dữ liệu.
     * Sử dụng Prepared Statement (?) để chống lỗi SQL Injection.
     * * @param int $id ID của user cần cập nhật.
     * @param array $data Mảng chứa dữ liệu mới (full_name, phone, address).
     * @return bool Trả về true nếu thực thi SQL thành công, false nếu thất bại.
     */
    public function update(int $id, array $data)
    {
        // 1. Chuẩn bị câu lệnh SQL (Dùng dấu ? làm tham số để bảo mật)
        $query = "UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?";

        // 2. Prepare câu lệnh với PDO
        $stmt = $this->db->prepare($query);

        // 3. Truyền giá trị thật vào và thực thi (execute)
        // Các phần tử trong mảng execute() phải đúng thứ tự với các dấu ? ở trên
        return $stmt->execute([
            $data['full_name'], // Tương ứng với dấu ? đầu tiên
            $data['phone'],     // Tương ứng với dấu ? thứ hai
            $data['address'],   // Tương ứng với dấu ? thứ ba
            $id                 // Tương ứng với dấu ? cuối cùng (WHERE id = ?)
        ]);
    }

}
