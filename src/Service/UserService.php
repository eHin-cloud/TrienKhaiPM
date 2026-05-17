<?php

namespace App\Service;

use App\Repository\UserRepository;
use PDO;
use Exception;

require_once __DIR__ . '/../../core/totp_helper.php';
require_once __DIR__ . '/../../core/otp_mail_templates.php';

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
            } elseif ($action === 'enable_2fa') {
                return $this->startTwoFactorEnrollment($userId);
            } elseif ($action === 'verify_2fa_enable') {
                return $this->verifyTwoFactorEnrollment($userId, trim($post['otp_code'] ?? ''));
            } elseif ($action === 'disable_2fa') {
                return $this->disableTwoFactor($userId);
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
        $username = trim($post['username'] ?? '');
        $email = trim($post['email'] ?? '');
        $phone = trim($post['phone'] ?? '');
        $address = trim($post['address'] ?? '');

        if (empty($fullname) || empty($username)) {
            return ['success' => false, 'message' => 'Vui lòng nhập đầy đủ họ tên và tên đăng nhập!'];
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
            return ['success' => false, 'message' => 'Tên đăng nhập chỉ được chứa chữ, số, dấu chấm, gạch dưới hoặc gạch ngang và dài 3-30 ký tự!'];
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Định dạng email không hợp lệ!'];
        }

        // Kiểm tra trùng username
        if ($this->userRepo->checkUsernameExists($username, $userId)) {
            return ['success' => false, 'message' => 'Tên đăng nhập này đã tồn tại!'];
        }

        // Kiểm tra trùng email nếu có nhập
        if (!empty($email) && $this->userRepo->checkEmailExists($email, $userId)) {
            return ['success' => false, 'message' => 'Email này đã được sử dụng bởi tài khoản khác!'];
        }

        $data = [
            'fullname' => $fullname,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'address' => $address
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
            'user' => $this->userRepo->getUserById($userId),
            'orders' => $this->userRepo->getUserOrders($userId, $limit, $offset),
            'pagination' => [
                'total_orders' => $totalOrders,
                'total_pages' => ceil($totalOrders / $limit),
                'current_page' => $page,
                'limit' => $limit
            ]
        ];
    }

    /**
     * Xử lý logic đăng ký tài khoản.
     * @param array $post Dữ liệu từ form.
     * @return array ['success' => bool, 'userId' => int|null, 'message' => string]
     */
    public function registerUser(array $post): array
    {
        $fullname = trim($post['fullname'] ?? '');
        $phone = trim($post['phone'] ?? '');
        $email = trim($post['email'] ?? '');
        $username = trim($post['username'] ?? '');
        $password = trim($post['password'] ?? '');
        $confirm_password = trim($post['confirm_password'] ?? '');

        if (strlen($password) < 8 || !preg_match('/[a-zA-Z]/', $password)) {
            return ['success' => false, 'message' => 'Mật khẩu phải có ít nhất 8 ký tự và chứa ít nhất 1 chữ cái!'];
        }
        if ($password !== $confirm_password) {
            return ['success' => false, 'message' => 'Mật khẩu xác nhận không khớp!'];
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Định dạng email không hợp lệ!'];
        }

        // Kiểm tra trùng username hoặc phone
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR phone = ?");
        $stmt->execute([$username, $phone]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Tên tài khoản hoặc Số điện thoại đã tồn tại!'];
        }

        // Hash mật khẩu trước khi lưu
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $userId = $this->userRepo->createCustomer([
            'phone' => $phone,
            'username' => $username,
            'password' => $hashedPassword,
            'fullname' => $fullname,
            'email' => $email,
            'auth_provider' => 'local'
        ]);

        if ($userId) {
            return ['success' => true, 'userId' => $userId, 'message' => 'Đăng ký thành công!'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi tạo tài khoản!'];
    }

    /**
     * Gửi OTP quên mật khẩu qua email.
     */
    public function requestPasswordResetOtp(array $post): array
    {
        $email = trim($post['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Vui lòng nhập email hợp lệ!'];
        }

        $stmt = $this->db->prepare("SELECT id, fullname, email FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Không tiết lộ email có tồn tại hay không.
        if (!$user) {
            return ['success' => true, 'message' => 'Nếu email tồn tại trong hệ thống, mã OTP đã được gửi.'];
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $otp = (string) random_int(100000, 999999);
        $_SESSION['reset_password_otp'] = [
            'user_id' => (int) $user['id'],
            'email' => $user['email'],
            'otp' => $otp,
            'expires_at' => time() + 600, // 10 phút
            'attempts' => 0,
            'last_sent_at' => time(),
            'resend_available_at' => time() + 60,
            'token' => bin2hex(random_bytes(16))
        ];

        require_once __DIR__ . '/../../core/mail_helper.php';
        $subject = 'DienMayPro - Mã OTP đặt lại mật khẩu';
        $body = buildOtpEmailTemplate(
            'Đặt lại mật khẩu',
            'Xác minh yêu cầu khôi phục tài khoản',
            $otp,
            'Xin chào ' . ($user['fullname'] ?: $user['email']) . ', vui lòng dùng mã OTP bên dưới để đặt lại mật khẩu tài khoản của bạn.'
        );

        $sent = sendEmail($user['email'], $user['fullname'] ?: 'Khách hàng', $subject, $body);
        if (!$sent) {
            unset($_SESSION['reset_password_otp']);
            return ['success' => false, 'message' => 'Không thể gửi OTP lúc này. Vui lòng thử lại sau!'];
        }

        return ['success' => true, 'message' => 'OTP đã được gửi đến email của bạn.'];
    }

    public function resendPasswordResetOtp(array $post): array
    {
        $email = trim($post['email'] ?? '');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionOtp = $_SESSION['reset_password_otp'] ?? null;
        if (!$sessionOtp) {
            return ['success' => false, 'message' => 'Vui lòng yêu cầu OTP trước khi gửi lại!'];
        }

        if (strcasecmp($email, $sessionOtp['email'] ?? '') !== 0) {
            return ['success' => false, 'message' => 'Email không khớp với yêu cầu OTP!'];
        }

        $lastSentAt = (int) ($sessionOtp['last_sent_at'] ?? 0);
        if ($lastSentAt > 0 && (time() - $lastSentAt) < 60) {
            $wait = 60 - (time() - $lastSentAt);
            return ['success' => false, 'message' => 'Vui lòng chờ ' . $wait . ' giây trước khi gửi lại OTP!'];
        }

        return $this->requestPasswordResetOtp($post);
    }

    /**
     * Đặt lại mật khẩu bằng OTP đã gửi qua email.
     */
    public function resetPasswordWithOtp(array $post): array
    {
        $email = trim($post['email'] ?? '');
        $otp = trim($post['otp'] ?? '');
        $newPassword = trim($post['new_password'] ?? '');
        $confirmPassword = trim($post['confirm_password'] ?? '');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionOtp = $_SESSION['reset_password_otp'] ?? null;
        if (!$sessionOtp) {
            return ['success' => false, 'message' => 'OTP không tồn tại hoặc đã hết hạn. Vui lòng yêu cầu lại mã mới!'];
        }

        if ($sessionOtp['expires_at'] < time()) {
            unset($_SESSION['reset_password_otp']);
            return ['success' => false, 'message' => 'OTP đã hết hạn. Vui lòng yêu cầu mã mới!'];
        }

        if (strcasecmp($email, $sessionOtp['email']) !== 0) {
            return ['success' => false, 'message' => 'Email không khớp với yêu cầu OTP!'];
        }

        if (!preg_match('/^\d{6}$/', $otp) || $otp !== $sessionOtp['otp']) {
            $_SESSION['reset_password_otp']['attempts'] = (int) ($sessionOtp['attempts'] ?? 0) + 1;
            if ($_SESSION['reset_password_otp']['attempts'] >= 5) {
                unset($_SESSION['reset_password_otp']);
                return ['success' => false, 'message' => 'Bạn đã nhập sai OTP quá nhiều lần. Vui lòng yêu cầu lại mã mới!'];
            }
            return ['success' => false, 'message' => 'Mã OTP không đúng!'];
        }

        if (strlen($newPassword) < 8 || !preg_match('/[a-zA-Z]/', $newPassword)) {
            return ['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 8 ký tự và chứa ít nhất 1 chữ cái!'];
        }
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'message' => 'Mật khẩu xác nhận không khớp!'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!$this->userRepo->updatePassword((int) $sessionOtp['user_id'], $hashedPassword)) {
            return ['success' => false, 'message' => 'Không thể cập nhật mật khẩu. Vui lòng thử lại!'];
        }

        unset($_SESSION['reset_password_otp']);
        return ['success' => true, 'message' => 'Đặt lại mật khẩu thành công! Vui lòng đăng nhập lại.'];
    }

    public function startTwoFactorEnrollment(int $userId): array
    {
        $user = $this->userRepo->getUserByIdForAuth($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'Không tìm thấy tài khoản.'];
        }

        $email = trim((string) ($user['email'] ?? ''));
        $isGmail = (bool) preg_match('/@gmail\.com$/i', $email);
        if (($user['auth_provider'] ?? 'local') !== 'google' && !$isGmail) {
            return ['success' => false, 'message' => 'Chỉ tài khoản có Gmail hoặc tài khoản Google mới có thể bật 2FA bằng Gmail OTP.'];
        }

        if (!($this->userRepo->hasTwoFactorColumns())) {
            return ['success' => false, 'message' => 'Cơ sở dữ liệu chưa có cột 2FA.'];
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $otp = (string) random_int(100000, 999999);
        $_SESSION['two_factor_pending_enroll'] = [
            'user_id' => $userId,
            'email' => $user['email'],
            'otp' => $otp,
            'expires_at' => time() + 600,
            'attempts' => 0,
        ];

        require_once __DIR__ . '/../../core/mail_helper.php';
        require_once __DIR__ . '/../../core/otp_mail_templates.php';
        $subject = 'DienMayPro - Mã OTP bật bảo mật 2 lớp';
        $body = buildOtpEmailTemplate(
            'Bật bảo mật 2 lớp',
            'Xác minh qua Gmail OTP',
            $otp,
            'Xin chào ' . ($user['fullname'] ?: $user['email']) . ', vui lòng dùng mã OTP bên dưới để bật bảo mật 2 lớp cho tài khoản của bạn.'
        );

        $sent = sendEmail($user['email'], $user['fullname'] ?: 'Khách hàng', $subject, $body);
        if (!$sent) {
            unset($_SESSION['two_factor_pending_enroll']);
            return ['success' => false, 'message' => 'Không thể gửi mã OTP 2FA. Vui lòng thử lại sau!'];
        }

        return ['success' => true, 'message' => 'Đã gửi mã OTP 2FA đến email của bạn.'];
    }

    public function verifyTwoFactorEnrollment(int $userId, string $code): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $pending = $_SESSION['two_factor_pending_enroll'] ?? null;
        if (!$pending || (int)($pending['user_id'] ?? 0) !== $userId) {
            return ['success' => false, 'message' => 'Phiên xác minh 2FA không hợp lệ.'];
        }

        if (($pending['expires_at'] ?? 0) < time()) {
            unset($_SESSION['two_factor_pending_enroll']);
            return ['success' => false, 'message' => 'Mã OTP 2FA đã hết hạn. Vui lòng yêu cầu lại mã mới!'];
        }

        if (!preg_match('/^\d{6}$/', $code) || $code !== ($pending['otp'] ?? '')) {
            $_SESSION['two_factor_pending_enroll']['attempts'] = (int)($pending['attempts'] ?? 0) + 1;
            if ($_SESSION['two_factor_pending_enroll']['attempts'] >= 5) {
                unset($_SESSION['two_factor_pending_enroll']);
                return ['success' => false, 'message' => 'Bạn đã nhập sai OTP quá nhiều lần. Vui lòng yêu cầu lại mã mới!'];
            }
            return ['success' => false, 'message' => 'Mã OTP không đúng.'];
        }

        if (!$this->userRepo->enableTwoFactor($userId, 1)) {
            return ['success' => false, 'message' => 'Không thể bật 2FA.'];
        }

        unset($_SESSION['two_factor_pending_enroll']);
        return ['success' => true, 'message' => 'Đã bật bảo mật 2 lớp bằng Gmail OTP thành công!'];
    }

    public function disableTwoFactor(int $userId): array
    {
        if (!$this->userRepo->enableTwoFactor($userId, 0)) {
            return ['success' => false, 'message' => 'Không thể tắt 2FA.'];
        }

        return ['success' => true, 'message' => 'Đã tắt bảo mật 2 lớp.'];
    }
}

