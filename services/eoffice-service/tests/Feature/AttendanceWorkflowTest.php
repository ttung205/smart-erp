<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PunchLog;
use App\Services\AttendanceService;
use Database\Seeders\AttendanceMasterDataSeeder;
use Database\Seeders\LeaveMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const EMPLOYEE = 42;

    private const REMOTE_EMPLOYEE = 43;

    private const MANAGER = 8;

    private const HR = 7;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-27 18:00:00'); // Thứ ba
        $this->seed([LeaveMasterDataSeeder::class, AttendanceMasterDataSeeder::class]);

        EmployeeProfile::create(['user_id' => self::EMPLOYEE, 'user_name' => 'Nguyễn Văn A', 'employee_code' => 'NV0042', 'base_salary' => 15000000]);
        EmployeeProfile::create(['user_id' => self::REMOTE_EMPLOYEE, 'user_name' => 'Trần Thị B', 'employee_code' => 'NV0043', 'work_mode' => 'REMOTE']);

        Http::fake(['*/api/users*' => Http::response(['data' => [
            ['id' => self::EMPLOYEE, 'name' => 'Nguyễn Văn A', 'line_manager_id' => self::MANAGER, 'is_active' => true],
            ['id' => self::REMOTE_EMPLOYEE, 'name' => 'Trần Thị B', 'line_manager_id' => self::MANAGER, 'is_active' => true],
            ['id' => 99, 'name' => 'Lê Văn C', 'line_manager_id' => self::MANAGER, 'is_active' => true],
        ]])]);
    }

    private function hr(): array
    {
        return $this->jwtHeaders(self::HR, 1, ['attendance.manage']);
    }

    private function importRows(array $rows, ?array $headers = null)
    {
        return $this->withHeaders($headers ?? $this->hr())->postJson('/api/attendance/import', ['file_name' => 'may-cham-cong.xlsx', 'rows' => $rows]);
    }

    public function test_import_creates_logs_and_calculates_days_then_reimport_does_not_duplicate(): void
    {
        $rows = [
            ['employee_code' => 'NV0042', 'punched_at' => '2026-10-26 08:03:10', 'device_id' => 'MCC-01'],
            ['employee_code' => 'NV0042', 'punched_at' => '2026-10-26 17:10:00', 'device_id' => 'MCC-01'],
            ['employee_code' => 'NV0042', 'punched_at' => '2026-10-23 08:30'],
            ['employee_code' => 'NV0042', 'punched_at' => '23/10/2026 17:30'],
        ];

        $first = $this->importRows($rows)->assertCreated()->json('data');
        $this->assertSame(4, $first['imported_rows']);
        $this->assertSame(0, $first['error_rows']);
        $this->assertSame(4, PunchLog::count());

        $monday = AttendanceDay::where('user_id', self::EMPLOYEE)->whereDate('work_date', '2026-10-26')->first();
        $this->assertSame(AttendanceDay::PRESENT, $monday->status);
        $this->assertSame(1.0, $monday->work_value);

        $friday = AttendanceDay::where('user_id', self::EMPLOYEE)->whereDate('work_date', '2026-10-23')->first();
        $this->assertSame(30, $friday->late_minutes);

        $again = $this->importRows($rows)->assertCreated()->json('data');
        $this->assertSame(0, $again['imported_rows']);
        $this->assertSame(4, $again['duplicate_rows']);
        $this->assertSame(4, PunchLog::count());
    }

    public function test_import_reports_bad_rows_but_keeps_the_good_ones(): void
    {
        $batch = $this->importRows([
            ['employee_code' => 'NV0042', 'punched_at' => '2026-10-26 08:00:00'],
            ['employee_code' => 'KHONG-CO', 'punched_at' => '2026-10-26 08:00:00'],
            ['employee_code' => 'NV0042', 'punched_at' => 'không phải giờ'],
            ['employee_code' => 'NV0042', 'punched_at' => '31/02/2026 08:00'],
            ['employee_code' => 'NV0042', 'punched_at' => '2026-12-31 08:00:00'],
            ['punched_at' => '2026-10-26 08:00:00'],
        ])->assertCreated()->json('data');

        $this->assertSame(1, $batch['imported_rows']);
        $this->assertSame(5, $batch['error_rows']);
        $this->assertSame([2, 3, 4, 5, 6], array_column($batch['errors'], 'row'));
        $this->assertSame(1, PunchLog::count());
    }

    public function test_import_requires_credentials_and_attendance_permission(): void
    {
        $row = [['employee_code' => 'NV0042', 'punched_at' => '2026-10-26 08:00:00']];

        $this->postJson('/api/attendance/import', ['rows' => $row])->assertUnauthorized();
        $this->importRows($row, $this->jwtHeaders(self::EMPLOYEE, 2, ['quotation.view']))->assertForbidden();
        $this->assertSame(0, PunchLog::count());
    }

    public function test_forgotten_checkout_shows_as_missing_punch_in_my_timesheet(): void
    {
        $this->importRows([['employee_code' => 'NV0042', 'punched_at' => '2026-10-26 08:00:00']])->assertCreated();

        $data = $this->withHeaders($this->jwtHeaders(self::EMPLOYEE))->getJson('/api/attendance/me?month=2026-10')->assertOk()->json('data');

        $this->assertSame(1, $data['summary']['missing_punch_days']);
        $this->assertFalse($data['can_check_in_web']);
        $monday = collect($data['days'])->firstWhere('work_date', '2026-10-26');
        $this->assertSame(AttendanceDay::MISSING_PUNCH, $monday['status']);
        // Các ngày làm việc đã qua mà không có dữ liệu là vắng
        $this->assertSame(AttendanceDay::ABSENT, collect($data['days'])->firstWhere('work_date', '2026-10-22')['status']);
    }

    public function test_only_remote_employees_can_check_in_on_the_web_with_a_one_minute_guard(): void
    {
        $this->withHeaders($this->jwtHeaders(self::EMPLOYEE))->postJson('/api/attendance/check-in')->assertUnprocessable();

        $remote = $this->jwtHeaders(self::REMOTE_EMPLOYEE);
        $this->withHeaders($remote)->postJson('/api/attendance/check-in')->assertCreated()->assertJsonPath('data.status', AttendanceDay::PENDING);
        $this->withHeaders($remote)->postJson('/api/attendance/check-in')->assertUnprocessable();

        $this->travelTo('2026-10-27 18:02:00');
        $this->withHeaders($remote)->postJson('/api/attendance/check-in')->assertCreated();
        $this->assertSame(2, PunchLog::where('user_id', self::REMOTE_EMPLOYEE)->where('source', PunchLog::WEB)->count());
    }

    public function test_approved_leave_and_holiday_are_merged_into_the_timesheet(): void
    {
        Holiday::create(['date' => '2026-10-21', 'name' => 'Ngày lễ thử nghiệm']);
        LeaveRequest::create([
            'code' => 'NP-2026-0001', 'user_id' => self::EMPLOYEE, 'user_name' => 'Nguyễn Văn A', 'department_id' => 2,
            'leave_type_id' => LeaveType::where('code', 'ANNUAL')->value('id'), 'start_date' => '2026-10-22', 'start_session' => 'FULL',
            'end_date' => '2026-10-22', 'end_session' => 'FULL', 'total_days' => 1, 'reason' => 'Việc riêng', 'status' => LeaveRequest::APPROVED,
        ]);
        LeaveRequest::create([
            'code' => 'NP-2026-0002', 'user_id' => self::EMPLOYEE, 'user_name' => 'Nguyễn Văn A', 'department_id' => 2,
            'leave_type_id' => LeaveType::where('code', 'UNPAID')->value('id'), 'start_date' => '2026-10-23', 'start_session' => 'FULL',
            'end_date' => '2026-10-23', 'end_session' => 'FULL', 'total_days' => 1, 'reason' => 'Việc riêng', 'status' => LeaveRequest::APPROVED,
        ]);
        // Đơn chưa duyệt không được tính
        LeaveRequest::create([
            'code' => 'NP-2026-0003', 'user_id' => self::EMPLOYEE, 'user_name' => 'Nguyễn Văn A', 'department_id' => 2,
            'leave_type_id' => LeaveType::where('code', 'ANNUAL')->value('id'), 'start_date' => '2026-10-20', 'start_session' => 'FULL',
            'end_date' => '2026-10-20', 'end_session' => 'FULL', 'total_days' => 1, 'reason' => 'Việc riêng', 'status' => LeaveRequest::PENDING,
        ]);

        $days = collect($this->withHeaders($this->jwtHeaders(self::EMPLOYEE))->getJson('/api/attendance/me?month=2026-10')->assertOk()->json('data.days'))->keyBy('work_date');

        $this->assertSame(AttendanceDay::HOLIDAY, $days['2026-10-21']['status']);
        $this->assertEquals(1.0, $days['2026-10-21']['work_value']);
        $this->assertSame(AttendanceDay::LEAVE_PAID, $days['2026-10-22']['status']);
        $this->assertEquals(1.0, $days['2026-10-22']['work_value']);
        $this->assertSame(AttendanceDay::LEAVE_UNPAID, $days['2026-10-23']['status']);
        $this->assertEquals(0.0, $days['2026-10-23']['work_value']);
        $this->assertSame(AttendanceDay::ABSENT, $days['2026-10-20']['status']);
        $this->assertSame(AttendanceDay::WEEKEND, $days['2026-10-24']['status']);
        $this->assertArrayNotHasKey('2026-10-28', $days->all(), 'Không tạo bản ghi cho ngày tương lai');
    }

    public function test_viewing_someone_elses_timesheet_needs_permission_or_a_reporting_line(): void
    {
        $query = '/api/attendance/days?month=2026-10&user_id='.self::EMPLOYEE;

        $this->withHeaders($this->jwtHeaders(55))->getJson($query)->assertForbidden();
        $this->withHeaders($this->jwtHeaders(self::MANAGER))->getJson($query)->assertOk();
        $this->withHeaders($this->jwtHeaders(self::HR, 1, ['attendance.view']))->getJson($query)->assertOk();
        $this->withHeaders($this->jwtHeaders(self::EMPLOYEE))->getJson($query)->assertOk();
    }

    public function test_company_summary_requires_attendance_view_and_totals_the_month(): void
    {
        $this->importRows([
            ['employee_code' => 'NV0042', 'punched_at' => '2026-10-26 08:00:00'],
            ['employee_code' => 'NV0042', 'punched_at' => '2026-10-26 17:00:00'],
        ])->assertCreated();

        $this->withHeaders($this->jwtHeaders(self::EMPLOYEE))->getJson('/api/attendance/summary?month=2026-10')->assertForbidden();

        $rows = $this->withHeaders($this->jwtHeaders(self::HR, 1, ['attendance.view']))->getJson('/api/attendance/summary?month=2026-10')
            ->assertOk()->json('data');
        $row = collect($rows)->firstWhere('employee_code', 'NV0042');
        $this->assertEquals(1.0, $row['work_days']);
    }

    public function test_a_locked_day_is_not_recalculated_unless_forced(): void
    {
        $this->importRows([
            ['employee_code' => 'NV0042', 'punched_at' => '2026-10-26 08:00:00'],
            ['employee_code' => 'NV0042', 'punched_at' => '2026-10-26 17:00:00'],
        ])->assertCreated();
        AttendanceDay::where('user_id', self::EMPLOYEE)->update(['locked_at' => now()]);

        PunchLog::where('user_id', self::EMPLOYEE)->whereDate('work_date', '2026-10-26')->where('punched_at', 'like', '%17:00%')->update(['voided_at' => now()]);
        $service = app(AttendanceService::class);

        $this->assertSame(AttendanceDay::PRESENT, $service->recalculate(self::EMPLOYEE, '2026-10-26')->status);
        $this->assertSame(AttendanceDay::MISSING_PUNCH, $service->recalculate(self::EMPLOYEE, '2026-10-26', force: true)->status);
    }

    public function test_voided_logs_are_ignored_when_calculating(): void
    {
        $this->importRows([
            ['employee_code' => 'NV0042', 'punched_at' => '2026-10-26 08:00:00'],
            ['employee_code' => 'NV0042', 'punched_at' => '2026-10-26 17:00:00'],
        ])->assertCreated();
        PunchLog::where('user_id', self::EMPLOYEE)->where('punched_at', 'like', '%17:00%')->update(['voided_at' => now()]);

        $day = app(AttendanceService::class)->recalculate(self::EMPLOYEE, '2026-10-26');

        $this->assertSame(AttendanceDay::MISSING_PUNCH, $day->status);
    }

    public function test_salary_fields_are_hidden_and_only_payroll_managers_can_edit_them(): void
    {
        $profile = EmployeeProfile::where('user_id', self::EMPLOYEE)->first();

        $list = $this->withHeaders($this->hr())->getJson('/api/employee-profiles')->assertOk()->json('data');
        $this->assertArrayNotHasKey('base_salary', $list[0]);

        $this->withHeaders($this->hr())->putJson("/api/employee-profiles/{$profile->id}", ['base_salary' => 20000000])->assertForbidden();
        $this->withHeaders($this->hr())->putJson("/api/employee-profiles/{$profile->id}", ['work_mode' => 'HYBRID'])->assertOk();

        $payroll = $this->jwtHeaders(self::HR, 1, ['attendance.manage', 'payroll.manage']);
        $this->withHeaders($payroll)->putJson("/api/employee-profiles/{$profile->id}", ['base_salary' => 20000000])->assertOk();
        $this->assertSame(20000000.0, $profile->fresh()->base_salary);
        $this->assertArrayHasKey('base_salary', $this->withHeaders($payroll)->getJson('/api/employee-profiles')->json('data.0'));
    }

    public function test_sync_creates_missing_profiles_from_identity(): void
    {
        $this->withHeaders($this->hr())->postJson('/api/employee-profiles/sync')->assertOk()->assertJsonPath('data.created', 1);

        $this->assertSame('NV0099', EmployeeProfile::where('user_id', 99)->value('employee_code'));
        $this->withHeaders($this->hr())->postJson('/api/employee-profiles/sync')->assertJsonPath('data.created', 0);
    }
}
