<?php

namespace App\Services;

use App\Contracts\ApprovalHandler;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class LeaveRequestService implements ApprovalHandler
{
    public function __construct(
        private readonly LeaveDayCalculator $calculator,
        private readonly LeaveBalanceService $balances,
        private readonly ApprovalService $approvals,
        private readonly ApproverResolver $approvers,
    ) {}

    public function create(array $data, object $actor): LeaveRequest
    {
        return DB::transaction(function () use ($data, $actor) {
            $request = new LeaveRequest([
                'code' => $this->nextCode((int) substr($data['start_date'], 0, 4)),
                'user_id' => $actor->id, 'user_name' => $actor->name, 'department_id' => $actor->department_id,
                'status' => LeaveRequest::DRAFT,
            ]);
            $this->fill($request, $data);
            $request->save();
            $this->approvals->record($request, $actor, 'CREATED');

            return $request;
        });
    }

    public function update(LeaveRequest $request, array $data, object $actor): LeaveRequest
    {
        $this->ensureOwner($request, $actor);
        if (! $request->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Chỉ sửa được đơn nháp hoặc đơn bị trả lại.']);
        }
        $this->fill($request, $data);
        $request->save();

        return $request;
    }

    public function delete(LeaveRequest $request, object $actor): void
    {
        $this->ensureOwner($request, $actor);
        if ($request->status !== LeaveRequest::DRAFT) {
            throw ValidationException::withMessages(['status' => 'Chỉ xóa được đơn nháp. Đơn đã gửi hãy dùng chức năng hủy.']);
        }
        DB::transaction(function () use ($request) {
            $request->approvalHistories()->delete();
            $request->delete();
        });
    }

    public function submit(LeaveRequest $request, object $actor): LeaveRequest
    {
        $this->ensureOwner($request, $actor);
        if (! $request->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Đơn đã được gửi duyệt.']);
        }
        $request->loadMissing('leaveType');
        $this->validateBusinessRules($request);
        $approver = $this->approvers->resolve($request->user_id, $actor->department_id ?? $request->department_id);

        return DB::transaction(function () use ($request, $actor, $approver) {
            $request->update(['status' => LeaveRequest::PENDING, 'submitted_at' => now(), 'department_id' => $actor->department_id ?? $request->department_id]);
            $this->approvals->record($request, $actor, 'SUBMITTED');

            $this->balances->ensureAvailable($request);
            if ($approver === null) {
                // Người tạo là cấp cao nhất: không có ai duyệt, đơn được duyệt thẳng
                $request->update(['status' => LeaveRequest::APPROVED, 'decided_at' => now()]);
                $this->approvals->record($request, $actor, 'AUTO_APPROVED');
            } else {
                $this->approvals->openStep($request, $approver);
            }

            return $request->fresh(['leaveType', 'approvalSteps', 'approvalHistories']);
        });
    }

    public function cancel(LeaveRequest $request, object $actor, ?string $reason): LeaveRequest
    {
        $this->ensureOwner($request, $actor);
        $fromStatus = $request->status;
        if ($fromStatus === LeaveRequest::APPROVED && ! $request->start_date->isAfter(today())) {
            throw ValidationException::withMessages(['status' => 'Đơn đã duyệt chỉ được hủy trước ngày bắt đầu nghỉ.']);
        }
        if (! in_array($fromStatus, LeaveRequest::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Chỉ hủy được đơn đang chờ duyệt hoặc đã duyệt.']);
        }

        return DB::transaction(function () use ($request, $actor, $reason) {
            $this->approvals->cancelPendingSteps($request);
            $request->update(['status' => LeaveRequest::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            $this->approvals->record($request, $actor, 'CANCELLED', $reason);

            return $request->fresh(['leaveType', 'approvalSteps', 'approvalHistories']);
        });
    }

    public function onDecision(Model $approvable, string $decision): void
    {
        // Quỹ phép tính trực tiếp từ trạng thái đơn nên chỉ cần cập nhật trạng thái
        $approvable->update(['status' => $decision, 'decided_at' => now()]);
    }

    private function fill(LeaveRequest $request, array $data): void
    {
        $leaveType = LeaveType::where('is_active', true)->find($data['leave_type_id']);
        if (! $leaveType) {
            throw ValidationException::withMessages(['leave_type_id' => 'Loại nghỉ không tồn tại hoặc đã ngừng sử dụng.']);
        }
        $startSession = $data['start_session'] ?? LeaveDayCalculator::FULL;
        $endSession = $data['start_date'] === $data['end_date'] ? $startSession : ($data['end_session'] ?? LeaveDayCalculator::FULL);
        if (substr($data['start_date'], 0, 4) !== substr($data['end_date'], 0, 4)) {
            throw ValidationException::withMessages(['end_date' => 'Đơn nghỉ không được kéo dài sang năm sau, vui lòng tách thành 2 đơn.']);
        }

        $request->fill([
            'leave_type_id' => $leaveType->id,
            'start_date' => $data['start_date'], 'start_session' => $startSession,
            'end_date' => $data['end_date'], 'end_session' => $endSession,
            'total_days' => $this->calculator->totalDays($data['start_date'], $startSession, $data['end_date'], $endSession),
            'reason' => $data['reason'],
            'handover_user_id' => $data['handover_user_id'] ?? null,
            'handover_user_name' => $data['handover_user_name'] ?? null,
            'handover_note' => $data['handover_note'] ?? null,
        ]);
        $request->setRelation('leaveType', $leaveType);
    }

    private function validateBusinessRules(LeaveRequest $request): void
    {
        $leaveType = $request->leaveType;
        if (! $leaveType?->is_active) {
            throw ValidationException::withMessages(['leave_type_id' => 'Loại nghỉ đã ngừng sử dụng.']);
        }
        if ($request->total_days <= 0) {
            throw ValidationException::withMessages(['start_date' => 'Khoảng thời gian đã chọn không có ngày làm việc nào.']);
        }
        if ($leaveType->max_days_per_request && $request->total_days > $leaveType->max_days_per_request) {
            throw ValidationException::withMessages([
                'total_days' => "{$leaveType->name} tối đa {$leaveType->max_days_per_request} ngày cho mỗi đơn.",
            ]);
        }
        if ($leaveType->requires_attachment && empty($request->attachments)) {
            throw ValidationException::withMessages(['attachments' => "{$leaveType->name} cần đính kèm giấy tờ chứng minh."]);
        }

        $slots = $this->slotsOf($request);
        $overlapping = LeaveRequest::where('user_id', $request->user_id)->whereKeyNot($request->id)
            ->whereIn('status', LeaveRequest::ACTIVE_STATUSES)
            ->whereDate('start_date', '<=', $request->end_date)->whereDate('end_date', '>=', $request->start_date)
            ->get()
            ->first(fn (LeaveRequest $other) => array_intersect($slots, $this->slotsOf($other)) !== []);
        if ($overlapping) {
            throw ValidationException::withMessages(['start_date' => "Thời gian nghỉ bị trùng với đơn {$overlapping->code}."]);
        }
    }

    /** @return list<string> */
    private function slotsOf(LeaveRequest $request): array
    {
        return $this->calculator->slots($request->start_date->toDateString(), $request->start_session,
            $request->end_date->toDateString(), $request->end_session);
    }

    private function nextCode(int $year): string
    {
        $prefix = "NP-{$year}-";
        $last = LeaveRequest::where('code', 'like', "{$prefix}%")->lockForUpdate()->orderByDesc('code')->value('code');
        $sequence = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function ensureOwner(LeaveRequest $request, object $actor): void
    {
        if ((int) $request->user_id !== (int) $actor->id) {
            throw new AccessDeniedHttpException('Bạn chỉ được thao tác trên đơn của chính mình.');
        }
    }
}
