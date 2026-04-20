<?php

namespace App\Service;

use App\Repository\UserRepository;
use PDO;
use Exception;

/**
 * UserService
 * Lớp xử lý logic nghiệp vụ cho người dùng (User Account Management).
 */
class UserService
{
    private UserRepository $userRepo;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->userRepo = new UserRepository($db);
    }

    /**
     * Xử lý các hành động cập nhật tài khoản từ Form.
     * @param array $post Dữ liệu từ $_POST.
     * @param int $userId ID của người dùng đang đăng nhập.
     * @return array Kết quả trả về gồm ['success' => bool, 'message' => string].
     */
    public function handleAccountAction(array $post, int $userId): array
    {
        $action = $post['action'] ?? '';

        try {
            if ($action === 'update_profile') {
                return $this->processUpdateProfile($post, $userId);
            } elseif ($action === 'change_password') {
                return $this->processChangePassword($post, $userId);
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Hành động không hợp lệ!'];
    }

    /**
     * Xử lý cập nhật thông tin cá nhân.
     */
    private function processUpdateProfile(array $post, int $userId): array
    {
        $fullname = trim($post['fullname'] ?? '');
        $email = trim($post['email'] ?? '');
        $phone = trim($post['phone'] ?? '');
        $address = trim($post['address'] ?? '');

        if (empty($fullname) || empty($email)) {
            return ['success' => false, 'message' => 'Vui lòng nhập đầy đủ họ tên và email!'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Định dạng email không hợp lệ!'];
        }

        // Kiểm tra trùng email
        if ($this->userRepo->checkEmailExists($email, $userId)) {
            return ['success' => false, 'message' => 'Email này đã được sử dụng bởi tài khoản khác!'];
        }

        $data = [
            'fullname' => $fullname,
            'email'    => $email,
            'phone'    => $phone,
            'address'  => $address
        ];

        if ($this->userRepo->updateUserProfile($userId, $data)) {
            return ['success' => true, 'message' => 'Cập nhật thông tin thành công!'];
        }

        return ['success' => false, 'message' => 'Không thể cập nhật thông tin!'];
    }

    /**
     * Xử lý thay đổi mật khẩu.
     */
    private function processChangePassword(array $post, int $userId): array
    {
        $currentPassword = $post['current_password'] ?? '';
        $newPassword = $post['new_password'] ?? '';
        $confirmPassword = $post['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            return ['success' => false, 'message' => 'Vui lòng nhập đầy đủ mật khẩu!'];
        }

        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'message' => 'Mật khẩu mới và xác nhận mật khẩu không khớp!'];
        }

        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự!'];
        }

        // Kiểm tra mật khẩu hiện tại
        $user = $this->userRepo->getUserById($userId);
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Mật khẩu hiện tại không chính xác!'];
        }

        // Hash mật khẩu mới
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        if ($this->userRepo->updatePassword($userId, $hashedPassword)) {
            return ['success' => true, 'message' => 'Thay đổi mật khẩu thành công!'];
        }

        return ['success' => false, 'message' => 'Không thể cập nhật mật khẩu!'];
    }

    /**
     * Lấy thông tin profile đầy đủ bao gồm cả đơn hàng (có hỗ trợ phân trang).
     */
    public function getUserProfileData(int $userId, int $page = 1, int $limit = 5): array
    {
        $offset = ($page - 1) * $limit;
        $totalOrders = $this->userRepo->countUserOrders($userId);

        return [
            'user'         => $this->userRepo->getUserById($userId),
            'orders'       => $this->userRepo->getUserOrders($userId, $limit, $offset),
            'pagination'   => [
                'total_orders' => $totalOrders,
                'total_pages'  => ceil($totalOrders / $limit),
                'current_page' => $page,
                'limit'        => $limit
            ]
        ];
    }
}
