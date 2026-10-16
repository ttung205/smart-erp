<?php

namespace Tests\Feature;

use App\Models\LeaveType;
use App\Models\OutboxEvent;
use Database\Seeders\LeaveMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeaveRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const EMPLOYEE = 42;

    private const MANAGER = 8;

    private const DIRECTOR = 6;

    private const TEAM_LEAD = 51;

    /** Dữ liệu GET /api/users/{id} của identity-service (sửa được trong từng test). */
    private array $identityUsers = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 09:00:00'); // Thứ năm
        $this->seed(LeaveMasterDataSeeder::class);
        $this->identityUsers = [
            self::EMPLOYEE => ['id' => self::EMPLOYEE, 'department_id' => 2, 'line_manager_id' => self::MANAGER, 'line_manager_name' => 'Trưởng Phòng Kinh Doanh'],
            self::MANAGER => ['id' => self::MANAGER, 'department_id' => 2, 'line_manager_id' => self::DIRECTOR, 'line_manager_name' => 'Giám Đốc'],
            self::DIRECTOR => ['id' => self::DIRECTOR, 'department_id' => 1, 'line_manager_id' => null],
        ];
        Http::fake([
            '*/api/users/*' => function ($request) {
                $user = $this->identityUsers[(int) basename($request->url())] ?? null;

                return $user ? Http::response(['success' => true, 'data' => $user]) : Http::response(['success' => false], 404);
            },
            '*/api/departments/2' => Http::response(['success' => true, 'data' => ['id' => 2, 'code' => 'SALES', 'manager_id' => self::MANAGER, 'manager_name' => 'Trưởng Phòng Kinh Doanh']]),
            '*/api/departments/1' => Http::response(['success' => true, 'data' => ['id' => 1, 'code' => 'BOD', 'manager_id' => self::DIRECTOR, 'manager_name' => 'Giám Đốc']]),
            '*/api/users' => Http::response(['success' => true, 'data' => [
                ['id' => self::EMPLOYEE, 'name' => 'Lê Văn Nhân Viên', 'email' => 'nv@erp.local', 'is_active' => 1, 'department_name' => 'Phòng Kinh Doanh', 'line_manager_id' => self::MANAGER],
                ['id' => self::MANAGER, 'name' => 'Trưởng Phòng Kinh Doanh', 'email' => 'tp@erp.local', 'is_active' => 1, 'department_name' => 'Phòng Kinh Doanh', 'line_manager_id' => self::DIRECTOR],
                ['id' => 99, 'name' => 'Nhân Viên Đã Nghỉ Việc', 'email' => 'old@erp.local', 'is_active' => 0, 'department_name' => null],
            ]]),
            '*/api/departments' => Http::response(['success' => true, 'data' => [
                ['id' => 1, 'code' => 'BOD', 'manager_id' => self::DIRECTOR, 'manager_name' => 'Giám Đốc'],
                ['id' => 2, 'code' => 'SALES', 'manager_id' => self::MANAGER, 'manager_name' => 'Trưởng Phòng Kinh Doanh'],
            ]]),
        ]);
    }

    public function test_employee_submits_and_manager_approves_annual_leave(): void
    {
        $employee = $this->jwtHeaders(self::EMPLOYEE);
        // Thứ 6 23/10 chiều → Thứ 3 27/10 sáng: 0,5 + T2 1 + 0,5 = 2 ngày (bỏ T7, CN)
        $request = $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload([
            'start_date' => '2026-10-23', 'start_session' => 'AFTERNOON', 'end_date' => '2026-10-27', 'end_session' => 'MORNING',
        ]) + ['submit' => true])->assertCreated()
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.total_days', 2)
            ->assertJsonPath('data.code', 'NP-2026-0001')
            ->json('data');

        $this->withHeaders($employee)->getJson('/api/leave-balances/me')->assertOk()
            ->assertJsonPath('data.pending_days', 2)->assertJsonPath('data.remaining_days', 10);

        $manager = $this->jwtHeaders(self::MANAGER);
        $stepId = $this->withHeaders($manager)->getJson('/api/approvals/inbox')->assertOk()
            ->assertJsonPath('pending_count', 1)
            ->assertJsonPath('data.0.document.code', 'NP-2026-0001')
            ->json('data.0.id');

        // Người khác không duyệt được
        $this->withHeaders($employee)->postJson("/api/approvals/{$stepId}/approve")->assertForbidden();

        $this->withHeaders($manager)->postJson("/api/approvals/{$stepId}/approve", ['comment' => 'Đồng ý'])->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->assertDatabaseHas('leave_requests', ['id' => $request['id'], 'status' => 'APPROVED']);
        $this->withHeaders($employee)->getJson('/api/leave-balances/me')->assertOk()
            ->assertJsonPath('data.used_days', 2)->assertJsonPath('data.pending_days', 0)->assertJsonPath('data.remaining_days', 10);
        $this->withHeaders($employee)->getJson("/api/leave-requests/{$request['id']}")->assertOk()
            ->assertJsonCount(3, 'data.approval_histories');

        // Outbox: gửi duyệt báo người duyệt, quyết định báo người làm đơn
        $events = OutboxEvent::orderBy('id')->get();
        $this->assertSame(['eoffice.leave.submitted', 'eoffice.leave.approved'], $events->pluck('routing_key')->all());
        $submitted = json_decode($events[0]->payload, true)['payload'];
        $this->assertSame(self::MANAGER, $submitted['approver']['id']);
        $this->assertSame($stepId, $submitted['step_id']);
        $this->assertSame('NP-2026-0001', $submitted['code']);
        $approved = json_decode($events[1]->payload, true);
        $this->assertSame('LeaveRequestApproved', $approved['event_type']);
        $this->assertSame(self::EMPLOYEE, $approved['payload']['requester_id']);
        $this->assertSame('Đồng ý', $approved['payload']['comment']);
        $this->assertSame(self::MANAGER, $approved['payload']['actor']['id']);

        // Gọi identity-service không có token người dùng nên phải kèm khóa nội bộ
        Http::assertSent(fn ($request) => $request->hasHeader('X-Internal-Service-Key', 'phpunit-internal-key'));
    }

    public function test_reject_requires_comment_and_releases_balance(): void
    {
        $employee = $this->jwtHeaders(self::EMPLOYEE);
        $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload() + ['submit' => true])->assertCreated();
        $manager = $this->jwtHeaders(self::MANAGER);
        $stepId = $this->withHeaders($manager)->getJson('/api/approvals/inbox')->json('data.0.id');

        $this->withHeaders($manager)->postJson("/api/approvals/{$stepId}/reject")->assertUnprocessable()->assertJsonValidationErrors('comment');
        $this->withHeaders($manager)->postJson("/api/approvals/{$stepId}/reject", ['comment' => 'Cuối tháng chốt số liệu'])->assertOk();

        $this->withHeaders($employee)->getJson('/api/leave-balances/me')
            ->assertJsonPath('data.pending_days', 0)->assertJsonPath('data.used_days', 0);
        $this->withHeaders($manager)->postJson("/api/approvals/{$stepId}/approve")->assertUnprocessable();
    }

    public function test_returned_request_can_be_edited_and_resubmitted(): void
    {
        $employee = $this->jwtHeaders(self::EMPLOYEE);
        $id = $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload() + ['submit' => true])->json('data.id');
        $manager = $this->jwtHeaders(self::MANAGER);
        $stepId = $this->withHeaders($manager)->getJson('/api/approvals/inbox')->json('data.0.id');
        $this->withHeaders($manager)->postJson("/api/approvals/{$stepId}/return", ['comment' => 'Bổ sung người bàn giao'])->assertOk();

        $this->withHeaders($employee)->putJson("/api/leave-requests/{$id}", $this->payload(['handover_user_name' => 'Lê Văn B']))->assertOk();
        $this->withHeaders($employee)->postJson("/api/leave-requests/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'PENDING');
        $this->withHeaders($manager)->getJson('/api/approvals/inbox')->assertJsonPath('pending_count', 1);
    }

    public function test_overlapping_request_and_exceeding_balance_are_rejected(): void
    {
        $employee = $this->jwtHeaders(self::EMPLOYEE);
        $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload([
            'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'start_session' => 'MORNING',
        ]) + ['submit' => true])->assertCreated();

        // Buổi chiều cùng ngày: không trùng
        $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload([
            'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'start_session' => 'AFTERNOON',
        ]) + ['submit' => true])->assertCreated();

        $draft = $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload([
            'start_date' => '2026-10-19', 'end_date' => '2026-10-21',
        ]))->assertCreated()->json('data.id');
        $this->withHeaders($employee)->postJson("/api/leave-requests/{$draft}/submit")
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');

        // 11/11 → 30/11: 14 ngày làm việc, vượt quỹ còn 11 ngày
        $tooLong = $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload([
            'start_date' => '2026-11-11', 'end_date' => '2026-11-30',
        ]))->json('data.id');
        $this->withHeaders($employee)->postJson("/api/leave-requests/{$tooLong}/submit")
            ->assertUnprocessable()->assertJsonValidationErrors('total_days');

        // Nghỉ không lương thì không trừ quỹ
        $unpaid = LeaveType::where('code', 'UNPAID')->value('id');
        $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload([
            'leave_type_id' => $unpaid, 'start_date' => '2026-11-11', 'end_date' => '2026-11-30',
        ]) + ['submit' => true])->assertCreated()->assertJsonPath('data.status', 'PENDING');
    }

    public function test_holidays_are_excluded_and_type_limit_is_enforced(): void
    {
        $employee = $this->jwtHeaders(self::EMPLOYEE);
        $this->withHeaders($employee)->postJson('/api/leave-requests/preview', [
            'start_date' => '2026-04-27', 'end_date' => '2026-05-04',
        ])->assertOk()->assertJsonPath('data.total_days', 3); // 27/4, 30/4, 1/5 nghỉ lễ → còn 28, 29/4, 4/5

        $wedding = LeaveType::where('code', 'WEDDING')->value('id');
        $id = $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload([
            'leave_type_id' => $wedding, 'start_date' => '2026-11-02', 'end_date' => '2026-11-05',
        ]))->json('data.id');
        $this->withHeaders($employee)->postJson("/api/leave-requests/{$id}/submit")
            ->assertUnprocessable()->assertJsonValidationErrors('total_days');
    }

    public function test_manager_request_goes_to_director_and_director_is_auto_approved(): void
    {
        $this->withHeaders($this->jwtHeaders(self::MANAGER))->postJson('/api/leave-requests', $this->payload() + ['submit' => true])
            ->assertCreated()->assertJsonPath('data.status', 'PENDING');
        $this->withHeaders($this->jwtHeaders(self::DIRECTOR, 1))->getJson('/api/approvals/inbox')->assertJsonPath('pending_count', 1);

        $this->withHeaders($this->jwtHeaders(self::DIRECTOR, 1))->postJson('/api/leave-requests', $this->payload([
            'start_date' => '2026-11-02', 'end_date' => '2026-11-02',
        ]) + ['submit' => true])->assertCreated()->assertJsonPath('data.status', 'APPROVED');
    }

    public function test_cancel_approved_request_before_start_restores_balance(): void
    {
        $employee = $this->jwtHeaders(self::EMPLOYEE);
        $id = $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload() + ['submit' => true])->json('data.id');
        $manager = $this->jwtHeaders(self::MANAGER);
        $stepId = $this->withHeaders($manager)->getJson('/api/approvals/inbox')->json('data.0.id');
        $this->withHeaders($manager)->postJson("/api/approvals/{$stepId}/approve")->assertOk();

        $this->withHeaders($manager)->postJson("/api/leave-requests/{$id}/cancel")->assertForbidden();
        $this->withHeaders($employee)->postJson("/api/leave-requests/{$id}/cancel", ['reason' => 'Đổi lịch'])->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');
        $this->withHeaders($employee)->getJson('/api/leave-balances/me')->assertJsonPath('data.used_days', 0);

        Carbon::setTestNow('2026-10-20 09:00:00');
        $id = $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload([
            'start_date' => '2026-10-20', 'end_date' => '2026-10-20',
        ]) + ['submit' => true])->json('data.id');
        $stepId = $this->withHeaders($manager)->getJson('/api/approvals/inbox')->json('data.0.id');
        $this->withHeaders($manager)->postJson("/api/approvals/{$stepId}/approve")->assertOk();
        $this->withHeaders($employee)->postJson("/api/leave-requests/{$id}/cancel")->assertUnprocessable();
    }

    public function test_line_manager_takes_priority_over_department_manager(): void
    {
        $this->identityUsers[self::EMPLOYEE]['line_manager_id'] = self::TEAM_LEAD;
        $this->identityUsers[self::EMPLOYEE]['line_manager_name'] = 'Trưởng Nhóm Bán Lẻ';

        $this->withHeaders($this->jwtHeaders(self::EMPLOYEE))->postJson('/api/leave-requests', $this->payload() + ['submit' => true])->assertCreated();

        $this->withHeaders($this->jwtHeaders(self::TEAM_LEAD))->getJson('/api/approvals/inbox')->assertJsonPath('pending_count', 1);
        $this->withHeaders($this->jwtHeaders(self::MANAGER))->getJson('/api/approvals/inbox')->assertJsonPath('pending_count', 0);
    }

    public function test_employee_without_line_manager_falls_back_to_department_manager(): void
    {
        $this->identityUsers[self::EMPLOYEE]['line_manager_id'] = null;

        $this->withHeaders($this->jwtHeaders(self::EMPLOYEE))->postJson('/api/leave-requests', $this->payload() + ['submit' => true])->assertCreated();

        $this->withHeaders($this->jwtHeaders(self::MANAGER))->getJson('/api/approvals/inbox')->assertJsonPath('pending_count', 1);
    }

    public function test_leave_list_shows_own_team_and_pending_approvals(): void
    {
        $employee = $this->jwtHeaders(self::EMPLOYEE);
        $manager = $this->jwtHeaders(self::MANAGER);
        $draftId = $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload())->json('data.id');
        $submittedId = $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload([
            'start_date' => '2026-10-22', 'end_date' => '2026-10-22',
        ]) + ['submit' => true])->json('data.id');
        // Người ngoài nhóm (không phải cấp dưới, không gán duyệt cho trưởng phòng)
        $this->identityUsers[77] = ['id' => 77, 'department_id' => 1, 'line_manager_id' => self::DIRECTOR];
        $outsiderId = $this->withHeaders($this->jwtHeaders(77, 1))->postJson('/api/leave-requests', $this->payload() + ['submit' => true])->json('data.id');

        // Trưởng phòng: thấy đơn đã gửi của cấp dưới, không thấy nháp, không thấy đơn của người ngoài
        $this->withHeaders($manager)->getJson('/api/leave-requests')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $submittedId)->assertJsonPath('pending_count', 1);
        $this->withHeaders($manager)->getJson('/api/leave-requests?scope=to_approve')->assertJsonCount(1, 'data');
        $this->withHeaders($manager)->getJson('/api/leave-requests?scope=team')->assertJsonCount(1, 'data');
        $this->withHeaders($manager)->getJson('/api/leave-requests?scope=mine')->assertJsonCount(0, 'data');
        $this->withHeaders($manager)->getJson("/api/leave-requests/{$submittedId}")->assertOk();
        $this->withHeaders($manager)->getJson("/api/leave-requests/{$draftId}")->assertForbidden();
        $this->withHeaders($manager)->getJson("/api/leave-requests/{$outsiderId}")->assertForbidden();

        // Nhân viên: thấy cả nháp của mình
        $this->withHeaders($employee)->getJson('/api/leave-requests')->assertJsonCount(2, 'data')->assertJsonPath('pending_count', 0);

        // Super Admin: thấy mọi đơn đã gửi của toàn công ty
        $this->withHeaders($this->jwtHeaders(1, 1, roles: ['SUPER_ADMIN']))->getJson('/api/leave-requests')->assertJsonCount(2, 'data');
    }

    public function test_super_admin_sees_leave_calendar_of_all_departments(): void
    {
        $this->withHeaders($this->jwtHeaders(self::EMPLOYEE))->postJson('/api/leave-requests', $this->payload() + ['submit' => true])->assertCreated();
        $this->withHeaders($this->jwtHeaders(self::DIRECTOR, 1))->postJson('/api/leave-requests', $this->payload() + ['submit' => true])->assertCreated();

        // Nhân viên thường chỉ thấy phòng mình, kể cả khi cố truyền department_id khác
        $this->withHeaders($this->jwtHeaders(self::EMPLOYEE))->getJson('/api/leave-calendar?month=2026-10&department_id=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user_id', self::EMPLOYEE)->assertJsonPath('can_view_all', false);

        $admin = $this->jwtHeaders(self::DIRECTOR, 1, roles: ['SUPER_ADMIN']);
        $this->withHeaders($admin)->getJson('/api/leave-calendar?month=2026-10')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('can_view_all', true);
        $this->withHeaders($admin)->getJson('/api/leave-calendar?month=2026-10&department_id=2')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_hr_sets_opening_balance_from_period_start(): void
    {
        $hr = $this->jwtHeaders(1, 1, ['eoffice.manage']);
        $employee = $this->jwtHeaders(self::EMPLOYEE);

        $this->withHeaders($hr)->getJson('/api/leave-balances')->assertOk()
            ->assertJsonCount(2, 'data') // bỏ qua nhân viên đã khóa
            ->assertJsonPath('data.0.user_name', 'Lê Văn Nhân Viên')
            ->assertJsonPath('data.0.period_start', '2026-01-01')
            ->assertJsonPath('data.0.opening_days', 12);

        $this->withHeaders($hr)->putJson('/api/leave-balances/'.self::EMPLOYEE, [
            'year' => 2026, 'period_start' => '2026-05-01', 'opening_days' => 4, 'note' => 'Số dư khi áp dụng hệ thống',
        ])->assertOk()->assertJsonPath('data.remaining_days', 4);

        // Nghỉ trước ngày đầu kỳ không được tính vào quỹ phép
        $before = $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload([
            'start_date' => '2026-04-29', 'end_date' => '2026-04-29',
        ]))->json('data.id');
        $this->withHeaders($employee)->postJson("/api/leave-requests/{$before}/submit")
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');

        $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload([
            'start_date' => '2026-10-19', 'end_date' => '2026-10-21', 'end_session' => 'MORNING',
        ]) + ['submit' => true])->assertCreated();
        $stepId = $this->withHeaders($this->jwtHeaders(self::MANAGER))->getJson('/api/approvals/inbox')->json('data.0.id');
        $this->withHeaders($this->jwtHeaders(self::MANAGER))->postJson("/api/approvals/{$stepId}/approve")->assertOk();

        $this->withHeaders($hr)->getJson('/api/leave-balances')
            ->assertJsonPath('data.0.used_days', 2.5)->assertJsonPath('data.0.remaining_days', 1.5);
        $this->withHeaders($hr)->getJson('/api/leave-balances/'.self::EMPLOYEE.'/requests')->assertOk()->assertJsonCount(1, 'data');

        // Chỉ còn 1,5 ngày → xin 2 ngày bị chặn
        $this->withHeaders($employee)->postJson('/api/leave-requests', $this->payload([
            'start_date' => '2026-11-02', 'end_date' => '2026-11-03',
        ]) + ['submit' => true])->assertUnprocessable()->assertJsonValidationErrors('total_days');
    }

    public function test_new_employee_gets_pro_rata_leave(): void
    {
        $hr = $this->jwtHeaders(1, 1, ['eoffice.manage']);
        $this->withHeaders($hr)->getJson('/api/leave-balances/pro-rata?period_start=2026-07-10')->assertJsonPath('data.opening_days', 6);
        $this->withHeaders($hr)->getJson('/api/leave-balances/pro-rata?period_start=2026-07-20')->assertJsonPath('data.opening_days', 5);

        $this->withHeaders($hr)->putJson('/api/leave-balances/'.self::EMPLOYEE, ['year' => 2026, 'period_start' => '2026-09-01'])
            ->assertOk()->assertJsonPath('data.opening_days', 4);
        $this->withHeaders($hr)->putJson('/api/leave-balances/'.self::EMPLOYEE, ['year' => 2026, 'period_start' => '2025-12-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('period_start');
    }

    public function test_admin_routes_require_permission(): void
    {
        $this->withHeaders($this->jwtHeaders())->postJson('/api/holidays', ['date' => '2026-12-31', 'name' => 'Test'])->assertForbidden();
        $this->withHeaders($this->jwtHeaders(permissions: ['eoffice.manage']))
            ->postJson('/api/holidays', ['date' => '2026-12-31', 'name' => 'Nghỉ cuối năm'])->assertCreated();
        $this->flushHeaders()->getJson('/api/leave-requests')->assertUnauthorized();
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'leave_type_id' => LeaveType::where('code', 'ANNUAL')->value('id'),
            'start_date' => '2026-10-21', 'end_date' => '2026-10-21', 'start_session' => 'FULL',
            'reason' => 'Giải quyết việc gia đình',
        ];
    }
}
