<?php

function buildOtpEmailTemplate(string $title, string $subtitle, string $otp, string $purpose, string $expiresText = '10 phút'): string
{
    $brand = 'DienMayPro';
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin:0;padding:0;background:#eef4ff;font-family:Arial,Helvetica,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:radial-gradient(circle at top,#e0ecff 0,#eef4ff 45%,#f8fbff 100%);padding:36px 12px;">
            <tr>
                <td align="center">
                    <table width="640" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:28px;overflow:hidden;box-shadow:0 30px 80px rgba(15,23,42,.14);border:1px solid #dbe7ff;">
                        <tr>
                            <td style="background:linear-gradient(135deg,#0f5fe6 0%,#0b4fbf 55%,#082b66 100%);padding:38px 40px;color:#fff;text-align:center;position:relative;">
                                <div style="display:inline-block;padding:8px 18px;border-radius:999px;background:rgba(255,255,255,.14);font-size:12px;font-weight:bold;letter-spacing:.16em;text-transform:uppercase;">' . $brand . '</div>
                                <h1 style="margin:18px 0 0 0;font-size:30px;line-height:1.15;letter-spacing:-.02em;">' . htmlspecialchars($title) . '</h1>
                                <p style="margin:10px 0 0 0;font-size:14px;color:rgba(255,255,255,.9);line-height:1.7;">' . htmlspecialchars($subtitle) . '</p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:34px 38px 30px;">
                                <div style="background:#f8fbff;border:1px solid #dbe7ff;border-radius:20px;padding:18px 20px;margin-bottom:22px;">
                                    <p style="margin:0;font-size:14px;color:#334155;line-height:1.8;">' . htmlspecialchars($purpose) . '</p>
                                </div>
                                <div style="margin:28px 0 26px;text-align:center;">
                                    <div style="display:inline-block;min-width:250px;padding:18px 28px;border-radius:22px;background:linear-gradient(180deg,#eff6ff,#ffffff);border:1px solid #bfdbfe;box-shadow:0 10px 24px rgba(37,99,235,.08);">
                                        <div style="font-size:11px;font-weight:700;color:#2563eb;letter-spacing:.22em;text-transform:uppercase;margin-bottom:10px;">Mã xác minh</div>
                                        <div style="font-size:38px;font-weight:900;letter-spacing:12px;color:#0f5fe6;">' . htmlspecialchars($otp) . '</div>
                                    </div>
                                </div>
                                <div style="display:grid;grid-template-columns:1fr;gap:12px;">
                                    <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:16px;padding:16px 18px;">
                                        <p style="margin:0;font-size:13px;color:#075985;line-height:1.7;"><b>Thời hạn:</b> ' . htmlspecialchars($expiresText) . '</p>
                                    </div>
                                    <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:16px;padding:16px 18px;">
                                        <p style="margin:0;font-size:13px;color:#9a3412;line-height:1.7;"><b>Bảo mật:</b> Nếu bạn không thực hiện thao tác này, hãy bỏ qua email ngay lập tức.</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:18px 38px 34px;text-align:center;border-top:1px solid #e2e8f0;background:#fbfdff;">
                                <p style="margin:0;font-size:12px;color:#64748b;">© ' . $brand . ' • Email được gửi tự động, vui lòng không trả lời</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}
