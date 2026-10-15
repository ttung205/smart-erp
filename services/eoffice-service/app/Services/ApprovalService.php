<?php

namespace App\Services;

use App\Contracts\ApprovalHandler;
use App\Models\ApprovalStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Luồng duyệt dùng chung cho mọi loại đơn E-Office. */
class ApprovalService
{
    public const DECISIONS = [ApprovalStep::APPROVED, ApprovalStep::REJECTED, ApprovalStep::RETURNED];

    /** @param  array{id: int, name: string}  $approver */
    public function openStep(Model $approvable, array $approver): ApprovalStep
    {
        $order = (int) $approvable->approvalSteps()->max('step_order') + 1;

        return $approvable->approvalSteps()->create([
            'step_order' => $order, 'approver_id' => $approver['id'], 'approver_name' => $approver['name'],
            'status' => ApprovalStep::PENDING,
        ]);
    }

    public function record(Model $approvable, object $actor, string $action, ?string $comment = null): void
    {
        $approvable->approvalHistories()->create([
            'actor_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action, 'comment' => $comment,
        ]);
    }

    /** Hủy các bước còn chờ khi người tạo rút / hủy đơn. */
    public function cancelPendingSteps(Model $approvable): void
    {
        $approvable->approvalSteps()->where('status', ApprovalStep::PENDING)
            ->update(['status' => ApprovalStep::CANCELLED, 'acted_at' => now()]);
    }

    public function decide(ApprovalStep $step, object $actor, string $decision, ?string $comment): ApprovalStep
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw ValidationException::withMessages(['decision' => 'Quyết định duyệt không hợp lệ.']);
        }
        if ($decision !== ApprovalStep::APPROVED && blank($comment)) {
            throw ValidationException::withMessages(['comment' => 'Vui lòng nhập lý do khi từ chối hoặc trả lại đơn.']);
        }
        if ((int) $step->approver_id !== (int) $actor->id) {
            throw new AccessDeniedHttpException('Bạn không phải người duyệt của bước này.');
        }

        return DB::transaction(function () use ($step, $actor, $decision, $comment) {
            $step = ApprovalStep::whereKey($step->id)->lockForUpdate()->firstOrFail();
            if ($step->status !== ApprovalStep::PENDING) {
                throw ValidationException::withMessages(['step' => 'Bước duyệt này đã được xử lý.']);
            }

            $step->update(['status' => $decision, 'comment' => $comment, 'acted_at' => now()]);
            $approvable = $step->approvable;
            $this->record($approvable, $actor, $decision, $comment);
            $this->handlerFor($step->approvable_type)->onDecision($approvable, $decision);

            return $step->fresh('approvable');
        });
    }

    private function handlerFor(string $type): ApprovalHandler
    {
        $handler = config("eoffice.approval_handlers.{$type}");
        if (! $handler) {
            throw new \LogicException("Chưa đăng ký nghiệp vụ duyệt cho loại chứng từ [{$type}].");
        }

        return app($handler);
    }
}
