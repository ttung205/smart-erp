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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PayrollController extends Controller
{
    private const VIEW_PERMISSIONS = ['payroll.view', 'payroll.manage', 'payroll.approve', 'payroll.pay'];

    public function __construct(private readonly PayrollService $payroll) {}

    // ───────────── Quy tắc lương ─────────────

    public function rules(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PayrollRule::orderBy('rule_key')->get()]);
    }

    public function updateRule(Request $request, string $key): JsonResponse
    {
        $rule = PayrollRule::where('rule_key', $key)->firstOrFail();
        $value = $request->validate(['value' => ['required', 'array']])['value'];
        $this->validateRuleValue($key, $value);
        $rule->update(['value' => $value]);

        return response()->json(['success' => true, 'data' => $rule->fresh()]);
    }

    // ───────────── Kỳ lương ─────────────

    public function periods(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PayrollPeriod::orderByDesc('year')->orderByDesc('month')->get()]);
    }

    public function storePeriod(Request $request): JsonResponse
    {
        $data = $request->validate(['year' => ['required', 'integer', 'between:2020,2100'], 'month' => ['required', 'integer', 'between:1,12']]);

        return response()->json(['success' => true, 'data' => $this->payroll->ensurePeriod($data['year'], $data['month'])], 201);
    }

    public function showPeriod(PayrollPeriod $period): JsonResponse
    {
        $period->load(['approvalHistories', 'adjustments']);

        return response()->json(['success' => true, 'data' => $period, 'slips' => $period->slips()->orderBy('employee_code')->get()]);
    }

    public function lockAttendance(Request $request, PayrollPeriod $period): JsonResponse
    {
        $summary = $this->payroll->lockAttendance($period, $request->attributes->get('actor'));

        return response()->json(['success' => true, 'data' => $period->fresh(), 'summary' => $summary]);
    }

    public function unlockAttendance(Request $request, PayrollPeriod $period): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']])['reason'];

        return response()->json(['success' => true, 'data' => $this->payroll->unlockAttendance($period, $request->attributes->get('actor'), $reason)]);
    }

    public function calculate(Request $request, PayrollPeriod $period): JsonResponse
    {
        $result = $this->payroll->calculate($period, $request->attributes->get('actor'));

        return response()->json(['success' => true, 'data' => $result['period'], 'warnings' => $result['warnings']]);
    }

    public function submit(Request $request, PayrollPeriod $period): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->payroll->submit($period, $request->attributes->get('actor'))]);
    }

    public function approve(Request $request, PayrollPeriod $period): JsonResponse
    {
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:1000']])['comment'] ?? null;

        return response()->json(['success' => true, 'data' => $this->payroll->approve($period, $request->attributes->get('actor'), $comment)]);
    }

    public function returnPeriod(Request $request, PayrollPeriod $period): JsonResponse
    {
        $comment = $request->validate(['comment' => ['required', 'string', 'min:3', 'max:1000']])['comment'];

        return response()->json(['success' => true, 'data' => $this->payroll->returnToPreparer($period, $request->attributes->get('actor'), $comment)]);
    }

    public function pay(Request $request, PayrollPeriod $period, FinancePayrollService $finance): JsonResponse
    {
        $data = $request->validate(['bank_account_id' => ['required', 'integer', 'min:1']]);
        $paid = $finance->pay($period, $request->attributes->get('actor'), (string) $request->bearerToken(), $data['bank_account_id']);

        return response()->json(['success' => true, 'data' => $paid]);
    }

    // ───────────── Thưởng / khấu trừ thủ công ─────────────

    public function adjustments(PayrollPeriod $period): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $period->adjustments()->orderBy('user_id')->orderBy('id')->get()]);
    }

    public function storeAdjustment(Request $request, PayrollPeriod $period): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'], 'type' => ['required', Rule::in([PayrollAdjustment::BONUS, PayrollAdjustment::DEDUCTION])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000000'], 'reason' => ['required', 'string', 'min:3', 'max:500'],
            'taxable' => ['nullable', 'boolean'],
        ]);

        return response()->json(['success' => true, 'data' => $this->payroll->addAdjustment($period, $data, $request->attributes->get('actor'))], 201);
    }

    public function destroyAdjustment(Request $request, PayrollAdjustment $adjustment): JsonResponse
    {
        $this->payroll->removeAdjustment($adjustment, $request->attributes->get('actor'));

        return response()->json(['success' => true]);
    }

    // ───────────── Phiếu lương ─────────────

    /** Phiếu lương của chính mình: chỉ các kỳ đã được duyệt. */
    public function mySlips(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $slips = PayrollSlip::with('period')->where('user_id', $actor->id)
            ->whereHas('period', fn ($query) => $query->whereIn('status', [PayrollPeriod::APPROVED, PayrollPeriod::PAID]))
            ->get()->sortByDesc(fn (PayrollSlip $slip) => $slip->period->year * 100 + $slip->period->month)->values();

        return response()->json(['success' => true, 'data' => $slips]);
    }

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
