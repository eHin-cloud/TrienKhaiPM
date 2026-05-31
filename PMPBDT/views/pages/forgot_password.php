<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Service\UserService;

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userService = new UserService($db);
$message = '';
$messageType = '';
$showForm = true;
$forgot_email = '';
$resendAvailableAt = (int) (($_SESSION['reset_password_otp']['resend_available_at'] ?? 0));
$otpExpiresAt = (int) (($_SESSION['reset_password_otp']['expires_at'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'forgot_password_send_otp') {
        $forgot_email = trim($_POST['email'] ?? '');
        if (isset($_POST['resend_otp'])) {
            $result = $userService->resendPasswordResetOtp($_POST);
        } else {
            $result = $userService->requestPasswordResetOtp($_POST);
        }
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        $showForm = !$result['success'];
        $resendAvailableAt = (int) ($_SESSION['reset_password_otp']['resend_available_at'] ?? 0);
        $otpExpiresAt = (int) ($_SESSION['reset_password_otp']['expires_at'] ?? 0);
    } elseif ($action === 'forgot_password_reset') {
        $forgot_email = trim($_POST['email'] ?? '');
        $result = $userService->resetPasswordWithOtp($_POST);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        $showForm = !$result['success'];
    } elseif (isset($_POST['resend_otp'])) {
        $result = $userService->resendPasswordResetOtp($_POST);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        $showForm = !$result['success'];
        $resendAvailableAt = (int) ($_SESSION['reset_password_otp']['resend_available_at'] ?? 0);
        $otpExpiresAt = (int) ($_SESSION['reset_password_otp']['expires_at'] ?? 0);
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu - DIENMAYPRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-950 via-blue-950 to-fuchsia-950 font-sans">
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-24 left-1/4 h-80 w-80 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-[28rem] w-[28rem] rounded-full bg-fuchsia-500/20 blur-3xl"></div>
    </div>

    <main class="relative z-10 flex min-h-screen items-center justify-center px-4 py-8">
        <div class="w-full max-w-5xl overflow-hidden rounded-[30px] bg-white shadow-[0_30px_80px_-30px_rgba(15,23,42,0.6)] ring-1 ring-white/10">
            <div class="grid min-h-[620px] grid-cols-1 lg:grid-cols-[0.95fr_1.05fr]">
                <div class="relative overflow-hidden bg-gradient-to-br from-[#0f5fe6] via-[#0b4fbf] to-[#0a2f7a] p-8 text-white md:p-10">
                    <div class="absolute -right-20 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
                    <div class="absolute -bottom-24 -left-20 h-72 w-72 rounded-full bg-secondary/15"></div>
                    <div class="relative z-10 flex h-full flex-col justify-between">
                        <div>
                            <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.2em] backdrop-blur">
                                <i class="fa-solid fa-key text-secondary"></i> Khôi phục mật khẩu
                            </div>
                            <h1 class="mt-6 text-4xl font-black leading-[0.95] md:text-5xl">
                                Lấy lại tài khoản
                                <span class="block text-secondary">an toàn và nhanh chóng</span>
                            </h1>
                            <p class="mt-5 max-w-md text-sm leading-7 text-blue-100 md:text-base">
                                Nhập email, nhận OTP và đặt lại mật khẩu trong một luồng riêng, gọn gàng và dễ theo dõi.
                            </p>
                        </div>
                        <div class="grid grid-cols-3 gap-3 text-center text-sm">
                            <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                                <p class="text-2xl font-black text-secondary">3</p>
                                <p class="mt-1 text-[11px] font-semibold tracking-[0.15em] text-blue-100">BƯỚC</p>
                            </div>
                            <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                                <p class="text-2xl font-black text-secondary">10p</p>
                                <p class="mt-1 text-[11px] font-semibold tracking-[0.15em] text-blue-100">OTP</p>
                            </div>
                            <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                                <p class="text-2xl font-black text-secondary">24/7</p>
                                <p class="mt-1 text-[11px] font-semibold tracking-[0.15em] text-blue-100">HỖ TRỢ</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 p-5 sm:p-6 md:p-8 flex items-center">
                    <div class="mx-auto w-full max-w-xl">
                        <div class="mb-4">
                            <a href="/PMVSCuoi/public/index.php?route=index.php" class="inline-flex items-center gap-2 text-sm font-semibold text-primary transition hover:underline">
                                <i class="fa-solid fa-arrow-left"></i> Quay về trang chủ
                            </a>
                        </div>

                        <?php if ($message): ?>
                            <div class="mb-4 rounded-2xl border px-4 py-3 text-sm <?= $messageType === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700' ?>">
                                <i class="fa-solid <?= $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> mr-1"></i><?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>

                        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-xl md:p-8">
                            <?php if ($showForm): ?>
                                <h2 class="text-3xl font-black text-slate-800">Quên mật khẩu</h2>
                                <p class="mt-2 text-sm text-slate-500">Nhập email tài khoản để nhận mã OTP.</p>

                                <form method="POST" class="mt-6 space-y-4">
                                    <?= csrf_input_field() ?>
                                    <input type="hidden" name="action" value="forgot_password_send_otp">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Email tài khoản</label>
                                        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                                            placeholder="example@gmail.com">
                                    </div>
                                    <button type="submit" class="w-full rounded-2xl bg-primary py-3.5 font-bold text-white shadow-lg shadow-primary/20 transition hover:bg-blue-800">Gửi mã OTP</button>
                                </form>

                                <?php if (!empty($_SESSION['reset_password_otp'])): ?>
                                    <div class="mt-4 rounded-2xl border border-blue-100 bg-blue-50 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Thời gian chờ gửi lại</p>
                                                <p id="resend-countdown" class="mt-1 text-2xl font-black text-slate-800">01:00</p>
                                            </div>
                                            <div class="text-right text-xs text-slate-500">
                                                <p>OTP còn hiệu lực</p>
                                                <p><?= !empty($otpExpiresAt) ? 'Hết hạn lúc ' . date('H:i', $otpExpiresAt) : '10 phút' ?></p>
                                            </div>
                                        </div>
                                        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-blue-100">
                                            <div id="resend-progress" class="h-full w-full rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 transition-all duration-1000"></div>
                                        </div>
                                    </div>
                                    <form method="POST" class="mt-3">
                                        <?= csrf_input_field() ?>
                                        <input type="hidden" name="action" value="forgot_password_send_otp">
                                        <input type="hidden" name="email" value="<?= htmlspecialchars($forgot_email) ?>">
                                        <button type="submit" name="resend_otp" value="1" id="resend-otp-btn" class="w-full rounded-2xl border border-slate-200 bg-white py-3.5 font-semibold text-slate-700 transition hover:bg-slate-50">Gửi lại OTP qua Gmail</button>
                                        <p id="resend-hint" class="mt-2 text-center text-xs text-slate-500"></p>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <h2 class="text-3xl font-black text-slate-800">Nhập OTP và đặt lại mật khẩu</h2>
                                <p class="mt-2 text-sm text-slate-500">Kiểm tra email của bạn để lấy mã OTP.</p>

                                <form method="POST" class="mt-6 space-y-4">
                                    <?= csrf_input_field() ?>
                                    <input type="hidden" name="action" value="forgot_password_reset">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Email tài khoản</label>
                                        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Mã OTP</label>
                                        <input type="text" name="otp" required maxlength="6" pattern="\d{6}"
                                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                                            placeholder="Nhập mã 6 số">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Mật khẩu mới</label>
                                        <input type="password" name="new_password" required minlength="8"
                                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Xác nhận mật khẩu mới</label>
                                        <input type="password" name="confirm_password" required minlength="8"
                                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-800 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10">
                                    </div>
                                    <button type="submit" class="w-full rounded-2xl bg-secondary py-3.5 font-bold text-primary shadow-lg shadow-secondary/20 transition hover:bg-yellow-400">Đặt lại mật khẩu</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        (function () {
            const resendAvailableAt = <?= (int) $resendAvailableAt ?> * 1000;
            const otpExpiresAt = <?= (int) $otpExpiresAt ?> * 1000;
            const resendCountdownEl = document.getElementById('resend-countdown');
            const resendProgressEl = document.getElementById('resend-progress');
            const resendBtn = document.getElementById('resend-otp-btn');
            const resendHint = document.getElementById('resend-hint');
            const totalCooldownMs = 60 * 1000;

            function format(ms) {
                const totalSeconds = Math.max(0, Math.floor(ms / 1000));
                const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
                const seconds = String(totalSeconds % 60).padStart(2, '0');
                return `${minutes}:${seconds}`;
            }

            function tick() {
                const now = Date.now();
                const resendRemaining = Math.max(0, resendAvailableAt - now);
                const otpRemaining = Math.max(0, otpExpiresAt - now);

                if (resendCountdownEl) {
                    resendCountdownEl.textContent = resendRemaining > 0 ? format(resendRemaining) : '00:00';
                }
                if (resendProgressEl) {
                    resendProgressEl.style.width = `${Math.max(0, (resendRemaining / totalCooldownMs) * 100)}%`;
                }
                if (resendBtn) {
                    resendBtn.disabled = resendRemaining > 0 || otpRemaining <= 0;
                }
                if (resendHint) {
                    if (otpRemaining <= 0) {
                        resendHint.textContent = 'Mã OTP đã hết hạn. Vui lòng yêu cầu OTP mới.';
                    } else if (resendRemaining > 0) {
                        resendHint.textContent = `Bạn có thể gửi lại sau ${format(resendRemaining)}.`;
                    } else {
                        resendHint.textContent = '';
                    }
                }
            }

            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
