<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Email gửi mã OTP đăng nhập. HTML dựng bằng bảng + style nội tuyến để hiển thị ổn định trên Gmail, Outlook, di động. */
class LoginOtpMail extends Mailable
{
    public function __construct(
        public readonly string $employeeName,
        public readonly string $employeeCode,
        public readonly string $otp,
        public readonly int $ttlMinutes,
        public readonly string $companyName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Mã xác thực đăng nhập {$this->companyName}: {$this->otp}");
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->renderHtml());
    }

    public function renderHtml(): string
    {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name = $e($this->employeeName);
        $code = $e($this->employeeCode);
        $company = $e($this->companyName);
        $otp = $e($this->otp);
        $ttl = $this->ttlMinutes;

        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mã xác thực đăng nhập</title>
</head>
<body style="margin:0;padding:0;background:#F8FAFC;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8FAFC;padding:32px 16px;">
  <tr><td align="center">
    <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:6px;font-family:'Segoe UI',Arial,Helvetica,sans-serif;">
      <tr><td style="padding:24px 32px;border-bottom:1px solid #E2E8F0;">
        <span style="font-size:18px;font-weight:700;color:#0F172A;">{$company}</span>
        <span style="display:block;font-size:12px;color:#64748B;margin-top:2px;">Hệ thống quản trị nguồn lực doanh nghiệp</span>
      </td></tr>
      <tr><td style="padding:32px 32px 8px 32px;">
        <p style="margin:0 0 8px 0;font-size:16px;color:#0F172A;">Xin chào <strong>{$name}</strong>,</p>
        <p style="margin:0;font-size:14px;line-height:22px;color:#334155;">
          Bạn vừa yêu cầu đăng nhập bằng mã nhân viên <strong style="color:#0F172A;">{$code}</strong>.
          Nhập mã xác thực dưới đây để hoàn tất đăng nhập:
        </p>
      </td></tr>
      <tr><td align="center" style="padding:24px 32px;">
        <!-- Cả 6 số nằm trong MỘT phần tử văn bản liền mạch: nhấn đúp để chọn, sao chép ra đúng "123456" -->
        <table role="presentation" cellpadding="0" cellspacing="0"><tr>
          <td align="center" style="padding:12px 24px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;">
            <span style="font-family:Consolas,'Courier New',monospace;font-size:32px;line-height:40px;font-weight:700;letter-spacing:8px;color:#4F46E5;-webkit-user-select:all;user-select:all;">{$otp}</span>
          </td>
        </tr></table>
        <p style="margin:8px 0 0 0;font-size:12px;color:#64748B;text-align:center;">Nhấn đúp vào mã để chọn rồi sao chép.</p>
      </td></tr>
      <tr><td style="padding:0 32px 24px 32px;">
        <p style="margin:0;font-size:13px;line-height:20px;color:#334155;">
          Mã có hiệu lực trong <strong>{$ttl} phút</strong> và chỉ dùng được một lần.
        </p>
      </td></tr>
      <tr><td style="padding:0 32px 32px 32px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:6px;">
          <tr><td style="padding:12px 16px;font-size:12px;line-height:18px;color:#B45309;">
            <strong>Lưu ý bảo mật:</strong> không chia sẻ mã này cho bất kỳ ai, kể cả người xưng là nhân viên hỗ trợ.
            Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email và báo cho quản trị viên.
          </td></tr>
        </table>
      </td></tr>
      <tr><td style="padding:16px 32px;border-top:1px solid #E2E8F0;font-size:11px;line-height:16px;color:#64748B;">
        Đây là email tự động, vui lòng không trả lời. &copy; {$company}
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }
}
