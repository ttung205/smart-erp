<?php

namespace App\Http\Controllers;

use App\Models\ApprovalStep;
use App\Models\OfficeRequest;
use App\Models\OfficeRequestAttachment;
use App\Services\IdentityClient;
use App\Services\OfficeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[OA\Schema(
    schema: 'OfficeRequestInput',
    required: ['category', 'purpose', 'items'],
    properties: [
        new OA\Property(property: 'category', type: 'string', description: 'purchase: OFFICE_SUPPLIES, EQUIPMENT, OTHER; payment: HOSPITALITY, TRAVEL, OTHER', enum: ['OFFICE_SUPPLIES', 'EQUIPMENT', 'HOSPITALITY', 'TRAVEL', 'OTHER']),
        new OA\Property(property: 'supplier_name', type: 'string', nullable: true, maxLength: 200),
        new OA\Property(property: 'purpose', type: 'string', maxLength: 2000),
        new OA\Property(property: 'notes', type: 'string', nullable: true, maxLength: 2000),
        new OA\Property(
            property: 'items',
            type: 'array',
            maxItems: 30,
            minItems: 1,
            items: new OA\Items(
                required: ['name', 'unit', 'quantity', 'unit_price'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'unit', type: 'string', maxLength: 50),
                    new OA\Property(property: 'quantity', type: 'number', exclusiveMinimum: 0),
                    new OA\Property(property: 'unit_price', type: 'number', minimum: 0),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, maxLength: 1000),
                ],
                type: 'object'
            )
        ),
    ],
    type: 'object'
)]
class OfficeRequestController extends Controller
{
    public function __construct(private readonly OfficeRequestService $service) {}

    #[OA\Get(
        path: '/{kind}-requests',
        summary: 'Danh sách đề nghị mua sắm hoặc thanh toán',
        description: 'kind = purchase (đề nghị mua sắm) hoặc payment (đề nghị thanh toán). Người dùng thấy đơn của mình, nhân viên dưới quyền (trừ nháp) và đơn mình được gán duyệt; SUPER_ADMIN hoặc eoffice.manage thấy tất cả.',
        tags: ['Đề nghị mua sắm và thanh toán'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'kind', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['purchase', 'payment'])),
            new OA\Parameter(name: 'scope', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'all', enum: ['all', 'mine', 'team', 'to_approve'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'RETURNED', 'CANCELLED'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách đơn, tổng số và số đơn chờ tôi duyệt'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function index(Request $request, string $kind, IdentityClient $identity): JsonResponse
    {
        $type = $this->kind($kind);
        $actor = $request->attributes->get('actor');
        $request->validate(['scope' => ['nullable', Rule::in(['all', 'mine', 'team', 'to_approve'])]]);
        $scope = (string) $request->input('scope', 'all');
        $teamIds = in_array($scope, ['all', 'team'], true) ? $identity->directReportIds($actor->id) : [];
        $myPendingStep = fn ($steps) => $steps->where('approver_id', $actor->id)->where('status', ApprovalStep::PENDING);
        $query = OfficeRequest::with('approvalSteps')->where('kind', $type)->orderByDesc('id');
        match ($scope) {
            'mine' => $query->where('user_id', $actor->id),
            'team' => $query->whereIn('user_id', $teamIds)->where('status', '!=', OfficeRequest::DRAFT),
            'to_approve' => $query->whereHas('approvalSteps', $myPendingStep),
            default => $query->where(function ($visible) use ($actor, $teamIds) {
                $visible->where('user_id', $actor->id)->orWhere(function ($others) use ($actor, $teamIds) {
                    $others->where('status', '!=', OfficeRequest::DRAFT);
                    if (! $this->canManage($actor)) {
                        $others->where(fn ($related) => $related->whereIn('user_id', $teamIds)
                            ->orWhereHas('approvalSteps', fn ($steps) => $steps->where('approver_id', $actor->id)));
                    }
                });
            }),
        };
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }
        $result = $query->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json([
            'success' => true, 'data' => $result->items(), 'total' => $result->total(),
            'pending_count' => OfficeRequest::where('kind', $type)->whereHas('approvalSteps', $myPendingStep)->count(),
        ]);
    }

    #[OA\Get(
        path: '/{kind}-requests/{officeRequest}',
        summary: 'Chi tiết đề nghị mua sắm hoặc thanh toán',
        description: 'Kèm dòng hàng, tệp đính kèm, bước và lịch sử duyệt. Đơn khác loại kind trả 404.',
        tags: ['Đề nghị mua sắm và thanh toán'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'kind', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['purchase', 'payment'])),
            new OA\Parameter(name: 'officeRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Đơn đề nghị đầy đủ'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
        ]
    )]
    public function show(Request $request, string $kind, OfficeRequest $officeRequest, IdentityClient $identity): JsonResponse
    {
        $this->ensureKind($kind, $officeRequest);
        $this->ensureVisible($officeRequest, $request->attributes->get('actor'), $identity);

        return response()->json(['success' => true, 'data' => $officeRequest->load(['items', 'attachments', 'approvalSteps', 'approvalHistories'])]);
    }

    #[OA\Post(
        path: '/{kind}-requests',
        summary: 'Tạo đề nghị mua sắm hoặc thanh toán',
        description: 'Tạo đơn nháp của chính mình. category phải thuộc nhóm của kind.',
        tags: ['Đề nghị mua sắm và thanh toán'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'kind', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['purchase', 'payment']))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/OfficeRequestInput')),
        responses: [
            new OA\Response(response: 201, description: 'Đơn đề nghị mới'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 404, description: 'kind không hợp lệ'),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function store(Request $request, string $kind): JsonResponse
    {
        $type = $this->kind($kind);
        $created = $this->service->create($type, $this->validated($request, $type), $request->attributes->get('actor'));

        return response()->json(['success' => true, 'data' => $created], 201);
    }

    #[OA\Put(
        path: '/{kind}-requests/{officeRequest}',
        summary: 'Sửa đề nghị mua sắm hoặc thanh toán',
        description: 'Chỉ chủ đơn, khi đơn ở trạng thái DRAFT hoặc RETURNED. Thay toàn bộ dòng hàng.',
        tags: ['Đề nghị mua sắm và thanh toán'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'kind', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['purchase', 'payment'])),
            new OA\Parameter(name: 'officeRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/OfficeRequestInput')),
        responses: [
            new OA\Response(response: 200, description: 'Đơn sau khi sửa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function update(Request $request, string $kind, OfficeRequest $officeRequest): JsonResponse
    {
        $this->ensureKind($kind, $officeRequest);

        return response()->json(['success' => true, 'data' => $this->service->update(
            $officeRequest, $this->validated($request, $officeRequest->kind), $request->attributes->get('actor'),
        )]);
    }

    #[OA\Delete(
        path: '/{kind}-requests/{officeRequest}',
        summary: 'Xóa đề nghị mua sắm hoặc thanh toán',
        description: 'Chỉ chủ đơn, khi đơn còn sửa được. Xóa luôn các tệp đính kèm.',
        tags: ['Đề nghị mua sắm và thanh toán'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'kind', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['purchase', 'payment'])),
            new OA\Parameter(name: 'officeRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function destroy(Request $request, string $kind, OfficeRequest $officeRequest): JsonResponse
    {
        $this->ensureKind($kind, $officeRequest);
        $paths = $officeRequest->attachments()->pluck('disk_path')->all();
        $this->service->delete($officeRequest, $request->attributes->get('actor'));
        Storage::disk('local')->delete($paths);

        return response()->json(['success' => true]);
    }

    #[OA\Post(
        path: '/{kind}-requests/{officeRequest}/submit',
        summary: 'Gửi duyệt đề nghị mua sắm hoặc thanh toán',
        description: 'Chỉ chủ đơn. Đơn chuyển sang PENDING và sinh các bước duyệt.',
        tags: ['Đề nghị mua sắm và thanh toán'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'kind', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['purchase', 'payment'])),
            new OA\Parameter(name: 'officeRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Đơn sau khi gửi duyệt'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function submit(Request $request, string $kind, OfficeRequest $officeRequest): JsonResponse
    {
        $this->ensureKind($kind, $officeRequest);

        return response()->json(['success' => true, 'data' => $this->service->submit($officeRequest, $request->attributes->get('actor'))]);
    }

    #[OA\Post(
        path: '/{kind}-requests/{officeRequest}/cancel',
        summary: 'Hủy đề nghị mua sắm hoặc thanh toán',
        tags: ['Đề nghị mua sắm và thanh toán'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'kind', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['purchase', 'payment'])),
            new OA\Parameter(name: 'officeRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(properties: [new OA\Property(property: 'reason', type: 'string', nullable: true, maxLength: 1000)])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Đơn sau khi hủy'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function cancel(Request $request, string $kind, OfficeRequest $officeRequest): JsonResponse
    {
        $this->ensureKind($kind, $officeRequest);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        return response()->json(['success' => true, 'data' => $this->service->cancel(
            $officeRequest, $request->attributes->get('actor'), $data['reason'] ?? null,
        )]);
    }

    #[OA\Post(
        path: '/{kind}-requests/{officeRequest}/attachments',
        summary: 'Đính kèm tệp vào đề nghị',
        description: 'Chỉ chủ đơn, khi đơn còn sửa được. Tối đa 10 tệp mỗi đơn, mỗi tệp 10 MB: pdf, png, jpg, jpeg, doc, docx, xls, xlsx.',
        tags: ['Đề nghị mua sắm và thanh toán'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'kind', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['purchase', 'payment'])),
            new OA\Parameter(name: 'officeRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(required: ['file'], properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')], type: 'object')
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tệp đính kèm mới'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: self::DOC_404),
            new OA\Response(response: 422, description: 'Tệp sai định dạng, quá 10 MB, hoặc đơn đã đủ 10 tệp'),
        ]
    )]
    public function upload(Request $request, string $kind, OfficeRequest $officeRequest): JsonResponse
    {
        $this->ensureKind($kind, $officeRequest);
        if ((int) $officeRequest->user_id !== (int) $request->attributes->get('actor')->id || ! $officeRequest->isEditable()) {
            throw new AccessDeniedHttpException('Chỉ chủ đơn được thêm tệp khi đơn còn sửa được.');
        }
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,doc,docx,xls,xlsx'],
        ]);
        if ($officeRequest->attachments()->count() >= 10) {
            return response()->json(['success' => false, 'message' => 'Mỗi đơn tối đa 10 tệp.'], 422);
        }
        $file = $data['file'];
        $path = $file->store("office-requests/{$officeRequest->id}", 'local');
        $attachment = $officeRequest->attachments()->create([
            'file_name' => $file->getClientOriginalName(),
            'disk_path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
        ]);

        return response()->json(['success' => true, 'data' => $attachment], 201);
    }

    #[OA\Get(
        path: '/{kind}-requests/{officeRequest}/attachments/{attachment}',
        summary: 'Tải tệp đính kèm',
        description: 'Người xem được đơn thì tải được tệp.',
        tags: ['Đề nghị mua sắm và thanh toán'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'kind', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['purchase', 'payment'])),
            new OA\Parameter(name: 'officeRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'attachment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Nội dung tệp',
                content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: 'string', format: 'binary'))
            ),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: 'Không tìm thấy đơn hoặc tệp'),
        ]
    )]
    public function download(Request $request, string $kind, OfficeRequest $officeRequest, OfficeRequestAttachment $attachment, IdentityClient $identity)
    {
        $this->ensureKind($kind, $officeRequest);
        $this->ensureVisible($officeRequest, $request->attributes->get('actor'), $identity);
        if ($attachment->office_request_id !== $officeRequest->id || ! Storage::disk('local')->exists($attachment->disk_path)) {
            throw new NotFoundHttpException;
        }

        return Storage::disk('local')->download($attachment->disk_path, $attachment->file_name);
    }

    #[OA\Delete(
        path: '/{kind}-requests/{officeRequest}/attachments/{attachment}',
        summary: 'Xóa tệp đính kèm',
        description: 'Chỉ chủ đơn, khi đơn còn sửa được.',
        tags: ['Đề nghị mua sắm và thanh toán'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'kind', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['purchase', 'payment'])),
            new OA\Parameter(name: 'officeRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'attachment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: self::DOC_403),
            new OA\Response(response: 404, description: 'Không tìm thấy đơn hoặc tệp'),
        ]
    )]
    public function removeAttachment(Request $request, string $kind, OfficeRequest $officeRequest, OfficeRequestAttachment $attachment): JsonResponse
    {
        $this->ensureKind($kind, $officeRequest);
        if ($attachment->office_request_id !== $officeRequest->id) {
            throw new NotFoundHttpException;
        }
        if ((int) $officeRequest->user_id !== (int) $request->attributes->get('actor')->id || ! $officeRequest->isEditable()) {
            throw new AccessDeniedHttpException('Chỉ chủ đơn được xóa tệp khi đơn còn sửa được.');
        }
        $path = $attachment->disk_path;
        $attachment->delete();
        Storage::disk('local')->delete($path);

        return response()->json(['success' => true]);
    }

    private function validated(Request $request, string $kind): array
    {
        $categories = $kind === OfficeRequest::PURCHASE
            ? ['OFFICE_SUPPLIES', 'EQUIPMENT', 'OTHER']
            : ['HOSPITALITY', 'TRAVEL', 'OTHER'];

        return $request->validate([
            'category' => ['required', Rule::in($categories)],
            'supplier_name' => ['nullable', 'string', 'max:200'],
            'purpose' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function kind(string $kind): string
    {
        return match ($kind) {
            'purchase' => OfficeRequest::PURCHASE,
            'payment' => OfficeRequest::PAYMENT,
            default => throw new NotFoundHttpException,
        };
    }

    private function ensureKind(string $kind, OfficeRequest $request): void
    {
        if ($request->kind !== $this->kind($kind)) {
            throw new NotFoundHttpException;
        }
    }

    private function ensureVisible(OfficeRequest $request, object $actor, IdentityClient $identity): void
    {
        if ((int) $request->user_id === (int) $actor->id) {
            return;
        }
        if ($request->status !== OfficeRequest::DRAFT && ($this->canManage($actor)
            || $request->approvalSteps()->where('approver_id', $actor->id)->exists()
            || in_array((int) $request->user_id, $identity->directReportIds($actor->id), true))) {
            return;
        }
        throw new AccessDeniedHttpException('Bạn không có quyền xem đơn này.');
    }

    private function canManage(object $actor): bool
    {
        return in_array('eoffice.manage', $actor->permissions, true) || in_array('SUPER_ADMIN', $actor->roles, true);
    }
}
