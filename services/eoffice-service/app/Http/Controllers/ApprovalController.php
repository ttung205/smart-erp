<?php

namespace App\Http\Controllers;

use App\Models\ApprovalStep;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Hộp "Chờ tôi duyệt" dùng chung cho mọi loại đơn. */
class ApprovalController extends Controller
{
    public function inbox(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('actor');
        $status = (string) $request->input('status', ApprovalStep::PENDING);
        $steps = ApprovalStep::with('approvable')->where('approver_id', $actor->id)
            ->when($status !== 'ALL', fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json([
            'success' => true,
            'data' => collect($steps->items())->map(fn (ApprovalStep $step) => $this->present($step))->all(),
            'total' => $steps->total(),
            'pending_count' => ApprovalStep::where('approver_id', $actor->id)->where('status', ApprovalStep::PENDING)->count(),
            'meta' => ['current_page' => $steps->currentPage(), 'last_page' => $steps->lastPage(), 'per_page' => $steps->perPage()],
        ]);
    }

    public function approve(Request $request, ApprovalStep $step, ApprovalService $approvals): JsonResponse
    {
        return $this->decide($request, $step, $approvals, ApprovalStep::APPROVED);
    }

    public function reject(Request $request, ApprovalStep $step, ApprovalService $approvals): JsonResponse
    {
        return $this->decide($request, $step, $approvals, ApprovalStep::REJECTED);
    }

    public function return(Request $request, ApprovalStep $step, ApprovalService $approvals): JsonResponse
    {
        return $this->decide($request, $step, $approvals, ApprovalStep::RETURNED);
    }

    private function decide(Request $request, ApprovalStep $step, ApprovalService $approvals, string $decision): JsonResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        $step = $approvals->decide($step, $request->attributes->get('actor'), $decision, $data['comment'] ?? null);

        return response()->json(['success' => true, 'data' => $this->present($step)]);
    }

    private function present(ApprovalStep $step): array
    {
        return [
            'id' => $step->id, 'status' => $step->status, 'comment' => $step->comment, 'acted_at' => $step->acted_at,
            'created_at' => $step->created_at, 'document' => $step->approvable?->approvalSummary(),
        ];
    }
}
