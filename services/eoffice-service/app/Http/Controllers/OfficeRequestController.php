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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OfficeRequestController extends Controller
{
    public function __construct(private readonly OfficeRequestService $service) {}

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

    public function show(Request $request, string $kind, OfficeRequest $officeRequest, IdentityClient $identity): JsonResponse
    {
        $this->ensureKind($kind, $officeRequest);
        $this->ensureVisible($officeRequest, $request->attributes->get('actor'), $identity);

        return response()->json(['success' => true, 'data' => $officeRequest->load(['items', 'attachments', 'approvalSteps', 'approvalHistories'])]);
    }

    public function store(Request $request, string $kind): JsonResponse
    {
        $type = $this->kind($kind);
        $created = $this->service->create($type, $this->validated($request, $type), $request->attributes->get('actor'));

        return response()->json(['success' => true, 'data' => $created], 201);
    }

    public function update(Request $request, string $kind, OfficeRequest $officeRequest): JsonResponse
    {
        $this->ensureKind($kind, $officeRequest);

        return response()->json(['success' => true, 'data' => $this->service->update(
            $officeRequest, $this->validated($request, $officeRequest->kind), $request->attributes->get('actor'),
        )]);
    }

    public function destroy(Request $request, string $kind, OfficeRequest $officeRequest): JsonResponse
    {
        $this->ensureKind($kind, $officeRequest);
        $paths = $officeRequest->attachments()->pluck('disk_path')->all();
        $this->service->delete($officeRequest, $request->attributes->get('actor'));
        Storage::disk('local')->delete($paths);

        return response()->json(['success' => true]);
    }

    public function submit(Request $request, string $kind, OfficeRequest $officeRequest): JsonResponse
    {
        $this->ensureKind($kind, $officeRequest);

        return response()->json(['success' => true, 'data' => $this->service->submit($officeRequest, $request->attributes->get('actor'))]);
    }

    public function cancel(Request $request, string $kind, OfficeRequest $officeRequest): JsonResponse
    {
        $this->ensureKind($kind, $officeRequest);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        return response()->json(['success' => true, 'data' => $this->service->cancel(
            $officeRequest, $request->attributes->get('actor'), $data['reason'] ?? null,
        )]);
    }

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

    public function download(Request $request, string $kind, OfficeRequest $officeRequest, OfficeRequestAttachment $attachment, IdentityClient $identity)
    {
        $this->ensureKind($kind, $officeRequest);
        $this->ensureVisible($officeRequest, $request->attributes->get('actor'), $identity);
        if ($attachment->office_request_id !== $officeRequest->id || ! Storage::disk('local')->exists($attachment->disk_path)) {
            throw new NotFoundHttpException;
        }

        return Storage::disk('local')->download($attachment->disk_path, $attachment->file_name);
    }

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
