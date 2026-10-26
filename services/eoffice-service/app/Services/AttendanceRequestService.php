<?php

namespace App\Services;

use App\Contracts\ApprovalHandler;
use App\Models\AttendanceRequest;
use App\Models\Holiday;
use App\Models\PunchLog;
use App\Support\Outbox;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Yêu cầu bổ sung công / bù công / remote / công tác / làm thêm giờ.
 * Dùng chung luồng duyệt E-Office: người duyệt là quản lý trực tiếp (xem {@see ApproverResolver}).
 * Khi được duyệt, yêu cầu tác động vào chấm công rồi tính lại các ngày liên quan.
 */
class AttendanceRequestService implements ApprovalHandler
{
    /** Số ngày tối đa của một yêu cầu remote / công tác. */
    private const MAX_RANGE_DAYS = 31;

    private const MAX_OVERTIME_MINUTES = 12 * 60;

    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly ApproverResolver $approvers,
        private readonly AttendanceService $attendance,
    ) {}

    public function create(array $data, object $actor): AttendanceRequest
    {
        return DB::transaction(function () use ($data, $actor) {
            $request = new AttendanceRequest([
                'code' => $this->nextCode((int) substr($data['start_date'], 0, 4)),
                'user_id' => $actor->id, 'user_name' => $actor->name, 'department_id' => $actor->department_id,
                'status' => AttendanceRequest::DRAFT,
            ]);
            $request->fill($this->normalize($data))->save();
            $this->approvals->record($request, $actor, 'CREATED');

            return $request;
        });
    }

    public function update(AttendanceRequest $request, array $data, object $actor): AttendanceRequest
    {
        $this->ensureOwner($request, $actor);
        if (! $request->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Chỉ sửa được yêu cầu nháp hoặc yêu cầu bị trả lại.']);
        }
        $request->fill($this->normalize($data))->save();

        return $request;
    }

    public function delete(AttendanceRequest $request, object $actor): void
    {
        $this->ensureOwner($request, $actor);
        if ($request->status !== AttendanceRequest::DRAFT) {
            throw ValidationException::withMessages(['status' => 'Chỉ xóa được yêu cầu nháp. Yêu cầu đã gửi hãy dùng chức năng hủy.']);
        }
        DB::transaction(function () use ($request) {
            $request->approvalHistories()->delete();
            $request->delete();
        });
    }

    public function submit(AttendanceRequest $request, object $actor): AttendanceRequest
    {
        $this->ensureOwner($request, $actor);
        if (! $request->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Yêu cầu đã được gửi duyệt.']);
        }
        $this->validateBusinessRules($request);
        $approver = $this->approvers->resolve($request->user_id, $actor->department_id ?? $request->department_id);

        return DB::transaction(function () use ($request, $actor, $approver) {
            $request->update(['status' => AttendanceRequest::PENDING, 'submitted_at' => now(), 'department_id' => $actor->department_id ?? $request->department_id]);
            $this->approvals->record($request, $actor, 'SUBMITTED');

            if ($approver === null) {
                // Người tạo là cấp cao nhất: yêu cầu được duyệt thẳng và có hiệu lực ngay
                $request->update(['status' => AttendanceRequest::APPROVED, 'decided_at' => now()]);
                $this->approvals->record($request, $actor, 'AUTO_APPROVED');
                $this->apply($request);
            } else {
                $step = $this->approvals->openStep($request, $approver);
                $this->recordEvent('AttendanceRequestSubmitted', 'submitted', $request, [
                    'step_id' => $step->id,
                    'approver' => ['id' => (int) $approver['id'], 'name' => (string) $approver['name']],
                    'actor' => Outbox::actor($actor),
                ]);
            }

            return $request->fresh(['approvalSteps', 'approvalHistories']);
        });
    }

    public function cancel(AttendanceRequest $request, object $actor, ?string $reason): AttendanceRequest
    {
        $this->ensureOwner($request, $actor);
        if (! in_array($request->status, AttendanceRequest::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Chỉ hủy được yêu cầu đang chờ duyệt hoặc đã duyệt.']);
        }

        return DB::transaction(function () use ($request, $actor, $reason) {
            if ($request->status === AttendanceRequest::APPROVED) {
                $this->revert($request);
            }
            $this->approvals->cancelPendingSteps($request);
            $request->update(['status' => AttendanceRequest::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            $this->approvals->record($request, $actor, 'CANCELLED', $reason);
            $this->recordEvent('AttendanceRequestCancelled', 'cancelled', $request, ['reason' => $reason, 'actor' => Outbox::actor($actor)]);

            return $request->fresh(['approvalSteps', 'approvalHistories']);
        });
    }

    public function onDecision(Model $approvable, string $decision): void
    {
        $approvable->update(['status' => $decision, 'decided_at' => now()]);
        if ($decision === AttendanceRequest::APPROVED) {
            $this->apply($approvable);
        }

        $step = $approvable->approvalSteps()->where('status', $decision)->reorder('step_order', 'desc')->first();
        $this->recordEvent('AttendanceRequest'.ucfirst(strtolower($decision)), strtolower($decision), $approvable, [
            'step_id' => $step?->id,
            'comment' => $step?->comment,
            'actor' => $step ? ['id' => (int) $step->approver_id, 'name' => (string) $step->approver_name] : null,
        ]);
    }

    /** Ghi nhận tác động của yêu cầu đã duyệt vào chấm công và tính lại các ngày liên quan. */
    private function apply(AttendanceRequest $request): void
    {
        foreach ($request->dates() as $date) {
            $this->attendance->assertUnlocked($request->user_id, $date);
        }

        if (in_array($request->type, [AttendanceRequest::ADD_PUNCH, AttendanceRequest::MAKEUP], true)) {
            $date = $request->start_date->toDateString();
            foreach ([$request->requested_in, $request->requested_out] as $time) {
                if ($time) {
                    PunchLog::firstOrCreate(
                        ['user_id' => $request->user_id, 'punched_at' => CarbonImmutable::parse("{$date} {$time}"), 'source' => PunchLog::ADJUSTMENT],
                        ['work_date' => $date, 'attendance_request_id' => $request->id, 'note' => $request->code],
                    );
                }
            }
        }

        $this->recalculateDates($request);
    }

    /** Hủy hiệu lực của yêu cầu đã duyệt: vô hiệu hóa log do yêu cầu tạo ra và tính lại. */
    private function revert(AttendanceRequest $request): void
    {
        foreach ($request->dates() as $date) {
            $this->attendance->assertUnlocked($request->user_id, $date);
        }
        PunchLog::where('attendance_request_id', $request->id)->whereNull('voided_at')
            ->update(['voided_at' => now(), 'void_reason' => "Hủy yêu cầu {$request->code}"]);
        // Trạng thái phải được đổi trước khi tính lại để yêu cầu này không còn được tính
        $request->update(['status' => AttendanceRequest::CANCELLED]);
        $this->recalculateDates($request);
    }

    private function recalculateDates(AttendanceRequest $request): void
    {
        $today = CarbonImmutable::today();
        foreach ($request->dates() as $date) {
            if (CarbonImmutable::parse($date)->lte($today)) {
                $this->attendance->recalculate($request->user_id, $date);
            }
        }
    }

    /** @param  array<string, mixed>  $extra */
    private function recordEvent(string $eventType, string $action, AttendanceRequest $request, array $extra): void
    {
        Outbox::record($eventType, "eoffice.attendance.{$action}", 'AttendanceRequest', $request->id, [
            ...$request->approvalSummary(),
            'request_type' => $request->type,
            'start_date' => $request->start_date->toDateString(),
            'end_date' => $request->end_date->toDateString(),
            ...$extra,
        ]);
    }

    /** @return array<string, mixed> */
    private function normalize(array $data): array
    {
        $isRange = in_array($data['type'], [AttendanceRequest::REMOTE, AttendanceRequest::BUSINESS_TRIP], true);
        $usesTimes = ! $isRange;

        return [
            'type' => $data['type'],
            'start_date' => $data['start_date'],
            'end_date' => $isRange ? ($data['end_date'] ?? $data['start_date']) : $data['start_date'],
            'requested_in' => $usesTimes ? ($data['requested_in'] ?? null) : null,
            'requested_out' => $usesTimes ? ($data['requested_out'] ?? null) : null,
            'reason' => $data['reason'],
        ];
    }

    private function validateBusinessRules(AttendanceRequest $request): void
    {
        $start = CarbonImmutable::parse($request->start_date);
        $end = CarbonImmutable::parse($request->end_date);
        $today = CarbonImmutable::today();
        $type = $request->type;

        if ($end->lt($start) || $start->diffInDays($end) >= self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['end_date' => 'Khoảng ngày không hợp lệ (tối đa '.self::MAX_RANGE_DAYS.' ngày).']);
        }

        match ($type) {
            AttendanceRequest::ADD_PUNCH => $this->requireTimes($request, either: true),
            AttendanceRequest::MAKEUP, AttendanceRequest::OVERTIME => $this->requireTimes($request, either: false),
            default => null,
        };
        if (in_array($type, [AttendanceRequest::ADD_PUNCH, AttendanceRequest::MAKEUP], true) && $start->isAfter($today)) {
            throw ValidationException::withMessages(['start_date' => 'Không thể bổ sung công cho ngày chưa diễn ra.']);
        }
        if ($type === AttendanceRequest::OVERTIME) {
            $minutes = CarbonImmutable::parse($request->requested_in)->diffInMinutes(CarbonImmutable::parse($request->requested_out));
            if ($minutes > self::MAX_OVERTIME_MINUTES) {
                throw ValidationException::withMessages(['requested_out' => 'Một yêu cầu làm thêm giờ tối đa 12 giờ.']);
            }
        }

        // Gửi bù cho ngày đã qua phải trong hạn; quá hạn thì nhờ HR điều chỉnh có ghi vết
        if ($start->lt($today)) {
            $limit = (int) config('eoffice.attendance_request_deadline_days');
            if ($this->workingDaysBetween($start, $today) > $limit) {
                throw ValidationException::withMessages(['start_date' => "Đã quá hạn gửi yêu cầu (tối đa {$limit} ngày làm việc kể từ ngày phát sinh). Vui lòng liên hệ HR để điều chỉnh."]);
            }
        }

        foreach ($request->dates() as $date) {
            $this->attendance->assertUnlocked($request->user_id, $date);
        }

        $this->ensureNoDuplicate($request);
    }

    private function requireTimes(AttendanceRequest $request, bool $either): void
    {
        $in = $request->requested_in;
        $out = $request->requested_out;
        if ($either ? (! $in && ! $out) : (! $in || ! $out)) {
            throw ValidationException::withMessages(['requested_in' => $either ? 'Nhập giờ vào hoặc giờ ra cần bổ sung.' : 'Nhập đủ giờ bắt đầu và giờ kết thúc.']);
        }
        if ($in && $out && strcmp(substr($out, 0, 5), substr($in, 0, 5)) <= 0) {
            throw ValidationException::withMessages(['requested_out' => 'Giờ ra phải sau giờ vào.']);
        }
    }

    private function ensureNoDuplicate(AttendanceRequest $request): void
    {
        $query = AttendanceRequest::where('user_id', $request->user_id)->whereKeyNot($request->id)
            ->whereIn('status', AttendanceRequest::ACTIVE_STATUSES)->where('type', $request->type)
            ->whereDate('start_date', '<=', $request->end_date)->whereDate('end_date', '>=', $request->start_date);
        $duplicate = $query->get()->first(function (AttendanceRequest $other) use ($request) {
            if ($request->type !== AttendanceRequest::OVERTIME) {
                return true;
            }

            // OT trong cùng ngày chỉ trùng khi hai khung giờ chồng nhau
            return strcmp(substr($request->requested_in, 0, 5), substr($other->requested_out, 0, 5)) < 0
                && strcmp(substr($other->requested_in, 0, 5), substr($request->requested_out, 0, 5)) < 0;
        });
        if ($duplicate) {
            throw ValidationException::withMessages(['start_date' => "Đã có yêu cầu {$duplicate->code} cho thời gian này."]);
        }
    }

    /** Số ngày làm việc (thứ 2-6, không lễ) trong khoảng (từ, đến]. */
    private function workingDaysBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $holidays = Holiday::whereBetween('date', [$from->toDateString(), $to->toDateString()])->pluck('date')
            ->map(fn ($date) => $date->toDateString())->all();
        $count = 0;
        foreach (CarbonPeriod::create($from->addDay(), $to) as $day) {
            if (! $day->isWeekend() && ! in_array($day->toDateString(), $holidays, true)) {
                $count++;
            }
        }

        return $count;
    }

    private function nextCode(int $year): string
    {
        $prefix = "CC-{$year}-";
        $last = AttendanceRequest::where('code', 'like', "{$prefix}%")->lockForUpdate()->orderByDesc('code')->value('code');
        $sequence = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function ensureOwner(AttendanceRequest $request, object $actor): void
    {
        if ((int) $request->user_id !== (int) $actor->id) {
            throw new AccessDeniedHttpException('Bạn chỉ được thao tác trên yêu cầu của chính mình.');
        }
    }
}
