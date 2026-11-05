<?php

namespace App\Http\Controllers;

use App\Services\EmployeeCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class CompanyController extends Controller
{
    private const PROFILE_ID = 1;

    #[OA\Get(
        path: '/company',
        summary: 'Thông tin công ty',
        tags: ['Công ty'],
        security: [['bearerAuth' => []], ['internalServiceKey' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Hồ sơ công ty, kèm logo_url nếu đã có logo'),
            new OA\Response(response: 401, description: 'AUTH_TOKEN_MISSING, AUTH_TOKEN_INVALID hoặc AUTH_TOKEN_EXPIRED: thiếu token hoặc khóa nội bộ, hoặc token sai, hết hạn'),
        ]
    )]
    public function show(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->profile()]);
    }

    #[OA\Put(
        path: '/company',
        summary: 'Cập nhật thông tin công ty',
        description: 'Yêu cầu quyền company.manage.',
        tags: ['Công ty'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'short_name', type: 'string', nullable: true, maxLength: 100),
                    new OA\Property(property: 'tax_code', type: 'string', nullable: true, maxLength: 30),
                    new OA\Property(property: 'address', type: 'string', nullable: true, maxLength: 500),
                    new OA\Property(property: 'office_address', type: 'string', nullable: true, maxLength: 500),
                    new OA\Property(property: 'tel', type: 'string', nullable: true, maxLength: 50),
                    new OA\Property(property: 'hotline', type: 'string', nullable: true, maxLength: 50),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, maxLength: 255),
                    new OA\Property(property: 'accounting_email', type: 'string', format: 'email', nullable: true, maxLength: 255),
                    new OA\Property(property: 'website', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'bank_account_number', type: 'string', nullable: true, maxLength: 100),
                    new OA\Property(property: 'bank_name', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'bank_branch', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'bank_beneficiary', type: 'string', nullable: true, maxLength: 255),
                    new OA\Property(property: 'employee_code_prefix', type: 'string', nullable: true, maxLength: 2, example: 'AC', description: 'Tiền tố mã nhân viên, tối đa 2 chữ cái'),
                ]
            )
        ),
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Hồ sơ công ty sau khi cập nhật'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền company.manage'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: dữ liệu sai định dạng'),
        ]
    )]
    public function update(Request $request, EmployeeCodeService $codes): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'tax_code' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'office_address' => ['nullable', 'string', 'max:500'],
            'tel' => ['nullable', 'string', 'max:50'],
            'hotline' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'accounting_email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_beneficiary' => ['nullable', 'string', 'max:255'],
            'employee_code_prefix' => ['nullable', 'string', 'regex:/^[A-Za-z]{1,2}$/'],
        ], ['employee_code_prefix.regex' => 'Tiền tố mã nhân viên gồm tối đa 2 chữ cái (A-Z).']);

        if (isset($validated['employee_code_prefix'])) {
            $validated['employee_code_prefix'] = strtoupper($validated['employee_code_prefix']);
        }

        $oldPrefix = $codes->prefix();
        $this->saveProfile($validated);
        // Đổi tiền tố thì đổi luôn mã đã cấp (giữ 4 số); nhân viên chưa có mã được gán mã ngay.
        $newPrefix = $codes->prefix();
        if ($newPrefix !== null && $oldPrefix !== null && $newPrefix !== $oldPrefix) {
            $codes->applyPrefix($newPrefix);
        }
        $codes->assignMissing();

        return $this->show();
    }

    #[OA\Post(
        path: '/company/logo',
        summary: 'Tải lên logo công ty',
        description: 'Yêu cầu quyền company.manage. Chấp nhận png, jpg, jpeg tối đa 2 MB.',
        tags: ['Công ty'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['logo'],
                    properties: [
                        new OA\Property(property: 'logo', type: 'string', format: 'binary'),
                    ],
                    type: 'object'
                )
            )
        ),
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Hồ sơ công ty kèm logo_url mới'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền company.manage'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: file không phải ảnh hoặc quá 2 MB'),
        ]
    )]
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:2048']]);
        $file = $request->file('logo');
        $this->saveProfile([
            'logo_data' => base64_encode($file->get()),
            'logo_mime' => $file->getMimeType(),
        ]);

        return $this->show();
    }

    #[OA\Delete(
        path: '/company/logo',
        summary: 'Xóa logo công ty',
        description: 'Yêu cầu quyền company.manage.',
        tags: ['Công ty'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Hồ sơ công ty sau khi xóa logo'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền company.manage'),
        ]
    )]
    public function deleteLogo(): JsonResponse
    {
        DB::table('company_profiles')->where('id', self::PROFILE_ID)
            ->update(['logo_data' => null, 'logo_mime' => null, 'updated_at' => now()]);

        return $this->show();
    }

    #[OA\Get(
        path: '/company/logo',
        summary: 'Xem logo công ty',
        description: 'Công khai, trả về ảnh nhị phân. Dùng trực tiếp trong thẻ img.',
        tags: ['Công ty'],
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ảnh logo',
                content: new OA\MediaType(mediaType: 'image/png', schema: new OA\Schema(type: 'string', format: 'binary'))
            ),
            new OA\Response(response: 404, description: 'Công ty chưa có logo'),
        ]
    )]
    public function logo(): Response
    {
        $row = DB::table('company_profiles')->where('id', self::PROFILE_ID)->first();
        $bytes = $row?->logo_data ? base64_decode($row->logo_data, true) : false;
        if ($bytes === false) {
            abort(404);
        }

        return response($bytes, 200, [
            'Content-Type' => $row->logo_mime ?: 'image/png',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function profile(): array
    {
        $row = DB::table('company_profiles')->where('id', self::PROFILE_ID)->first();
        $data = $row ? (array) $row : [];
        unset($data['logo_data'], $data['logo_mime']);
        $data['logo_url'] = $row?->logo_data
            ? '/api/v1/auth/company/logo?v='.substr(hash('sha256', $row->logo_data), 0, 12)
            : null;

        return $data;
    }

    private function saveProfile(array $values): void
    {
        $query = DB::table('company_profiles')->where('id', self::PROFILE_ID);
        if ($query->exists()) {
            $query->update([...$values, 'updated_at' => now()]);
        } else {
            DB::table('company_profiles')->insert(['id' => self::PROFILE_ID, ...$values, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
