<?php

namespace App\Http\Controllers;

use App\Models\SalesAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class AttachmentController extends Controller
{
    /**
     * Lấy danh sách file đính kèm theo loại & ID
     */
    #[OA\Get(
        path: '/attachments',
        summary: 'Danh sách tệp đính kèm của một chứng từ',
        description: 'Yêu cầu một trong các quyền quotation.view, order.view, invoice.view, sale_return.view. Truyền type + id (hoặc attachable_type + attachable_id).',
        tags: ['Tệp đính kèm'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['quotation', 'sales_order', 'invoice', 'sale_return_request'])),
            new OA\Parameter(name: 'id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'attachable_type', in: 'query', required: false, description: 'Tên khác của type', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'attachable_id', in: 'query', required: false, description: 'Tên khác của id', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách tệp'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền xem chứng từ'),
            new OA\Response(response: 422, description: self::DOC_422),
        ]
    )]
    public function index(Request $request)
    {
        $request->validate([
            'type' => ['required_without:attachable_type', 'nullable', 'in:quotation,sales_order,invoice,sale_return_request'],
            'attachable_type' => ['required_without:type', 'nullable', 'in:quotation,sales_order,invoice,sale_return_request'],
            'id' => ['required_without:attachable_id', 'nullable', 'integer'],
            'attachable_id' => ['required_without:id', 'nullable', 'integer'],
        ]);
        $type = $request->query('type') ?? $request->query('attachable_type');
        $id = $request->query('id') ?? $request->query('attachable_id');

        $query = SalesAttachment::query();

        if ($type) {
            $query->where('attachable_type', $type);
        }
        if ($id) {
            $query->where('attachable_id', $id);
        }

        $attachments = $query->latest()->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return response()->json([
            'success' => true,
            'data' => $attachments->items(),
            'meta' => [
                'current_page' => $attachments->currentPage(),
                'last_page' => $attachments->lastPage(),
                'per_page' => $attachments->perPage(),
                'total' => $attachments->total(),
            ],
        ]);
    }

    /**
     * Tải lên một hoặc nhiều tệp đính kèm
     */
    #[OA\Post(
        path: '/attachments/upload',
        summary: 'Tải lên tệp đính kèm',
        description: 'Yêu cầu quyền tạo hoặc sửa chứng từ (quotation.create/update, order.create/update, invoice.create, sale_return.create, sale_return.technical). '
            .'Gửi một tệp (file) hoặc nhiều tệp (files[]), mỗi tệp tối đa 20 MB: pdf, png, jpg, jpeg, webp, doc, docx, xls, xlsx, csv, txt. Bỏ trống attachable thì tệp chờ gắn khi lưu chứng từ.',
        tags: ['Tệp đính kèm'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                        new OA\Property(property: 'files[]', type: 'array', items: new OA\Items(type: 'string', format: 'binary')),
                        new OA\Property(property: 'attachable_type', type: 'string', enum: ['quotation', 'sales_order', 'invoice', 'sale_return_request']),
                        new OA\Property(property: 'attachable_id', type: 'integer'),
                    ],
                    type: 'object'
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Các tệp đã lưu'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền tạo hoặc sửa chứng từ'),
            new OA\Response(response: 422, description: 'Không có tệp, sai định dạng hoặc quá 20 MB'),
        ]
    )]
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'nullable|file|max:20480|mimes:pdf,png,jpg,jpeg,webp,doc,docx,xls,xlsx,csv,txt',
            'files.*' => 'nullable|file|max:20480|mimes:pdf,png,jpg,jpeg,webp,doc,docx,xls,xlsx,csv,txt',
            'attachable_type' => 'nullable|in:quotation,sales_order,invoice,sale_return_request',
            'attachable_id' => 'nullable|integer',
        ]);

        $uploadedFiles = [];
        $filesToProcess = [];

        if ($request->hasFile('file')) {
            $filesToProcess[] = $request->file('file');
        }

        if ($request->hasFile('files')) {
            $fList = $request->file('files');
            if (is_array($fList)) {
                $filesToProcess = array_merge($filesToProcess, $fList);
            } else {
                $filesToProcess[] = $fList;
            }
        }

        if (empty($filesToProcess)) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tệp tải lên',
            ], 422);
        }

        $type = $request->input('attachable_type') ?: 'general';
        $attachableId = $request->input('attachable_id') ?: null;
        $userName = $request->attributes->get('actor')->name;

        $year = date('Y');
        $month = date('m');
        $directory = "attachments/{$type}/{$year}/{$month}";

        foreach ($filesToProcess as $file) {
            if (! $file->isValid()) {
                continue;
            }

            $origName = $file->getClientOriginalName();
            $ext = $file->getClientOriginalExtension();
            $size = $file->getSize();
            $mime = $file->getMimeType();

            $safeName = Str::random(24).($ext ? ".{$ext}" : '');
            $path = $file->storeAs($directory, $safeName, 'local');

            $attachment = SalesAttachment::create([
                'attachable_type' => $type,
                'attachable_id' => $attachableId,
                'file_name' => $origName,
                'file_path' => $path,
                'file_url' => null,
                'file_size' => $size,
                'mime_type' => $mime,
                'uploaded_by' => $userName,
            ]);
            $attachment->update(['file_url' => "/api/v1/sales/attachments/{$attachment->id}/download"]);

            $uploadedFiles[] = $attachment;
        }

        return response()->json([
            'success' => true,
            'message' => 'Tải lên tệp đính kèm thành công',
            'data' => count($uploadedFiles) === 1 ? $uploadedFiles[0] : $uploadedFiles,
        ], 201);
    }

    /**
     * Tải xuống tệp đính kèm
     */
    #[OA\Get(
        path: '/attachments/{id}/download',
        summary: 'Tải tệp đính kèm',
        description: 'Yêu cầu một trong các quyền quotation.view, order.view, invoice.view, sale_return.view.',
        tags: ['Tệp đính kèm'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Nội dung tệp', content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền xem chứng từ'),
            new OA\Response(response: 404, description: 'Không tìm thấy tệp'),
        ]
    )]
    #[OA\Get(
        path: '/internal/attachments/{id}/download',
        summary: 'Tải tệp đính kèm (nội bộ)',
        description: 'Chỉ service khác gọi bằng khóa nội bộ, ví dụ notification-service tải PDF báo giá để gửi email. Không đi qua gateway.',
        tags: ['Tệp đính kèm'],
        security: [['internalServiceKey' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Nội dung tệp', content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: 'string', format: 'binary'))),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu hoặc sai khóa nội bộ'),
            new OA\Response(response: 404, description: 'Không tìm thấy tệp'),
        ]
    )]
    public function download($id)
    {
        $attachment = SalesAttachment::findOrFail($id);

        $disk = Storage::disk('local')->exists($attachment->file_path) ? 'local' : 'public';
        if (! Storage::disk($disk)->exists($attachment->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Tệp không tồn tại trên hệ thống lưu trữ',
            ], 404);
        }

        return Storage::disk($disk)->download(
            $attachment->file_path,
            $attachment->file_name
        );
    }

    /**
     * Xóa tệp đính kèm
     */
    #[OA\Delete(
        path: '/attachments/{id}',
        summary: 'Xóa tệp đính kèm',
        description: 'Yêu cầu một trong các quyền quotation.update, order.update, invoice.manage, sale_return.technical, sale_return.manage.',
        tags: ['Tệp đính kèm'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: self::DOC_401),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền sửa chứng từ'),
            new OA\Response(response: 404, description: 'Không tìm thấy tệp'),
        ]
    )]
    public function destroy($id)
    {
        $attachment = SalesAttachment::findOrFail($id);

        $disk = Storage::disk('local')->exists($attachment->file_path) ? 'local' : 'public';
        if (Storage::disk($disk)->exists($attachment->file_path)) {
            Storage::disk($disk)->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa tệp đính kèm thành công',
        ]);
    }
}
