<?php

namespace App\Http\Controllers;

use App\Models\ApprovalStep;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** Hộp "Chờ tôi duyệt" dùng chung cho mọi loại đơn. */
#[OA\Schema(
    schema: 'ApprovalDecisionInput',
    properties: [new OA\Property(property: 'comment', type: 'string', nullable: true, maxLength: 2000)],
    type: 'object'
)]
class ApprovalController extends Controller
{
    #[OA\Get(
        path: '/approvals/inbox',
        summary: 'Hộp chờ tôi duyệt',
        description: 'Các bước duyệt được gán cho người đang đăng nhập, dùng chung cho đơn nghỉ, đề nghị mua sắm/thanh toán, yêu cầu công và kỳ lương.',
        tags: ['Duyệt đơn'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'ALL để lấy mọi trạng thái', schema: new OA\Schema(type: 'string', default: 'PENDING', enum: ['PENDING', 'APPROVED', 'REJECTED', 'RETURNED', 'CANCELLED', 'ALL'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Các bước duyệt kèm tóm tắt chứng từ, tổng số và số đang chờ'),
            new OA\Response(response: 401, description: self::DOC_401),
        ]
    )]
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

    #[OA\Post(
        path: '/approvals/{step}/approve',
        summary: 'Duyệt một bước',
        description: 'Chỉ người được gán ở bước duyệt mới thao tác được. Bước cuối được duyệt thì chứng từ chuyển sang APPROVED.',
        tags: ['Duyệt đơn'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'step', in: 'path', required: true, description: 'Id bước duyệt', schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(ref: '#/components/schemas/ApprovalDecisionInput')),
        responses: [
            new OA\Response(response: 200, description: 'Bước duyệt sau khi quyết định'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function approve(Request $request, ApprovalStep $step, ApprovalService $approvals): JsonResponse
    {
        return $this->decide($request, $step, $approvals, ApprovalStep::APPROVED);
    }

    #[OA\Post(
        path: '/approvals/{step}/reject',
        summary: 'Từ chối một bước',
        description: 'Chỉ người được gán ở bước duyệt mới thao tác được. Chứng từ chuyển sang REJECTED.',
        tags: ['Duyệt đơn'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'step', in: 'path', required: true, description: 'Id bước duyệt', schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(ref: '#/components/schemas/ApprovalDecisionInput')),
        responses: [
            new OA\Response(response: 200, description: 'Bước duyệt sau khi quyết định'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function reject(Request $request, ApprovalStep $step, ApprovalService $approvals): JsonResponse
    {
        return $this->decide($request, $step, $approvals, ApprovalStep::REJECTED);
    }

    #[OA\Post(
        path: '/approvals/{step}/return',
        summary: 'Trả lại một bước để chỉnh sửa',
        description: 'Chỉ người được gán ở bước duyệt mới thao tác được. Chứng từ chuyển sang RETURNED, người tạo sửa và gửi lại.',
        tags: ['Duyệt đơn'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'step', in: 'path', required: true, description: 'Id bước duyệt', schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(ref: '#/components/schemas/ApprovalDecisionInput')),
        responses: [
            new OA\Response(response: 200, description: 'Bước duyệt sau khi quyết định'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
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
