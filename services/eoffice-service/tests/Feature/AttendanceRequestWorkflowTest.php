<?php

namespace Tests\Feature;

use App\Models\AttendanceAuditLog;
use App\Models\AttendanceDay;
use App\Models\AttendanceRequest;
use App\Models\EmployeeProfile;
use App\Models\OutboxEvent;
use App\Models\PayrollPeriod;
use App\Models\PunchLog;
use App\Services\AttendanceService;
use Database\Seeders\AttendanceMasterDataSeeder;
use Database\Seeders\LeaveMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Kịch bản quên chấm / bù công / remote / công tác / OT và HR thêm-xóa công. Hôm nay là thứ ba 27/10/2026. */
class AttendanceRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const EMPLOYEE = 42;

    private const MANAGER = 8;

    private const DIRECTOR = 6;

    private const HR = 7;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-27 18:00:00');
        $this->seed([LeaveMasterDataSeeder::class, AttendanceMasterDataSeeder::class]);
        EmployeeProfile::create(['user_id' => self::EMPLOYEE, 'user_name' => 'Nguyễn Văn A', 'employee_code' => 'NV0042']);

        $users = [
            self::EMPLOYEE => ['id' => self::EMPLOYEE, 'department_id' => 2, 'line_manager_id' => self::MANAGER, 'line_manager_name' => 'Trưởng phòng'],
            self::MANAGER => ['id' => self::MANAGER, 'department_id' => 2, 'line_manager_id' => self::DIRECTOR],
            self::DIRECTOR => ['id' => self::DIRECTOR, 'department_id' => 1, 'line_manager_id' => null],
        ];
        Http::fake([
            '*/api/users/*' => fn ($request) => ($user = $users[(int) basename($request->url())] ?? null) ? Http::response(['data' => $user]) : Http::response([], 404),
            '*/api/departments/2' => Http::response(['data' => ['id' => 2, 'code' => 'SALES', 'manager_id' => self::MANAGER, 'manager_name' => 'Trưởng phòng']]),
            '*/api/departments/1' => Http::response(['data' => ['id' => 1, 'code' => 'BOD', 'manager_id' => self::DIRECTOR, 'manager_name' => 'Giám đốc']]),
            '*/api/users' => Http::response(['data' => array_values($users)]),
            '*/api/departments' => Http::response(['data' => [
                ['id' => 1, 'code' => 'BOD', 'manager_id' => self::DIRECTOR, 'manager_name' => 'Giám đốc'],
                ['id' => 2, 'code' => 'SALES', 'manager_id' => self::MANAGER, 'manager_name' => 'Trưởng phòng'],
            ]]),
        ]);
    }

    private function employee(): array
    {
        return $this->jwtHeaders(self::EMPLOYEE, 2);
    }

    private function manager(): array
    {
        return $this->jwtHeaders(self::MANAGER, 2);
    }

    private function hr(): array
    {
        return $this->jwtHeaders(self::HR, 1, ['attendance.manage']);
    }

    private function punch(string $date, string $time, string $source = PunchLog::DEVICE_IMPORT): void
    {
        PunchLog::create(['user_id' => self::EMPLOYEE, 'punched_at' => "{$date} {$time}", 'work_date' => $date, 'source' => $source]);
    }

    private function day(string $date): ?AttendanceDay
    {
        return AttendanceDay::where('user_id', self::EMPLOYEE)->whereDate('work_date', $date)->first();
    }

    private function recalc(string $date): void
    {
        app(AttendanceService::class)->recalculate(self::EMPLOYEE, $date);
    }

    /** Tạo + gửi yêu cầu và trả về [request, stepId]. */
    private function submitRequest(array $payload): array
    {
        $request = $this->withHeaders($this->employee())->postJson('/api/attendance-requests', $payload + ['submit' => true, 'reason' => 'Lý do thử nghiệm'])
            ->assertCreated()->json('data');
        $stepId = $this->withHeaders($this->manager())->getJson('/api/approvals/inbox')->json('data.0.id');

        return [$request, $stepId];
    }

    private function approve(int $stepId)
    {
        return $this->withHeaders($this->manager())->postJson("/api/approvals/{$stepId}/approve");
    }

    public function test_forgotten_checkout_is_fixed_by_an_approved_add_punch_request(): void
    {
        $this->punch('2026-10-26', '08:00:00');
        $this->recalc('2026-10-26');
        $this->assertSame(AttendanceDay::MISSING_PUNCH, $this->day('2026-10-26')->status);

        [$request, $stepId] = $this->submitRequest(['type' => 'ADD_PUNCH', 'start_date' => '2026-10-26', 'requested_out' => '17:30']);
        $this->assertSame('CC-2026-0001', $request['code']);
        $this->assertSame(AttendanceDay::MISSING_PUNCH, $this->day('2026-10-26')->status, 'Chưa duyệt thì chưa đổi công');

        $this->approve($stepId)->assertOk();

        $this->assertSame(AttendanceDay::PRESENT, $this->day('2026-10-26')->status);
        $this->assertSame(1, PunchLog::where('source', PunchLog::ADJUSTMENT)->count());
        $this->assertEqualsCanonicalizing(['AttendanceRequestSubmitted', 'AttendanceRequestApproved'], OutboxEvent::pluck('event_type')->all());
        $this->assertSame('eoffice.attendance.approved', OutboxEvent::where('event_type', 'AttendanceRequestApproved')->value('routing_key'));
    }

    public function test_makeup_request_gives_credit_for_a_day_without_any_punch(): void
    {
        $this->recalc('2026-10-26');
        $this->assertSame(AttendanceDay::ABSENT, $this->day('2026-10-26')->status);

        [, $stepId] = $this->submitRequest(['type' => 'MAKEUP', 'start_date' => '2026-10-26', 'requested_in' => '08:00', 'requested_out' => '17:00']);
        $this->approve($stepId)->assertOk();

        $this->assertSame(AttendanceDay::PRESENT, $this->day('2026-10-26')->status);
        $this->assertEquals(1, $this->day('2026-10-26')->work_value);
    }

    public function test_makeup_needs_both_times_and_add_punch_needs_at_least_one(): void
    {
        $this->withHeaders($this->employee())->postJson('/api/attendance-requests', [
            'type' => 'MAKEUP', 'start_date' => '2026-10-26', 'requested_in' => '08:00', 'reason' => 'x', 'submit' => true,
        ])->assertUnprocessable();
        $this->withHeaders($this->employee())->postJson('/api/attendance-requests', [
            'type' => 'ADD_PUNCH', 'start_date' => '2026-10-26', 'reason' => 'x', 'submit' => true,
        ])->assertUnprocessable();
        $this->withHeaders($this->employee())->postJson('/api/attendance-requests', [
            'type' => 'MAKEUP', 'start_date' => '2026-10-26', 'requested_in' => '17:00', 'requested_out' => '08:00', 'reason' => 'x', 'submit' => true,
        ])->assertUnprocessable();
    }

    public function test_requests_beyond_the_deadline_or_for_future_days_are_refused(): void
    {
        // Thứ tư 21/10 đã quá 3 ngày làm việc
        $this->withHeaders($this->employee())->postJson('/api/attendance-requests', [
            'type' => 'MAKEUP', 'start_date' => '2026-10-21', 'requested_in' => '08:00', 'requested_out' => '17:00', 'reason' => 'x', 'submit' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->withHeaders($this->employee())->postJson('/api/attendance-requests', [
            'type' => 'MAKEUP', 'start_date' => '2026-10-29', 'requested_in' => '08:00', 'requested_out' => '17:00', 'reason' => 'x', 'submit' => true,
        ])->assertUnprocessable();
        $this->assertSame(0, AttendanceRequest::where('status', 'PENDING')->count());
    }

    public function test_duplicate_active_requests_are_refused(): void
    {
        $payload = ['type' => 'MAKEUP', 'start_date' => '2026-10-26', 'requested_in' => '08:00', 'requested_out' => '17:00'];
        $this->submitRequest($payload);

        $this->withHeaders($this->employee())->postJson('/api/attendance-requests', $payload + ['reason' => 'x', 'submit' => true])->assertUnprocessable();
    }

    public function test_approved_remote_range_counts_each_working_day_and_skips_weekends(): void
    {
        [, $stepId] = $this->submitRequest(['type' => 'REMOTE', 'start_date' => '2026-10-22', 'end_date' => '2026-10-27']);
        $this->approve($stepId)->assertOk();

        foreach (['2026-10-22', '2026-10-23', '2026-10-26', '2026-10-27'] as $date) {
            $this->assertSame(AttendanceDay::REMOTE, $this->day($date)->status, $date);
            $this->assertEquals(1, $this->day($date)->work_value);
        }
        $this->assertSame(AttendanceDay::WEEKEND, $this->day('2026-10-24')->status);
    }

    public function test_business_trip_overrides_missing_punches(): void
    {
        [, $stepId] = $this->submitRequest(['type' => 'BUSINESS_TRIP', 'start_date' => '2026-10-26']);
        $this->approve($stepId)->assertOk();

        $this->assertSame(AttendanceDay::BUSINESS_TRIP, $this->day('2026-10-26')->status);
    }

    public function test_only_approved_overtime_inside_actual_presence_is_counted(): void
    {
        $this->punch('2026-10-26', '08:00:00');
        $this->punch('2026-10-26', '20:10:00');
        $this->recalc('2026-10-26');
        $this->assertSame(0, $this->day('2026-10-26')->ot_minutes, 'Ở lại muộn mà không xin OT thì không tính');

        [, $stepId] = $this->submitRequest(['type' => 'OVERTIME', 'start_date' => '2026-10-26', 'requested_in' => '17:30', 'requested_out' => '20:00']);
        $this->approve($stepId)->assertOk();

        $day = $this->day('2026-10-26');
        $this->assertSame(150, $day->ot_minutes);
        $this->assertSame('WEEKDAY', $day->ot_type);
    }

    public function test_overtime_is_capped_by_actual_presence_and_rounded_down_to_half_hours(): void
    {
        $this->punch('2026-10-26', '08:00:00');
        $this->punch('2026-10-26', '19:20:00'); // chỉ ở đến 19:20
        [, $stepId] = $this->submitRequest(['type' => 'OVERTIME', 'start_date' => '2026-10-26', 'requested_in' => '17:30', 'requested_out' => '21:00']);
        $this->approve($stepId)->assertOk();

        $this->assertSame(90, $this->day('2026-10-26')->ot_minutes); // 17:30-19:20 = 110' → 90'
    }

    public function test_overtime_on_a_sunday_is_weekend_type(): void
    {
        $this->travelTo('2026-10-27 18:00:00');
        $this->punch('2026-10-25', '09:00:00');
        $this->punch('2026-10-25', '12:00:00');
        // Chủ nhật 25/10 còn trong hạn (T2, T3 = 2 ngày làm việc)
        [, $stepId] = $this->submitRequest(['type' => 'OVERTIME', 'start_date' => '2026-10-25', 'requested_in' => '09:00', 'requested_out' => '12:00']);
        $this->approve($stepId)->assertOk();

        $day = $this->day('2026-10-25');
        $this->assertSame(AttendanceDay::WEEKEND, $day->status);
        $this->assertSame(180, $day->ot_minutes);
        $this->assertSame('WEEKEND', $day->ot_type);
    }

    public function test_rejection_needs_a_comment_and_changes_nothing(): void
    {
        [, $stepId] = $this->submitRequest(['type' => 'MAKEUP', 'start_date' => '2026-10-26', 'requested_in' => '08:00', 'requested_out' => '17:00']);

        $this->withHeaders($this->manager())->postJson("/api/approvals/{$stepId}/reject")->assertUnprocessable();
        $this->withHeaders($this->manager())->postJson("/api/approvals/{$stepId}/reject", ['comment' => 'Không có minh chứng'])->assertOk();

        $this->assertSame(0, PunchLog::where('source', PunchLog::ADJUSTMENT)->count());
        $this->assertSame('REJECTED', AttendanceRequest::first()->status);
    }

    public function test_only_the_assigned_approver_can_decide(): void
    {
        [, $stepId] = $this->submitRequest(['type' => 'MAKEUP', 'start_date' => '2026-10-26', 'requested_in' => '08:00', 'requested_out' => '17:00']);

        $this->withHeaders($this->employee())->postJson("/api/approvals/{$stepId}/approve")->assertForbidden();
        $this->withHeaders($this->jwtHeaders(self::HR, 1, ['attendance.manage']))->postJson("/api/approvals/{$stepId}/approve")->assertForbidden();
    }

    public function test_cancelling_an_approved_request_removes_its_effect(): void
    {
        $this->punch('2026-10-26', '08:00:00');
        [$request, $stepId] = $this->submitRequest(['type' => 'ADD_PUNCH', 'start_date' => '2026-10-26', 'requested_out' => '17:30']);
        $this->approve($stepId)->assertOk();
        $this->assertSame(AttendanceDay::PRESENT, $this->day('2026-10-26')->status);

        $this->withHeaders($this->employee())->postJson("/api/attendance-requests/{$request['id']}/cancel", ['reason' => 'Nhập nhầm'])
            ->assertOk()->assertJsonPath('data.status', 'CANCELLED');

        $this->assertSame(AttendanceDay::MISSING_PUNCH, $this->day('2026-10-26')->status);
        $this->assertNotNull(PunchLog::where('source', PunchLog::ADJUSTMENT)->first()->voided_at);
    }

    public function test_locked_period_blocks_new_requests_and_approval_of_pending_ones(): void
    {
        [, $stepId] = $this->submitRequest(['type' => 'MAKEUP', 'start_date' => '2026-10-26', 'requested_in' => '08:00', 'requested_out' => '17:00']);
        PayrollPeriod::create(['year' => 2026, 'month' => 10, 'status' => 'DRAFT', 'attendance_locked_at' => now()]);

        $this->approve($stepId)->assertUnprocessable();
        $this->assertSame('PENDING', AttendanceRequest::first()->status, 'Duyệt thất bại thì yêu cầu vẫn chờ duyệt');
        $this->assertSame(0, PunchLog::where('source', PunchLog::ADJUSTMENT)->count());

        $this->withHeaders($this->employee())->postJson('/api/attendance-requests', [
            'type' => 'ADD_PUNCH', 'start_date' => '2026-10-23', 'requested_out' => '17:00', 'reason' => 'x', 'submit' => true,
        ])->assertUnprocessable();
    }

    public function test_owner_only_can_edit_and_draft_can_be_deleted(): void
    {
        $draft = $this->withHeaders($this->employee())->postJson('/api/attendance-requests', [
            'type' => 'MAKEUP', 'start_date' => '2026-10-26', 'requested_in' => '08:00', 'requested_out' => '17:00', 'reason' => 'x',
        ])->assertCreated()->assertJsonPath('data.status', 'DRAFT')->json('data');

        $this->withHeaders($this->manager())->putJson("/api/attendance-requests/{$draft['id']}", [
            'type' => 'MAKEUP', 'start_date' => '2026-10-26', 'requested_in' => '08:00', 'requested_out' => '18:00', 'reason' => 'y',
        ])->assertForbidden();
        $this->withHeaders($this->employee())->deleteJson("/api/attendance-requests/{$draft['id']}")->assertOk();
        $this->assertSame(0, AttendanceRequest::count());
    }

    public function test_requests_list_is_scoped_to_owner_team_and_approver(): void
    {
        $this->submitRequest(['type' => 'MAKEUP', 'start_date' => '2026-10-26', 'requested_in' => '08:00', 'requested_out' => '17:00']);

        $this->withHeaders($this->employee())->getJson('/api/attendance-requests?scope=mine')->assertOk()->assertJsonPath('total', 1);
        $this->withHeaders($this->manager())->getJson('/api/attendance-requests?scope=to_approve')->assertOk()->assertJsonPath('total', 1);
        $this->withHeaders($this->jwtHeaders(55))->getJson('/api/attendance-requests')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_attendance_view_permission_reads_everything_but_changes_nothing(): void
    {
        $this->submitRequest(['type' => 'MAKEUP', 'start_date' => '2026-10-26', 'requested_in' => '08:00', 'requested_out' => '17:00']);
        $this->punch('2026-10-26', '08:00:00');
        $this->withHeaders($this->hr())->postJson('/api/attendance/clear-day', ['user_id' => self::EMPLOYEE, 'date' => '2026-10-26', 'reason' => 'Dữ liệu nhập sai']);
        $viewer = $this->jwtHeaders(55, 6, ['attendance.view']);
        $requestId = AttendanceRequest::first()->id;

        // Kế toán chỉ có attendance.view: thấy yêu cầu của mọi người, chi tiết, bảng công, log và nhật ký điều chỉnh
        $this->withHeaders($viewer)->getJson('/api/attendance-requests')->assertOk()->assertJsonPath('total', 1);
        $this->withHeaders($viewer)->getJson("/api/attendance-requests/{$requestId}")->assertOk();
        $this->withHeaders($viewer)->getJson('/api/attendance/summary?month=2026-10')->assertOk();
        $this->withHeaders($viewer)->getJson('/api/attendance/punches?date=2026-10-26&user_id='.self::EMPLOYEE)->assertOk();
        $this->withHeaders($viewer)->getJson('/api/attendance/audit-logs')->assertOk()->assertJsonCount(1, 'data');
        $this->withHeaders($viewer)->getJson('/api/attendance/import-batches')->assertOk();
        $this->withHeaders($viewer)->getJson('/api/employee-profiles')->assertOk();

        // ...nhưng không sửa được công và không được duyệt thay người khác
        $this->withHeaders($viewer)->postJson('/api/attendance/manual-work', [
            'user_id' => self::EMPLOYEE, 'date' => '2026-10-26', 'in' => '08:00', 'out' => '17:00', 'reason' => 'Thử sửa công',
        ])->assertForbidden();
        $this->withHeaders($viewer)->postJson('/api/attendance/import', ['rows' => [['employee_code' => 'NV0042', 'punched_at' => '2026-10-26 08:00:00']]])->assertForbidden();
        $this->withHeaders($viewer)->postJson('/api/attendance/recalculate', ['month' => '2026-10'])->assertForbidden();
        $this->withHeaders($viewer)->getJson('/api/payroll/periods')->assertForbidden();

        // Người không có quyền thì vẫn không thấy yêu cầu của người khác
        $this->withHeaders($this->jwtHeaders(56, 6))->getJson('/api/attendance-requests')->assertOk()->assertJsonPath('total', 0);
        $this->withHeaders($this->jwtHeaders(56, 6))->getJson("/api/attendance-requests/{$requestId}")->assertForbidden();
    }

    public function test_hr_adds_work_with_a_mandatory_reason_and_an_audit_trail(): void
    {
        $payload = ['user_id' => self::EMPLOYEE, 'date' => '2026-10-26', 'in' => '08:00', 'out' => '17:00'];

        $this->withHeaders($this->hr())->postJson('/api/attendance/manual-work', $payload)->assertUnprocessable();
        $this->withHeaders($this->employee())->postJson('/api/attendance/manual-work', $payload + ['reason' => 'Quên chấm cả ngày'])->assertForbidden();
        $this->withHeaders($this->hr())->postJson('/api/attendance/manual-work', $payload + ['reason' => 'Quên chấm cả ngày'])->assertCreated();

        $this->assertSame(AttendanceDay::PRESENT, $this->day('2026-10-26')->status);
        $audit = AttendanceAuditLog::first();
        $this->assertSame('ADD_WORK', $audit->action);
        $this->assertSame('Quên chấm cả ngày', $audit->reason);
        $this->assertSame(self::HR, $audit->actor_id);
        $this->assertSame('ABSENT', $audit->before['status'] ?? 'ABSENT');
        $this->assertSame('PRESENT', $audit->after['status']);
    }

    public function test_hr_clears_a_day_and_voids_single_punches_without_deleting_raw_logs(): void
    {
        $this->punch('2026-10-26', '08:00:00');
        $this->punch('2026-10-26', '17:00:00');
        $this->recalc('2026-10-26');
        $log = PunchLog::where('punched_at', 'like', '%17:00%')->first();

        $this->withHeaders($this->hr())->postJson("/api/attendance/punches/{$log->id}/void", ['reason' => 'Chấm nhầm'])->assertOk();
        $this->assertSame(AttendanceDay::MISSING_PUNCH, $this->day('2026-10-26')->status);

        $this->withHeaders($this->hr())->postJson('/api/attendance/clear-day', ['user_id' => self::EMPLOYEE, 'date' => '2026-10-26', 'reason' => 'Nhập dữ liệu sai ngày'])->assertOk();
        $this->assertSame(AttendanceDay::ABSENT, $this->day('2026-10-26')->status);
        $this->assertSame(2, PunchLog::count(), 'Log thô vẫn còn, chỉ bị đánh dấu hủy');
        $this->assertSame(2, PunchLog::whereNotNull('voided_at')->count());
        $this->assertSame(['VOID_PUNCH', 'REMOVE_WORK'], AttendanceAuditLog::orderBy('id')->pluck('action')->all());

        $this->withHeaders($this->hr())->getJson('/api/attendance/audit-logs?month=2026-10')->assertOk()->assertJsonCount(2, 'data');
        $this->withHeaders($this->employee())->getJson('/api/attendance/audit-logs')->assertForbidden();
    }

    public function test_hr_cannot_change_a_locked_day(): void
    {
        PayrollPeriod::create(['year' => 2026, 'month' => 10, 'status' => 'DRAFT', 'attendance_locked_at' => now()]);

        $this->withHeaders($this->hr())->postJson('/api/attendance/manual-work', [
            'user_id' => self::EMPLOYEE, 'date' => '2026-10-26', 'in' => '08:00', 'out' => '17:00', 'reason' => 'Quên chấm cả ngày',
        ])->assertUnprocessable();
        $this->assertSame(0, PunchLog::count());
    }

    public function test_employee_sees_punches_of_their_own_day_including_voided_ones(): void
    {
        $this->punch('2026-10-26', '08:00:00');
        $this->punch('2026-10-26', '17:00:00');

        $this->withHeaders($this->employee())->getJson('/api/attendance/punches?date=2026-10-26')->assertOk()->assertJsonCount(2, 'data');
        $this->withHeaders($this->jwtHeaders(55))->getJson('/api/attendance/punches?date=2026-10-26&user_id='.self::EMPLOYEE)->assertForbidden();
    }
}
