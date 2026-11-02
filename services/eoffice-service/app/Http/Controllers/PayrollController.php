<?php

namespace App\Http\Controllers;

use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRule;
use App\Models\PayrollSlip;
use App\Services\FinancePayrollService;
use App\Services\PayrollService;
use App\Support\ActorPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PayrollController extends Controller
{
    private const VIEW_PERMISSIONS = ['payroll.view', 'payroll.manage', 'payroll.approve', 'payroll.pay'];

    private const DOC_VIEW = 'Yêu cầu một trong các quyền payroll.view, payroll.manage, payroll.approve, payroll.pay. SUPER_ADMIN luôn được; eoffice.manage không đi tắt.';

    private const DOC_MANAGE = 'Yêu cầu quyền payroll.manage (SUPER_ADMIN luôn được; eoffice.manage không đi tắt).';

    private const DOC_403_PAYROLL = 'FORBIDDEN: không có quyền truy cập dữ liệu lương';

    public function __construct(private readonly PayrollService $payroll) {}

    // ───────────── Quy tắc lương ─────────────

    #[OA\Get(
        path: '/payroll/rules',
        summary: 'Quy tắc tính lương',
        description: self::DOC_VIEW,
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Các quy tắc: standard, ot_multipliers, late_penalty, insurance, pit'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
        ]
    )]
    public function rules(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PayrollRule::orderBy('rule_key')->get()]);
    }

    #[OA\Put(
        path: '/payroll/rules/{key}',
        summary: 'Cập nhật một quy tắc tính lương',
        description: self::DOC_MANAGE.' Cấu trúc value phụ thuộc key; với pit, các bậc thuế phải tăng dần và bậc cuối có up_to = null.',
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['standard', 'ot_multipliers', 'late_penalty', 'insurance', 'pit']))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['value'],
                properties: [new OA\Property(property: 'value', type: 'object', example: ['hours_per_day' => 8, 'round_to' => 1000])]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Quy tắc sau cập nhật'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
            new OA\Response(response: 404, description: 'Không tìm thấy quy tắc'),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function updateRule(Request $request, string $key): JsonResponse
    {
        $rule = PayrollRule::where('rule_key', $key)->firstOrFail();
        $value = $request->validate(['value' => ['required', 'array']])['value'];
        $this->validateRuleValue($key, $value);
        $rule->update(['value' => $value]);

        return response()->json(['success' => true, 'data' => $rule->fresh()]);
    }

    // ───────────── Kỳ lương ─────────────

    #[OA\Get(
        path: '/payroll/periods',
        summary: 'Danh sách kỳ lương',
        description: self::DOC_VIEW,
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Các kỳ lương, mới nhất trước'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
        ]
    )]
    public function periods(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PayrollPeriod::orderByDesc('year')->orderByDesc('month')->get()]);
    }

    #[OA\Post(
        path: '/payroll/periods',
        summary: 'Tạo kỳ lương',
        description: self::DOC_MANAGE.' Kỳ đã tồn tại thì trả về kỳ đó.',
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['year', 'month'],
                properties: [
                    new OA\Property(property: 'year', type: 'integer', maximum: 2100, minimum: 2020),
                    new OA\Property(property: 'month', type: 'integer', maximum: 12, minimum: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Kỳ lương'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function storePeriod(Request $request): JsonResponse
    {
        $data = $request->validate(['year' => ['required', 'integer', 'between:2020,2100'], 'month' => ['required', 'integer', 'between:1,12']]);

        return response()->json(['success' => true, 'data' => $this->payroll->ensurePeriod($data['year'], $data['month'])], 201);
    }

    #[OA\Get(
        path: '/payroll/periods/{period}',
        summary: 'Chi tiết kỳ lương',
        description: self::DOC_VIEW.' Kèm lịch sử duyệt, thưởng/khấu trừ và các phiếu lương.',
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'period', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Kỳ lương và phiếu lương'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
            new OA\Response(response: 404, description: self::DOC_404),
        ]
    )]
    public function showPeriod(PayrollPeriod $period): JsonResponse
    {
        $period->load(['approvalHistories', 'adjustments']);

        return response()->json(['success' => true, 'data' => $period, 'slips' => $period->slips()->orderBy('employee_code')->get()]);
    }

    #[OA\Post(
        path: '/payroll/periods/{period}/lock-attendance',
        summary: 'Khóa bảng công của kỳ lương',
        description: self::DOC_MANAGE.' Sau khi khóa, công trong kỳ không bị tính lại trừ khi mở khóa.',
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'period', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Kỳ lương và tổng hợp công đã khóa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function lockAttendance(Request $request, PayrollPeriod $period): JsonResponse
    {
        $summary = $this->payroll->lockAttendance($period, $request->attributes->get('actor'));

        return response()->json(['success' => true, 'data' => $period->fresh(), 'summary' => $summary]);
    }

    #[OA\Post(
        path: '/payroll/periods/{period}/unlock-attendance',
        summary: 'Mở khóa bảng công của kỳ lương',
        description: self::DOC_MANAGE.' Bắt buộc ghi lý do.',
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'period', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string', maxLength: 500, minLength: 5)])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Kỳ lương sau khi mở khóa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function unlockAttendance(Request $request, PayrollPeriod $period): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']])['reason'];

        return response()->json(['success' => true, 'data' => $this->payroll->unlockAttendance($period, $request->attributes->get('actor'), $reason)]);
    }

    #[OA\Post(
        path: '/payroll/periods/{period}/calculate',
        summary: 'Tính lương kỳ',
        description: self::DOC_MANAGE.' Tính phiếu lương cho mọi nhân viên từ bảng công đã khóa, quy tắc lương và thưởng/khấu trừ.',
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'period', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Kỳ lương sau khi tính và danh sách cảnh báo'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function calculate(Request $request, PayrollPeriod $period): JsonResponse
    {
        $result = $this->payroll->calculate($period, $request->attributes->get('actor'));

        return response()->json(['success' => true, 'data' => $result['period'], 'warnings' => $result['warnings']]);
    }

    #[OA\Post(
        path: '/payroll/periods/{period}/submit',
        summary: 'Gửi duyệt bảng lương',
        description: self::DOC_MANAGE,
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'period', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Kỳ lương sau khi gửi duyệt'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function submit(Request $request, PayrollPeriod $period): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->payroll->submit($period, $request->attributes->get('actor'))]);
    }

    #[OA\Post(
        path: '/payroll/periods/{period}/approve',
        summary: 'Duyệt bảng lương',
        description: 'Yêu cầu quyền payroll.approve (SUPER_ADMIN luôn được; eoffice.manage không đi tắt). Sau khi duyệt, nhân viên xem được phiếu lương.',
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'period', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(properties: [new OA\Property(property: 'comment', type: 'string', nullable: true, maxLength: 1000)])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Kỳ lương sau khi duyệt'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function approve(Request $request, PayrollPeriod $period): JsonResponse
    {
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:1000']])['comment'] ?? null;

        return response()->json(['success' => true, 'data' => $this->payroll->approve($period, $request->attributes->get('actor'), $comment)]);
    }

    #[OA\Post(
        path: '/payroll/periods/{period}/return',
        summary: 'Trả lại bảng lương để làm lại',
        description: 'Yêu cầu quyền payroll.approve (SUPER_ADMIN luôn được; eoffice.manage không đi tắt). Bắt buộc ghi nhận xét.',
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'period', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['comment'], properties: [new OA\Property(property: 'comment', type: 'string', maxLength: 1000, minLength: 3)])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Kỳ lương sau khi trả lại'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function returnPeriod(Request $request, PayrollPeriod $period): JsonResponse
    {
        $comment = $request->validate(['comment' => ['required', 'string', 'min:3', 'max:1000']])['comment'];

        return response()->json(['success' => true, 'data' => $this->payroll->returnToPreparer($period, $request->attributes->get('actor'), $comment)]);
    }

    #[OA\Post(
        path: '/payroll/periods/{period}/pay',
        summary: 'Chi lương',
        description: 'Yêu cầu quyền payroll.pay (SUPER_ADMIN luôn được; eoffice.manage không đi tắt). Chỉ bảng lương đã duyệt. '
            .'Gọi finance-service bằng JWT của người thao tác để ghi bút toán lương và chứng từ chi từ tài khoản ngân hàng.',
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'period', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['bank_account_id'], properties: [new OA\Property(property: 'bank_account_id', type: 'integer', minimum: 1, description: 'Tài khoản ngân hàng bên finance')])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Kỳ lương sau khi chi'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: 'Bảng lương chưa duyệt, tổng thực nhận bằng 0, finance từ chối hoặc không kết nối được finance-service'),
        ]
    )]
    public function pay(Request $request, PayrollPeriod $period, FinancePayrollService $finance): JsonResponse
    {
        $data = $request->validate(['bank_account_id' => ['required', 'integer', 'min:1']]);
        $paid = $finance->pay($period, $request->attributes->get('actor'), (string) $request->bearerToken(), $data['bank_account_id']);

        return response()->json(['success' => true, 'data' => $paid]);
    }

    // ───────────── Thưởng / khấu trừ thủ công ─────────────

    #[OA\Get(
        path: '/payroll/periods/{period}/adjustments',
        summary: 'Thưởng và khấu trừ thủ công của kỳ lương',
        description: self::DOC_VIEW,
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'period', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Các khoản thưởng/khấu trừ'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
            new OA\Response(response: 404, description: self::DOC_404),
        ]
    )]
    public function adjustments(PayrollPeriod $period): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $period->adjustments()->orderBy('user_id')->orderBy('id')->get()]);
    }

    #[OA\Post(
        path: '/payroll/periods/{period}/adjustments',
        summary: 'Thêm thưởng hoặc khấu trừ',
        description: self::DOC_MANAGE,
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'period', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'type', 'amount', 'reason'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer'),
                    new OA\Property(property: 'type', type: 'string', enum: ['BONUS', 'DEDUCTION']),
                    new OA\Property(property: 'amount', type: 'number', maximum: 1000000000, exclusiveMinimum: 0),
                    new OA\Property(property: 'reason', type: 'string', maxLength: 500, minLength: 3),
                    new OA\Property(property: 'taxable', type: 'boolean', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Khoản thưởng/khấu trừ mới'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function storeAdjustment(Request $request, PayrollPeriod $period): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'], 'type' => ['required', Rule::in([PayrollAdjustment::BONUS, PayrollAdjustment::DEDUCTION])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000000'], 'reason' => ['required', 'string', 'min:3', 'max:500'],
            'taxable' => ['nullable', 'boolean'],
        ]);

        return response()->json(['success' => true, 'data' => $this->payroll->addAdjustment($period, $data, $request->attributes->get('actor'))], 201);
    }

    #[OA\Delete(
        path: '/payroll/adjustments/{adjustment}',
        summary: 'Xóa thưởng hoặc khấu trừ',
        description: self::DOC_MANAGE,
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'adjustment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403_PAYROLL),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function destroyAdjustment(Request $request, PayrollAdjustment $adjustment): JsonResponse
    {
        $this->payroll->removeAdjustment($adjustment, $request->attributes->get('actor'));

        return response()->json(['success' => true]);
    }

    // ───────────── Phiếu lương ─────────────

    /** Phiếu lương của chính mình: chỉ các kỳ đã được duyệt. */
    #[OA\Get(
        path: '/payroll/my-slips',
        summary: 'Phiếu lương của tôi',
        description: 'Chỉ các kỳ đã duyệt hoặc đã chi, mới nhất trước.',
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Phiếu lương kèm kỳ lương'),
            new OA\Response(response: 401, description: self::DOC_401),
        ]
    )]
    public function mySlips(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $slips = PayrollSlip::with('period')->where('user_id', $actor->id)
            ->whereHas('period', fn ($query) => $query->whereIn('status', [PayrollPeriod::APPROVED, PayrollPeriod::PAID]))
            ->get()->sortByDesc(fn (PayrollSlip $slip) => $slip->period->year * 100 + $slip->period->month)->values();

        return response()->json(['success' => true, 'data' => $slips]);
    }

    #[OA\Get(
        path: '/payroll/slips/{slip}',
        summary: 'Chi tiết phiếu lương',
        description: 'Xem được: chủ phiếu khi kỳ đã duyệt hoặc đã chi; hoặc người có một trong các quyền payroll.view, payroll.manage, payroll.approve, payroll.pay.',
        tags: ['Lương'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'slip', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Phiếu lương kèm kỳ lương'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
        ]
    )]
    public function showSlip(Request $request, PayrollSlip $slip): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $slip->load('period');
        $isOwner = (int) $slip->user_id === $actor->id
            && in_array($slip->period->status, [PayrollPeriod::APPROVED, PayrollPeriod::PAID], true);
        if (! $isOwner && ! ActorPermissions::hasStrict($actor, ...self::VIEW_PERMISSIONS)) {
            throw new AccessDeniedHttpException('Bạn không có quyền xem phiếu lương này.');
        }

        return response()->json(['success' => true, 'data' => $slip]);
    }

    /** @param  array<string, mixed>  $value */
    private function validateRuleValue(string $key, array $value): void
    {
        $rules = match ($key) {
            'standard' => ['hours_per_day' => ['required', 'numeric', 'between:1,24'], 'round_to' => ['required', 'integer', 'min:1']],
            'ot_multipliers' => ['WEEKDAY' => ['required', 'numeric', 'between:1,5'], 'WEEKEND' => ['required', 'numeric', 'between:1,5'], 'HOLIDAY' => ['required', 'numeric', 'between:1,5']],
            'late_penalty' => ['free_count' => ['required', 'integer', 'min:0'], 'amount_per_violation' => ['required', 'numeric', 'min:0']],
            'insurance' => ['employee_rate' => ['required', 'numeric', 'between:0,0.3'], 'salary_cap' => ['nullable', 'numeric', 'min:0']],
            'pit' => [
                'personal_deduction' => ['required', 'numeric', 'min:0'], 'dependent_deduction' => ['required', 'numeric', 'min:0'],
                'brackets' => ['required', 'array', 'min:1'], 'brackets.*.up_to' => ['nullable', 'numeric', 'gt:0'], 'brackets.*.rate' => ['required', 'numeric', 'between:0,1'],
            ],
            default => throw ValidationException::withMessages(['rule_key' => 'Quy tắc lương không tồn tại.']),
        };
        validator($value, $rules)->validate();

        if ($key === 'pit') {
            $limits = array_column($value['brackets'], 'up_to');
            $finite = array_slice($limits, 0, -1);
            if (end($limits) !== null || in_array(null, $finite, true) || $finite !== collect($finite)->sort()->values()->all()) {
                throw ValidationException::withMessages(['brackets' => 'Các bậc thuế phải tăng dần và bậc cuối không giới hạn (up_to = null).']);
            }
        }
    }
}
