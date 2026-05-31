<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Service\UserService;

if (empty($_SESSION['pending_2fa_user_id']) || empty($_SESSION['pending_2fa_otp'])) {
    header('Location: index.php');
    exit;
}

$userService = new UserService($db);
$message = '';
$messageType = '';
$pendingUserId = (int) $_SESSION['pending_2fa_user_id'];
$pendingEmail = (string) ($_SESSION['pending_2fa_email'] ?? '');
$pendingOtp = (string) $_SESSION['pending_2fa_otp'];
$expiresAt = (int) ($_SESSION['pending_2fa_expires_at'] ?? 0);
$resendAt = (int) ($_SESSION['pending_2fa_resend_at'] ?? 0);

if ($expiresAt > 0 && $expiresAt < time()) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_username'], $_SESSION['pending_2fa_name'], $_SESSION['pending_2fa_role'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_otp'], $_SESSION['pending_2fa_expires_at'], $_SESSION['pending_2fa_attempts'], $_SESSION['pending_2fa_started_at']);
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'verify';

    if ($action === 'resend_2fa_otp') {
        $lastSentAt = (int) ($_SESSION['pending_2fa_last_sent_at'] ?? 0);
        if ($lastSentAt > 0 && (time() - $lastSentAt) < 60) {
            $message = 'Bạn vui lòng chờ 60 giây trước khi gửi lại OTP.';
            $messageType = 'error';
        } else {
            $otp = (string) random_int(100000, 999999);
            $_SESSION['pending_2fa_otp'] = $otp;
            $_SESSION['pending_2fa_expires_at'] = time() + 600;
            $_SESSION['pending_2fa_attempts'] = 0;
            $_SESSION['pending_2fa_last_sent_at'] = time();
            $_SESSION['pending_2fa_resend_at'] = time() + 60;
            $pendingOtp = $otp;
            $expiresAt = (int) $_SESSION['pending_2fa_expires_at'];
            $resendAt = (int) $_SESSION['pending_2fa_resend_at'];

            require_once __DIR__ . '/../../core/mail_helper.php';
            require_once __DIR__ . '/../../core/otp_mail_templates.php';
            $body = buildOtpEmailTemplate(
                'Xác minh đăng nhập',
                'Mã OTP bảo mật 2 lớp',
                $otp,
                'Bạn vừa đăng nhập vào tài khoản DienMayPro. Vui lòng dùng mã OTP bên dưới để hoàn tất đăng nhập.'
            );
            $sent = sendEmail($pendingEmail, $_SESSION['pending_2fa_name'] ?? 'Khách hàng', 'DienMayPro - Mã OTP xác minh đăng nhập', $body);
            if ($sent) {
                $message = 'Đã gửi lại mã OTP đến email của bạn.';
                $messageType = 'success';
            } else {
                $message = 'Không thể gửi lại mã OTP. Vui lòng thử lại sau.';
                $messageType = 'error';
            }
        }
    } else {
        $code = trim($_POST['otp_code'] ?? '');
        if ($expiresAt > 0 && $expiresAt < time()) {
            $message = 'Mã OTP đã hết hạn. Vui lòng gửi lại mã mới.';
            $messageType = 'error';
        } elseif (!preg_match('/^\d{6}$/', $code)) {
            $message = 'Vui lòng nhập mã OTP hợp lệ.';
            $messageType = 'error';
        } elseif (!hash_equals($pendingOtp, $code)) {
            $_SESSION['pending_2fa_attempts'] = (int)($_SESSION['pending_2fa_attempts'] ?? 0) + 1;
            if ($_SESSION['pending_2fa_attempts'] >= 5) {
                unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_username'], $_SESSION['pending_2fa_name'], $_SESSION['pending_2fa_role'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_otp'], $_SESSION['pending_2fa_expires_at'], $_SESSION['pending_2fa_attempts'], $_SESSION['pending_2fa_started_at']);
                $message = 'Bạn đã nhập sai quá nhiều lần. Vui lòng đăng nhập lại.';
            } else {
                $message = 'Mã xác thực không đúng. Vui lòng thử lại.';
            }
            $messageType = 'error';
        } else {
            $user = $userService->getUserProfileData($pendingUserId)['user'] ?? null;
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];
                unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_username'], $_SESSION['pending_2fa_name'], $_SESSION['pending_2fa_role'], $_SESSION['pending_2fa_otp'], $_SESSION['pending_2fa_expires_at'], $_SESSION['pending_2fa_attempts'], $_SESSION['pending_2fa_started_at'], $_SESSION['pending_2fa_email']);

                record_login_history($db, $user['id'], 'success');

                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác minh 2 lớp - DienMayPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 flex items-center justify-center px-4">
    <div class="w-full max-w-md rounded-[28px] bg-white p-8 shadow-2xl">
        <div class="text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-primary">
                <i class="fa-solid fa-shield-halved text-2xl"></i>
            </div>
            <h1 class="text-2xl font-black text-slate-800">Xác minh đăng nhập</h1>
            <p class="mt-2 text-sm text-slate-500">Nhập mã OTP vừa được gửi đến email của bạn để tiếp tục đăng nhập.</p>
            <?php if (!empty($_SESSION['pending_2fa_username'])): ?>
                <p class="mt-2 text-xs text-slate-400">Tài khoản: <?= htmlspecialchars($_SESSION['pending_2fa_username']) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="mt-5 rounded-2xl border <?= $messageType === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700' ?> px-4 py-3 text-sm">
                <i class="fa-solid <?= $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> mr-1"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="mt-5 rounded-2xl border border-blue-100 bg-blue-50 p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Thời gian còn lại</p>
                    <p id="otp-countdown" class="mt-1 text-2xl font-black text-slate-800">10:00</p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    <p>Mã OTP hết hạn sau 10 phút</p>
                    <p>Vui lòng kiểm tra Gmail</p>
                </div>
            </div>
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-blue-100">
                <div id="otp-progress" class="h-full w-full rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 transition-all duration-1000"></div>
            </div>
        </div>

        <?php $isExpired = $expiresAt > 0 && $expiresAt < time(); ?>
        <?php $isResendLocked = $resendAt > time(); ?>
        <form method="POST" class="mt-6 space-y-4">
            <?= csrf_input_field() ?>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Mã xác thực</label>
                <input type="text" name="otp_code" maxlength="6" pattern="\d{6}" required autofocus <?= $isExpired ? 'disabled' : '' ?>
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center text-lg tracking-[0.35em] outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10 disabled:cursor-not-allowed disabled:bg-slate-100"
                    placeholder="123456">
            </div>
            <button id="verify-btn" type="submit" <?= $isExpired ? 'disabled' : '' ?> class="w-full rounded-2xl bg-blue-600 py-3.5 font-bold text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500">Xác minh</button>
        </form>

        <form method="POST" class="mt-3">
            <?= csrf_input_field() ?>
            <input type="hidden" name="action" value="resend_2fa_otp">
            <button id="resend-btn" type="submit" <?= ($isExpired || $isResendLocked) ? 'disabled' : '' ?> class="w-full rounded-2xl border border-slate-200 bg-white py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">Gửi lại OTP</button>
            <p id="resend-timer" class="mt-2 text-center text-xs text-slate-500"></p>
        </form>

        <form method="POST" action="index.php" class="mt-3">
            <?= csrf_input_field() ?>
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="w-full rounded-2xl border border-slate-200 bg-white py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Hủy đăng nhập</button>
        </form>
    </div>

    <script>
        (function () {
            const expiresAt = <?= (int) $expiresAt ?> * 1000;
            const countdownEl = document.getElementById('otp-countdown');
            const progressEl = document.getElementById('otp-progress');
            const verifyBtn = document.getElementById('verify-btn');
            const resendBtn = document.getElementById('resend-btn');
            const resendTimerEl = document.getElementById('resend-timer');
            const totalMs = 10 * 60 * 1000;
            const resendCooldownKey = 'dmp_2fa_resend_until_<?= (int) $pendingUserId ?>';
            let resendUntil = Math.max(parseInt(sessionStorage.getItem(resendCooldownKey) || '0', 10), <?= (int) $resendAt ?> * 1000);

            function format(ms) {
                const totalSeconds = Math.max(0, Math.floor(ms / 1000));
                const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
                const seconds = String(totalSeconds % 60).padStart(2, '0');
                return `${minutes}:${seconds}`;
            }

            function syncResendCooldown() {
                const now = Date.now();
                if (resendUntil > now) {
                    const left = resendUntil - now;
                    if (resendBtn) resendBtn.disabled = true;
                    if (resendTimerEl) resendTimerEl.textContent = `Bạn có thể gửi lại sau ${format(left)}.`;
                } else {
                    if (resendBtn) resendBtn.disabled = <?= $isExpired ? 'true' : 'false' ?>;
                    if (resendTimerEl) resendTimerEl.textContent = '';
                }
            }

            function tick() {
                const now = Date.now();
                const remaining = Math.max(0, expiresAt - now);
                const totalSeconds = Math.floor(remaining / 1000);
                const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
                const seconds = String(totalSeconds % 60).padStart(2, '0');
                if (countdownEl) countdownEl.textContent = `${minutes}:${seconds}`;
                if (progressEl) progressEl.style.width = `${Math.max(0, (remaining / totalMs) * 100)}%`;

                if (remaining <= 0) {
                    if (countdownEl) countdownEl.textContent = 'Hết hạn';
                    if (progressEl) progressEl.style.width = '0%';
                    if (verifyBtn) verifyBtn.disabled = true;
                    if (resendBtn) resendBtn.disabled = true;
                    if (resendTimerEl) resendTimerEl.textContent = 'Mã OTP đã hết hạn.';
                }

                syncResendCooldown();
            }

            if (!resendUntil || resendUntil < Date.now()) {
                sessionStorage.removeItem(resendCooldownKey);
                resendUntil = 0;
            }

            if (resendBtn) {
                resendBtn.addEventListener('click', () => {
                    const cooldownUntil = Date.now() + 60000;
                    sessionStorage.setItem(resendCooldownKey, String(cooldownUntil));
                    resendUntil = cooldownUntil;
                    syncResendCooldown();
                });
            }

            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
