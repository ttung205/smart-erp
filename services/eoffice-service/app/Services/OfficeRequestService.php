<?php

namespace App\Services;

use App\Contracts\ApprovalHandler;
use App\Models\OfficeRequest;
use App\Support\Outbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OfficeRequestService implements ApprovalHandler
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly ApproverResolver $approvers,
    ) {}

    public function create(string $kind, array $data, object $actor): OfficeRequest
    {
        return DB::transaction(function () use ($kind, $data, $actor) {
            $request = OfficeRequest::create([
                'code' => $this->nextCode($kind),
                'kind' => $kind,
                'category' => $data['category'],
                'user_id' => $actor->id,
                'user_name' => $actor->name,
                'department_id' => $actor->department_id,
                'supplier_name' => $data['supplier_name'] ?? null,
                'purpose' => $data['purpose'],
                'notes' => $data['notes'] ?? null,
                'total_amount' => 0,
                'status' => OfficeRequest::DRAFT,
            ]);
            $this->replaceItems($request, $data['items']);
            $this->approvals->record($request, $actor, 'CREATED');

            return $request->fresh(['items', 'attachments', 'approvalSteps', 'approvalHistories']);
        });
    }

    public function update(OfficeRequest $request, array $data, object $actor): OfficeRequest
    {
        $this->ensureEditableOwner($request, $actor);

        return DB::transaction(function () use ($request, $data) {
            $request->update([
                'category' => $data['category'],
                'supplier_name' => $data['supplier_name'] ?? null,
                'purpose' => $data['purpose'],
                'notes' => $data['notes'] ?? null,
            ]);
            $this->replaceItems($request, $data['items']);

            return $request->fresh(['items', 'attachments', 'approvalSteps', 'approvalHistories']);
        });
    }

    public function delete(OfficeRequest $request, object $actor): void
    {
        $this->ensureOwner($request, $actor);
        if ($request->status !== OfficeRequest::DRAFT) {
            throw ValidationException::withMessages(['status' => 'Chỉ xóa được đơn nháp.']);
        }
        DB::transaction(function () use ($request) {
            $request->approvalHistories()->delete();
            $request->delete();
        });
    }

    public function submit(OfficeRequest $request, object $actor): OfficeRequest
    {
        $this->ensureEditableOwner($request, $actor);
        if (! $request->items()->exists() || (float) $request->total_amount <= 0) {
            throw ValidationException::withMessages(['items' => 'Đơn cần ít nhất một khoản có thành tiền lớn hơn 0.']);
        }
        if ($request->kind === OfficeRequest::PAYMENT && ! $request->attachments()->exists()) {
            throw ValidationException::withMessages(['attachments' => 'Đề nghị thanh toán cần ít nhất một chứng từ đính kèm.']);
        }
        $approver = $this->approvers->resolve($request->user_id, $actor->department_id ?? $request->department_id);

        return DB::transaction(function () use ($request, $actor, $approver) {
            $request->update(['status' => OfficeRequest::PENDING, 'submitted_at' => now()]);
            $this->approvals->record($request, $actor, 'SUBMITTED');
            if ($approver === null) {
                $request->update(['status' => OfficeRequest::APPROVED, 'decided_at' => now()]);
                $this->approvals->record($request, $actor, 'AUTO_APPROVED');
            } else {
                $step = $this->approvals->openStep($request, $approver);
                $this->recordEvent($request, 'Submitted', ['step_id' => $step->id, 'approver' => $approver, 'actor' => Outbox::actor($actor)]);
            }

            return $request->fresh(['items', 'attachments', 'approvalSteps', 'approvalHistories']);
        });
    }

    public function cancel(OfficeRequest $request, object $actor, ?string $reason): OfficeRequest
    {
        $this->ensureOwner($request, $actor);
        if ($request->status !== OfficeRequest::PENDING) {
            throw ValidationException::withMessages(['status' => 'Chỉ hủy được đơn đang chờ duyệt.']);
        }

        return DB::transaction(function () use ($request, $actor, $reason) {
            $this->approvals->cancelPendingSteps($request);
            $request->update(['status' => OfficeRequest::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            $this->approvals->record($request, $actor, 'CANCELLED', $reason);
            $this->recordEvent($request, 'Cancelled', ['actor' => Outbox::actor($actor), 'reason' => $reason]);

            return $request->fresh(['items', 'attachments', 'approvalSteps', 'approvalHistories']);
        });
    }

    public function onDecision(Model $approvable, string $decision): void
    {
        $approvable->update(['status' => $decision, 'decided_at' => now()]);
        $step = $approvable->approvalSteps()->where('status', $decision)->reorder('step_order', 'desc')->first();
        $this->recordEvent($approvable, ucfirst(strtolower($decision)), [
            'step_id' => $step?->id,
            'comment' => $step?->comment,
            'actor' => $step ? ['id' => (int) $step->approver_id, 'name' => $step->approver_name] : null,
        ]);
    }

    private function replaceItems(OfficeRequest $request, array $items): void
    {
        $request->items()->delete();
        $total = 0;
        foreach ($items as $item) {
            $amount = round((float) $item['quantity'] * (float) $item['unit_price'], 2);
            $request->items()->create([
                'name' => $item['name'],
                'unit' => $item['unit'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'amount' => $amount,
                'notes' => $item['notes'] ?? null,
            ]);
            $total += $amount;
        }
        $request->update(['total_amount' => $total]);
    }

    private function nextCode(string $kind): string
    {
        $prefix = ($kind === OfficeRequest::PURCHASE ? 'MS-' : 'TT-').now()->year.'-';
        $last = OfficeRequest::where('code', 'like', "{$prefix}%")->lockForUpdate()->orderByDesc('code')->value('code');

        return $prefix.str_pad((string) ($last ? (int) substr($last, strlen($prefix)) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }

    private function recordEvent(OfficeRequest $request, string $action, array $extra): void
    {
        $kind = $request->kind === OfficeRequest::PURCHASE ? 'purchase' : 'payment';
        $kindName = ucfirst($kind);
        Outbox::record("Office{$kindName}Request{$action}", "eoffice.{$kind}.".strtolower($action),
            'OfficeRequest', $request->id, [...$request->approvalSummary(), ...$extra]);
    }

    private function ensureOwner(OfficeRequest $request, object $actor): void
    {
        if ((int) $request->user_id !== (int) $actor->id) {
            throw new AccessDeniedHttpException('Bạn chỉ được thao tác trên đơn của chính mình.');
        }
    }

    private function ensureEditableOwner(OfficeRequest $request, object $actor): void
    {
        $this->ensureOwner($request, $actor);
        if (! $request->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Chỉ sửa hoặc gửi đơn nháp và đơn bị trả lại.']);
        }
    }
}
