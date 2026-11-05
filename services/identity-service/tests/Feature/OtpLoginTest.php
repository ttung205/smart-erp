<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpLoginTest extends TestCase
{
    use RefreshDatabase;

    private string $code;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, DemoUserSeeder::class]);
        $this->code = (string) DB::table('users')->where('email', 'sale@erp.local')->value('employee_code');
    }

    private function sentOtp(): string
    {
        $otp = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        return (string) $otp;
    }

    public function test_demo_users_get_employee_codes_with_company_prefix(): void
    {
        $this->assertMatchesRegularExpression('/^ER-\d{4}$/', $this->code);
        $this->assertSame(0, DB::table('users')->whereNull('employee_code')->count());
    }

    public function test_password_login_with_employee_code(): void
    {
        $this->postJson('/api/login', ['employee_code' => strtolower($this->code), 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('data.user.employee_code', $this->code);
        $this->postJson('/api/login', ['employee_code' => $this->code, 'password' => 'sai-mat-khau'])
            ->assertUnauthorized()->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
        $this->postJson('/api/login', ['employee_code' => 'ZZ-0000', 'password' => 'password123'])
            ->assertUnauthorized()->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_otp_flow_logs_in_once(): void
    {
        Mail::fake();
        $this->postJson('/api/login/otp/request', ['employee_code' => $this->code])->assertOk();
        $otp = $this->sentOtp();

        $this->postJson('/api/login/otp/verify', ['employee_code' => $this->code, 'otp' => $otp])
            ->assertOk()->assertJsonPath('data.user.employee_code', $this->code);
        $this->postJson('/api/login/otp/verify', ['employee_code' => $this->code, 'otp' => $otp])
            ->assertUnauthorized()->assertJsonPath('error.code', 'INVALID_OTP');
    }

    public function test_unknown_employee_code_gets_same_response_and_no_email(): void
    {
        Mail::fake();
        $known = $this->postJson('/api/login/otp/request', ['employee_code' => $this->code])->assertOk()->json('data.message');
        $unknown = $this->postJson('/api/login/otp/request', ['employee_code' => 'ZZ-0000'])->assertOk()->json('data.message');

        $this->assertSame($known, $unknown);
        Mail::assertSent(LoginOtpMail::class, 1);
    }

    public function test_otp_is_locked_after_too_many_wrong_attempts(): void
    {
        Mail::fake();
        $this->postJson('/api/login/otp/request', ['employee_code' => $this->code])->assertOk();
        $otp = $this->sentOtp();
        $wrong = $otp === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login/otp/verify', ['employee_code' => $this->code, 'otp' => $wrong])->assertUnauthorized();
        }
        // Đã hết lượt thử: ngay cả OTP đúng cũng bị từ chối.
        $this->postJson('/api/login/otp/verify', ['employee_code' => $this->code, 'otp' => $otp])->assertUnauthorized();
    }

    public function test_expired_otp_is_rejected(): void
    {
        Mail::fake();
        $this->postJson('/api/login/otp/request', ['employee_code' => $this->code])->assertOk();
        $otp = $this->sentOtp();
        DB::table('login_otps')->update(['expires_at' => now()->subSecond()]);

        $this->postJson('/api/login/otp/verify', ['employee_code' => $this->code, 'otp' => $otp])->assertUnauthorized();
    }

    public function test_resend_within_cooldown_does_not_send_second_email(): void
    {
        Mail::fake();
        $this->postJson('/api/login/otp/request', ['employee_code' => $this->code])->assertOk();
        $this->postJson('/api/login/otp/request', ['employee_code' => $this->code])->assertOk();

        Mail::assertSent(LoginOtpMail::class, 1);
    }

    public function test_otp_is_stored_hashed_only(): void
    {
        Mail::fake();
        $this->postJson('/api/login/otp/request', ['employee_code' => $this->code])->assertOk();
        $otp = $this->sentOtp();

        $this->assertNotSame($otp, DB::table('login_otps')->value('code_hash'));
    }

    public function test_inactive_user_cannot_request_otp(): void
    {
        Mail::fake();
        DB::table('users')->where('email', 'sale@erp.local')->update(['is_active' => 0]);
        $this->postJson('/api/login/otp/request', ['employee_code' => $this->code])->assertOk();

        Mail::assertNothingSent();
    }

    public function test_otp_must_be_six_digits(): void
    {
        $this->postJson('/api/login/otp/verify', ['employee_code' => $this->code, 'otp' => 'abc'])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_mail_failure_returns_503_and_discards_otp(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $this->postJson('/api/login/otp/request', ['employee_code' => $this->code])
            ->assertStatus(503)->assertJsonPath('error.code', 'DEPENDENCY_UNAVAILABLE');
        $this->assertSame(0, DB::table('login_otps')->count());
    }

    public function test_company_prefix_is_validated_and_new_users_get_codes(): void
    {
        $token = $this->postJson('/api/login', ['employee_code' => 'admin@erp.local', 'password' => 'password123'])->json('data.token');

        $this->withToken($token)->putJson('/api/company', ['name' => 'Cong ty', 'employee_code_prefix' => 'ABC'])
            ->assertStatus(422);
        $this->withToken($token)->putJson('/api/company', ['name' => 'Cong ty', 'employee_code_prefix' => 'ac'])
            ->assertOk()->assertJsonPath('data.employee_code_prefix', 'AC');

        $id = $this->withToken($token)->postJson('/api/users', ['name' => 'Nhan vien moi', 'email' => 'new@example.com'])
            ->assertCreated()->json('data.id');
        $this->assertMatchesRegularExpression('/^AC-\d{4}$/', (string) DB::table('users')->where('id', $id)->value('employee_code'));
    }

    public function test_email_html_escapes_content_and_contains_otp(): void
    {
        $html = (new LoginOtpMail('<b>An</b>', 'AC-2005', '123456', 5, 'Cong ty & Co'))->renderHtml();

        $this->assertStringNotContainsString('<b>An</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;An&lt;/b&gt;', $html);
        $this->assertStringContainsString('AC-2005', $html);
        $this->assertStringContainsString('Cong ty &amp; Co', $html);
    }
}
