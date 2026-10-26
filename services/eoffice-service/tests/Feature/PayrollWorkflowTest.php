<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\AttendanceRequest;
use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\OutboxEvent;
use App\Models\PayrollPeriod;
use App\Models\PayrollSlip;
use App\Models\PunchLog;
use Carbon\CarbonPeriod;
use Database\Seeders\AttendanceMasterDataSeeder;
use Database\Seeders\LeaveMasterDataSeeder;
use Database\Seeders\PayrollRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Kỳ lương tháng 9/2026 (22 ngày làm việc, 1-2/9 là ngày lễ có lương). Số liệu kỳ vọng tính tay:
 * A đủ công · B vắng 2 ngày · C trễ 3 ngày · D chưa có lương cơ bản.
 */
class PayrollWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const A = 101;

    private const B = 102;

    private const C = 103;

    private const D = 104;

    private const HR = 7;

    private const APPROVER = 6;

    private const ACCOUNTANT = 9;

    /** Mã trả về của finance-service cho bút toán lương (đổi giữa các bước test). */
    private int $journalStatus = 403;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-27 09:00:00');
        $this->seed([LeaveMasterDataSeeder::class, AttendanceMasterDataSeeder::class, PayrollRuleSeeder::class]);

        foreach ([[self::A, 'NV0101', 15000000], [self::B, 'NV0102', 15000000], [self::C, 'NV0103', 10000000], [self::D, 'NV0104', 0]] as [$id, $code, $salary]) {
            EmployeeProfile::create(['user_id' => $id, 'user_name' => "Nhân viên {$code}", 'employee_code' => $code, 'base_salary' => $salary]);
        }
        $this->punchSeptember(self::A);
        $this->punchSeptember(self::B, absent: ['2026-09-10', '2026-09-11']);
        $this->punchSeptember(self::C, lateOn: ['2026-09-07', '2026-09-08', '2026-09-09']);
        $this->punchSeptember(self::D);
    }

    private function hr(): array
    {
        return $this->jwtHeaders(self::HR, 1, ['payroll.manage', 'attendance.manage']);
    }

    private function approver(): array
    {
        return $this->jwtHeaders(self::APPROVER, 1, ['payroll.approve']);
    }

    private function accountant(): array
    {
        return $this->jwtHeaders(self::ACCOUNTANT, 1, ['payroll.pay']);
    }

    /** Chấm 08:00-17:00 mọi ngày làm việc của tháng 9 trừ ngày lễ và ngày vắng; ngày trễ vào 08:30. */
    private function punchSeptember(int $userId, array $absent = [], array $lateOn = []): void
    {
        foreach (CarbonPeriod::create('2026-09-01', '2026-09-30') as $day) {
            $date = $day->toDateString();
            if ($day->isWeekend() || in_array($date, ['2026-09-01', '2026-09-02'], true) || in_array($date, $absent, true)) {
                continue;
            }
            $in = in_array($date, $lateOn, true) ? '08:30:00' : '08:00:00';
            foreach ([$in, '17:00:00'] as $time) {
                PunchLog::create(['user_id' => $userId, 'punched_at' => "{$date} {$time}", 'work_date' => $date, 'source' => PunchLog::DEVICE_IMPORT]);
            }
        }
    }

    private function seedHolidays(): void
    {
        Holiday::updateOrCreate(['date' => '2026-09-01'], ['name' => 'Quốc khánh']);
        Holiday::updateOrCreate(['date' => '2026-09-02'], ['name' => 'Quốc khánh']);
    }

    /** Tạo kỳ, khóa công, tính lương → trả về id kỳ. */
    private function calculatedPeriod(): int
    {
        $this->seedHolidays();
        $id = $this->withHeaders($this->hr())->postJson('/api/payroll/periods', ['year' => 2026, 'month' => 9])->assertCreated()->json('data.id');
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/lock-attendance")->assertOk();
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/calculate")->assertOk();

        return $id;
    }

    private function slip(int $periodId, int $userId): PayrollSlip
    {
        return PayrollSlip::where('payroll_period_id', $periodId)->where('user_id', $userId)->firstOrFail();
    }

    public function test_attendance_cannot_be_locked_before_the_month_ends_and_calculation_needs_the_lock(): void
    {
        $this->travelTo('2026-09-15 09:00:00');
        $id = $this->withHeaders($this->hr())->postJson('/api/payroll/periods', ['year' => 2026, 'month' => 9])->json('data.id');

        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/lock-attendance")->assertUnprocessable();
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/calculate")->assertUnprocessable();
    }

    public function test_calculation_matches_hand_computed_slips(): void
    {
        $this->seedHolidays();
        $id = $this->withHeaders($this->hr())->postJson('/api/payroll/periods', ['year' => 2026, 'month' => 9])->json('data.id');
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/lock-attendance")->assertOk()
            ->assertJsonPath('summary.missing_punch_days', 0);
        $response = $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/calculate")->assertOk();

        $this->assertStringContainsString('Nhân viên NV0104', $response->json('warnings.0'));
        $a = $this->slip($id, self::A);
        $this->assertSame(22.0, $a->standard_days);
        $this->assertSame(22.0, $a->paid_days);
        $this->assertSame(15000000.0, $a->salary_amount);
        $this->assertSame(1575000.0, $a->insurance_amount);
        $this->assertSame(121250.0, $a->pit_amount);
        $this->assertSame(13303750.0, $a->net_pay);

        $b = $this->slip($id, self::B);
        $this->assertSame(20.0, $b->paid_days);
        $this->assertSame(2, $b->absent_days);
        $this->assertSame(13636364.0, $b->salary_amount);
        $this->assertSame(53068.0, $b->pit_amount);
        $this->assertSame(12008296.0, $b->net_pay);

        $c = $this->slip($id, self::C);
        $this->assertSame(3, $c->late_count);
        $this->assertSame(50000.0, $c->penalty_amount);
        $this->assertSame(0.0, $c->pit_amount);
        $this->assertSame(8900000.0, $c->net_pay);

        $this->assertSame(3, PayrollSlip::where('payroll_period_id', $id)->count(), 'D không có lương cơ bản nên không có phiếu');
        $period = PayrollPeriod::find($id);
        $this->assertSame('CALCULATED', $period->status);
        $this->assertSame(38636364.0, $period->total_gross);
        $this->assertSame(174318.0, $period->total_pit);
        $this->assertSame(34212046.0, $period->total_net);
    }

    public function test_overtime_late_penalty_and_leave_flow_into_the_slip(): void
    {
        // A làm thêm 2 giờ ngày thường (đã duyệt) và B nghỉ không lương 1 ngày
        AttendanceRequest::create([
            'code' => 'CC-2026-0001', 'type' => 'OVERTIME', 'user_id' => self::A, 'user_name' => 'A', 'start_date' => '2026-09-14', 'end_date' => '2026-09-14',
            'requested_in' => '17:00', 'requested_out' => '19:00', 'reason' => 'Chốt đơn', 'status' => 'APPROVED',
        ]);
        PunchLog::where('user_id', self::A)->whereDate('work_date', '2026-09-14')->where('punched_at', 'like', '%17:00%')->update(['punched_at' => '2026-09-14 19:05:00']);

        $id = $this->calculatedPeriod();

        $a = $this->slip($id, self::A);
        $this->assertSame(2.0, $a->ot_hours_weekday);
        // 15.000.000 ÷ 22 ÷ 8 = 85.227,27 × 2 giờ × 1,5
        $this->assertSame(255682.0, $a->ot_amount);
        $this->assertSame(15255682.0, $a->gross_income);
    }

    public function test_full_approval_flow_with_segregation_of_duties_and_return(): void
    {
        $id = $this->calculatedPeriod();

        $this->withHeaders($this->approver())->postJson("/api/payroll/periods/{$id}/approve")->assertUnprocessable(); // chưa gửi duyệt
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'PENDING_APPROVAL');

        // Không sửa/tính lại được khi đang chờ duyệt
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/calculate")->assertUnprocessable();
        // Người lập bảng lương không được tự duyệt dù có quyền
        $both = $this->jwtHeaders(self::HR, 1, ['payroll.manage', 'payroll.approve']);
        $this->withHeaders($both)->postJson("/api/payroll/periods/{$id}/approve")->assertUnprocessable();
        // Người chỉ có quyền lập không duyệt được
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/approve")->assertForbidden();

        $this->withHeaders($this->approver())->postJson("/api/payroll/periods/{$id}/return")->assertUnprocessable(); // thiếu lý do
        $this->withHeaders($this->approver())->postJson("/api/payroll/periods/{$id}/return", ['comment' => 'Kiểm tra lại phạt đi trễ'])
            ->assertOk()->assertJsonPath('data.status', 'RETURNED');

        // Sau khi trả lại: tính lại rồi gửi duyệt lần nữa
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/calculate")->assertOk();
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/submit")->assertOk();
        $this->withHeaders($this->approver())->postJson("/api/payroll/periods/{$id}/approve", ['comment' => 'Đồng ý'])
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');

        // Bảng lương đã duyệt là bất biến
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/calculate")->assertUnprocessable();
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/unlock-attendance", ['reason' => 'Sửa công sau duyệt'])->assertUnprocessable();
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/adjustments", [
            'user_id' => self::A, 'type' => 'BONUS', 'amount' => 1000000, 'reason' => 'Thưởng',
        ])->assertUnprocessable();

        $actions = PayrollPeriod::find($id)->approvalHistories->pluck('action')->all();
        $this->assertSame(['ATTENDANCE_LOCKED', 'CALCULATED', 'SUBMITTED', 'RETURNED', 'CALCULATED', 'SUBMITTED', 'APPROVED'], $actions);
    }

    public function test_events_announce_the_flow_without_leaking_any_salary_figure(): void
    {
        $id = $this->calculatedPeriod();
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/submit")->assertOk();
        $this->withHeaders($this->approver())->postJson("/api/payroll/periods/{$id}/approve")->assertOk();

        $events = OutboxEvent::orderBy('id')->get();
        $this->assertSame(['PayrollSubmitted', 'PayrollApproved', 'PayslipPublished'], $events->pluck('event_type')->all());
        $this->assertSame(['eoffice.payroll.submitted', 'eoffice.payroll.approved', 'eoffice.payroll.slip_published'], $events->pluck('routing_key')->all());
        foreach ($events as $event) {
            $this->assertDoesNotMatchRegularExpression('/net_pay|gross|salary|total_net|13303750/', $event->payload);
        }
        $this->assertEqualsCanonicalizing([self::A, self::B, self::C], json_decode($events[2]->payload, true)['payload']['recipient_ids']);
    }

    public function test_employees_see_only_their_own_approved_slip_and_payroll_data_needs_strict_permission(): void
    {
        $id = $this->calculatedPeriod();
        $slipA = $this->slip($id, self::A);
        $employeeA = $this->jwtHeaders(self::A, 2);

        $this->withHeaders($employeeA)->getJson('/api/payroll/my-slips')->assertOk()->assertJsonCount(0, 'data');
        $this->withHeaders($employeeA)->getJson("/api/payroll/slips/{$slipA->id}")->assertForbidden(); // kỳ chưa duyệt

        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/submit");
        $this->withHeaders($this->approver())->postJson("/api/payroll/periods/{$id}/approve")->assertOk();

        $this->withHeaders($employeeA)->getJson('/api/payroll/my-slips')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.net_pay', 13303750);
        $this->withHeaders($employeeA)->getJson("/api/payroll/slips/{$slipA->id}")->assertOk();
        $this->withHeaders($this->jwtHeaders(self::B, 2))->getJson("/api/payroll/slips/{$slipA->id}")->assertForbidden();
        $this->withHeaders($employeeA)->getJson('/api/payroll/periods')->assertForbidden();

        // eoffice.manage không đi tắt vào dữ liệu lương; SUPER_ADMIN và payroll.view thì được
        $officeAdmin = $this->jwtHeaders(55, 1, ['eoffice.manage']);
        $this->withHeaders($officeAdmin)->getJson('/api/payroll/periods')->assertForbidden();
        $this->withHeaders($officeAdmin)->getJson("/api/payroll/slips/{$slipA->id}")->assertForbidden();
        $this->withHeaders($this->jwtHeaders(56, 1, [], ['SUPER_ADMIN']))->getJson('/api/payroll/periods')->assertOk();
        $this->withHeaders($this->jwtHeaders(57, 1, ['payroll.view']))->getJson("/api/payroll/periods/{$id}")->assertOk()->assertJsonCount(3, 'slips');
    }

    public function test_manual_adjustments_force_a_recalculation_and_feed_the_slip(): void
    {
        $id = $this->calculatedPeriod();

        $adjustment = $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/adjustments", [
            'user_id' => self::A, 'type' => 'BONUS', 'amount' => 2000000, 'reason' => 'Thưởng dự án',
        ])->assertCreated()->json('data');
        $this->assertSame('DRAFT', PayrollPeriod::find($id)->status, 'Điều chỉnh làm bảng lương không còn khớp, buộc tính lại');
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/submit")->assertUnprocessable();

        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/adjustments", [
            'user_id' => self::A, 'type' => 'DEDUCTION', 'amount' => 500000, 'reason' => 'Trừ tạm ứng',
        ])->assertCreated();
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/calculate")->assertOk();

        $a = $this->slip($id, self::A);
        $this->assertSame(2000000.0, $a->bonus_amount);
        $this->assertSame(500000.0, $a->deduction_amount);
        $this->assertSame(17000000.0, $a->gross_income);
        // thu nhập chịu thuế = 17tr − 1,575tr − 11tr = 4,425tr → thuế 5%
        $this->assertSame(221250.0, $a->pit_amount);
        $this->assertSame(17000000.0 - 1575000 - 221250 - 500000, $a->net_pay);

        $this->withHeaders($this->hr())->deleteJson("/api/payroll/adjustments/{$adjustment['id']}")->assertOk();
        $this->assertSame('DRAFT', PayrollPeriod::find($id)->status);
        $this->withHeaders($this->jwtHeaders(55, 1, ['attendance.manage']))->postJson("/api/payroll/periods/{$id}/adjustments", [])->assertForbidden();
    }

    public function test_unlocking_attendance_reopens_days_and_discards_stale_slips(): void
    {
        $id = $this->calculatedPeriod();
        $this->assertSame(3, PayrollSlip::count());
        $hr = $this->hr();
        $payload = ['user_id' => self::B, 'date' => '2026-09-10', 'in' => '08:00', 'out' => '17:00', 'reason' => 'Quên chấm công'];

        $this->withHeaders($hr)->postJson('/api/attendance/manual-work', $payload)->assertUnprocessable(); // đang khóa
        $this->withHeaders($hr)->postJson("/api/payroll/periods/{$id}/unlock-attendance")->assertUnprocessable(); // thiếu lý do
        $this->withHeaders($hr)->postJson("/api/payroll/periods/{$id}/unlock-attendance", ['reason' => 'Bổ sung công bị sót'])->assertOk()
            ->assertJsonPath('data.status', 'DRAFT');

        $this->assertSame(0, PayrollSlip::count());
        $this->assertSame(0, AttendanceDay::whereNotNull('locked_at')->count());
        $this->withHeaders($hr)->postJson('/api/attendance/manual-work', $payload)->assertCreated();

        $this->withHeaders($hr)->postJson("/api/payroll/periods/{$id}/lock-attendance")->assertOk();
        $this->withHeaders($hr)->postJson("/api/payroll/periods/{$id}/calculate")->assertOk();
        $this->assertSame(21.0, $this->slip($id, self::B)->paid_days, 'B có thêm 1 công sau khi bổ sung');
    }

    public function test_rule_changes_are_validated_and_change_the_next_calculation(): void
    {
        $id = $this->calculatedPeriod();
        $manager = $this->hr();

        $this->withHeaders($manager)->putJson('/api/payroll/rules/pit', ['value' => [
            'personal_deduction' => 11000000, 'dependent_deduction' => 4400000,
            'brackets' => [['up_to' => 10000000, 'rate' => 0.05], ['up_to' => 5000000, 'rate' => 0.1], ['up_to' => null, 'rate' => 0.2]],
        ]])->assertUnprocessable();
        $this->withHeaders($manager)->putJson('/api/payroll/rules/unknown', ['value' => ['x' => 1]])->assertNotFound();
        $this->withHeaders($this->approver())->putJson('/api/payroll/rules/late_penalty', ['value' => ['free_count' => 0, 'amount_per_violation' => 1]])->assertForbidden();

        $this->withHeaders($manager)->putJson('/api/payroll/rules/late_penalty', ['value' => ['free_count' => 0, 'amount_per_violation' => 100000]])->assertOk();
        $this->withHeaders($manager)->postJson("/api/payroll/periods/{$id}/calculate")->assertOk();

        $this->assertSame(300000.0, $this->slip($id, self::C)->penalty_amount); // 3 lần × 100.000
    }

    public function test_paying_posts_the_journal_and_voucher_to_finance_and_is_retryable(): void
    {
        $id = $this->calculatedPeriod();
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/submit")->assertOk();
        $this->withHeaders($this->approver())->postJson("/api/payroll/periods/{$id}/approve")->assertOk();

        // Kế toán chưa có quyền ghi sổ bên finance → thất bại có thông báo, kỳ vẫn APPROVED
        Http::fake([
            '*/api/payroll-journals' => fn () => Http::response(['message' => 'forbidden'], $this->journalStatus),
            '*/api/vouchers/9/post' => Http::response(['data' => ['id' => 9, 'status' => 'POSTED', 'voucher_number' => 'BC-202610-00001']]),
            '*/api/vouchers' => Http::response(['data' => ['id' => 9, 'status' => 'DRAFT', 'voucher_number' => 'BC-202610-00001']], 201),
        ]);
        $this->withHeaders($this->hr())->postJson("/api/payroll/periods/{$id}/pay", ['bank_account_id' => 1])->assertForbidden();
        $this->withHeaders($this->accountant())->postJson("/api/payroll/periods/{$id}/pay", ['bank_account_id' => 1])->assertUnprocessable()
            ->assertJsonValidationErrors('finance');
        $period = PayrollPeriod::find($id);
        $this->assertSame('APPROVED', $period->status);
        $this->assertSame('FAILED', $period->finance_status);

        $this->journalStatus = 201;
        $this->withHeaders($this->accountant())->postJson("/api/payroll/periods/{$id}/pay", ['bank_account_id' => 1])->assertOk()
            ->assertJsonPath('data.status', 'PAID')->assertJsonPath('data.finance_reference', 'BC-202610-00001');

        $journal = null;
        $voucher = null;
        Http::assertSent(function (HttpRequest $request) use (&$journal, &$voucher) {
            str_ends_with($request->url(), '/api/payroll-journals') && $journal = $request->data();
            str_ends_with($request->url(), '/api/vouchers') && $voucher = $request->data();

            return true;
        });
        $this->assertSame('2026-09', $journal['reference']);
        $this->assertSame('2026-09-30', $journal['entry_date']);
        $byCredit = collect($journal['lines'])->keyBy('credit_account_code');
        $this->assertSame(['debit' => '642', 'amount' => 38636364.0], ['debit' => $byCredit['334']['debit_account_code'], 'amount' => (float) $byCredit['334']['amount']]);
        $this->assertSame(4200000.0, (float) $byCredit['338']['amount']);
        $this->assertSame(174318.0, (float) $byCredit['3335']['amount']);
        $this->assertSame(50000.0, (float) $byCredit['711']['amount']);
        $this->assertArrayNotHasKey('138', $byCredit->all(), 'Không có khoản khấu trừ thủ công thì không có dòng 138');
        $this->assertSame(34212046.0, (float) $voucher['total_amount']);
        $this->assertSame('BANK_PAYMENT', $voucher['voucher_type']);
        // Cân đối: chi phí = giữ lại + thực nhận
        $this->assertEqualsWithDelta(38636364.0, 4200000 + 174318 + 50000 + 34212046, 0.5);

        // Chi lại lần nữa không gọi finance thêm
        $this->withHeaders($this->accountant())->postJson("/api/payroll/periods/{$id}/pay", ['bank_account_id' => 1])->assertOk();
        $this->assertSame('PAID', PayrollPeriod::find($id)->status);
        $this->assertContains('PayrollPaid', OutboxEvent::pluck('event_type')->all());
    }

    public function test_cannot_pay_a_period_that_is_not_approved(): void
    {
        $id = $this->calculatedPeriod();
        Http::fake();

        $this->withHeaders($this->accountant())->postJson("/api/payroll/periods/{$id}/pay", ['bank_account_id' => 1])->assertUnprocessable();
        Http::assertNothingSent();
    }
}
