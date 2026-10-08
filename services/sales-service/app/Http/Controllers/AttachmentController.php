<?php

namespace App\Http\Controllers;

use App\Models\SalesAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    /**
     * Lấy danh sách file đính kèm theo loại & ID
     */
    public function index(Request $request)
    {
        $type = $request->query('type') ?? $request->query('attachable_type');
        $id = $request->query('id') ?? $request->query('attachable_id');

        $query = SalesAttachment::query();

        if ($type) {
            $query->where('attachable_type', $type);
        }
        if ($id) {
            $query->where('attachable_id', $id);
        }

        $attachments = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $attachments
        ]);
    }

    /**
     * Tải lên một hoặc nhiều tệp đính kèm
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'nullable|file|max:20480', // Max 20MB
            'files.*' => 'nullable|file|max:20480',
            'attachable_type' => 'nullable|string|max:50',
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
                'message' => 'Không tìm thấy tệp tải lên'
            ], 422);
        }

        $type = $request->input('attachable_type') ?: 'general';
        $attachableId = $request->input('attachable_id') ?: null;
        $userName = $request->input('uploaded_by') ?: ($request->user()?->name ?? 'admin');

        $year = date('Y');
        $month = date('m');
        $directory = "attachments/{$type}/{$year}/{$month}";

        foreach ($filesToProcess as $file) {
            if (!$file->isValid()) continue;

            $origName = $file->getClientOriginalName();
            $ext = $file->getClientOriginalExtension();
            $size = $file->getSize();
            $mime = $file->getMimeType();

            $safeName = Str::random(24) . ($ext ? ".{$ext}" : '');
            $path = $file->storeAs($directory, $safeName, 'public');

            $fileUrl = asset("storage/{$path}");

            $attachment = SalesAttachment::create([
                'attachable_type' => $type,
                'attachable_id' => $attachableId,
                'file_name' => $origName,
                'file_path' => $path,
                'file_url' => $fileUrl,
                'file_size' => $size,
                'mime_type' => $mime,
                'uploaded_by' => $userName,
            ]);

            $uploadedFiles[] = $attachment;
        }

        return response()->json([
            'success' => true,
            'message' => 'Tải lên tệp đính kèm thành công',
            'data' => count($uploadedFiles) === 1 ? $uploadedFiles[0] : $uploadedFiles
        ], 201);
    }

    /**
     * Tải xuống tệp đính kèm
     */
    public function download($id)
    {
        $attachment = SalesAttachment::findOrFail($id);

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Tệp không tồn tại trên hệ thống lưu trữ'
            ], 404);
        }

        return Storage::disk('public')->download(
            $attachment->file_path,
            $attachment->file_name
        );
    }

    /**
     * Xóa tệp đính kèm
     */
    public function destroy($id)
    {
        $attachment = SalesAttachment::findOrFail($id);

        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa tệp đính kèm thành công'
        ]);
    }
}
