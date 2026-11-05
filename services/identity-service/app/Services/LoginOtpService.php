<?php

namespace App\Services;

use App\Mail\LoginOtpMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * OTP đăng nhập: 6 chữ số, sống 5 phút, dùng một lần, tối đa 5 lần nhập sai, gửi lại tối thiểu sau 60 giây.
 * Chỉ lưu băm HMAC của mã, không lưu mã gốc.
 */
class LoginOtpService
{
    public const TTL_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Tạo và gửi OTP cho nhân viên.
     *
     * @param  object{id:int,name:string,email:string,employee_code:string}  $user
     * @return bool false nếu đang trong thời gian chờ gửi lại (không gửi thêm)
     *
     * @throws Throwable khi gửi email thất bại; OTP vừa tạo đã bị hủy
     */
    public function issue(object $user): bool
    {
        $recent = DB::table('login_otps')
            ->where('user_id', $user->id)
            ->where('created_at', '>', now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))
            ->exists();
        if ($recent) {
            return false;
        }

        $otp = sprintf('%06d', random_int(0, 999999));

        // Mã cũ chưa dùng mất hiệu lực: mỗi nhân viên chỉ có một OTP còn sống.
        DB::table('login_otps')->where('user_id', $user->id)->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
        $otpId = DB::table('login_otps')->insertGetId([
            'user_id' => $user->id,
            'code_hash' => $this->hash((int) $user->id, $otp),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'created_at' => now(),
        ]);

        try {
            Mail::to($user->email)->send(new LoginOtpMail(
                employeeName: (string) $user->name,
                employeeCode: (string) $user->employee_code,
                otp: $otp,
                ttlMinutes: self::TTL_MINUTES,
                companyName: $this->companyName(),
            ));
        } catch (Throwable $exception) {
            DB::table('login_otps')->where('id', $otpId)->delete();
            throw $exception;
        }

        return true;
    }

    /** Kiểm tra OTP; đúng thì tiêu thụ ngay (dùng một lần). */
    public function verify(int $userId, string $otp): bool
    {
        $row = DB::table('login_otps')
            ->where('user_id', $userId)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first(['id', 'code_hash']);
        if (! $row) {
            return false;
        }

        // Tăng số lần thử bằng UPDATE có điều kiện: các request song song không vượt được giới hạn.
        $counted = DB::table('login_otps')
            ->where('id', $row->id)
            ->whereNull('consumed_at')
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->increment('attempts');
        if ($counted === 0) {
            return false;
        }

        if (! hash_equals($row->code_hash, $this->hash($userId, $otp))) {
            return false;
        }

        // Chỉ một request thắng được bước tiêu thụ.
        return DB::table('login_otps')->where('id', $row->id)->whereNull('consumed_at')
            ->update(['consumed_at' => now()]) === 1;
    }

    private function hash(int $userId, string $otp): string
    {
        return hash_hmac('sha256', $userId.':'.$otp, (string) config('app.key'));
    }

    private function companyName(): string
    {
        $row = DB::table('company_profiles')->where('id', 1)->first(['short_name', 'name']);

        return (string) ($row?->short_name ?: $row?->name ?: 'Smart ERP');
    }
}
