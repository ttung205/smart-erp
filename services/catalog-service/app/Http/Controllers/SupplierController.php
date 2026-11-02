<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SupplierInput',
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'code', type: 'string', nullable: true, description: 'Bỏ trống thì sinh TS-xxxx (TRADING) hoặc MS-xxxx (MANUFACTURER)'),
        new OA\Property(property: 'supplier_type', type: 'string', default: 'TRADING', enum: ['TRADING', 'MANUFACTURER', 'MS']),
        new OA\Property(property: 'supplier_group', type: 'string', default: 'Vật tư & Thiết bị'),
        new OA\Property(property: 'tax_code', type: 'string', nullable: true),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'email', type: 'string', nullable: true),
        new OA\Property(property: 'address', type: 'string', nullable: true),
        new OA\Property(property: 'contact_person', type: 'string', nullable: true),
        new OA\Property(property: 'contact_phone', type: 'string', nullable: true),
        new OA\Property(property: 'contact_email', type: 'string', nullable: true),
        new OA\Property(property: 'bank_name', type: 'string', nullable: true),
        new OA\Property(property: 'bank_account_number', type: 'string', nullable: true),
        new OA\Property(property: 'bank_account_holder', type: 'string', nullable: true),
        new OA\Property(property: 'bank_branch', type: 'string', nullable: true),
        new OA\Property(property: 'payment_terms', type: 'string', default: 'Net 30'),
        new OA\Property(property: 'credit_limit', type: 'number', default: 0),
        new OA\Property(property: 'status', type: 'string', default: 'ACTIVE'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
    ],
    type: 'object'
)]
class SupplierController extends Controller
{
    /**
     * Danh sách nhà cung cấp (hỗ trợ tìm kiếm theo tên, mã, MST, SĐT)
     */
    #[OA\Get(
        path: '/suppliers',
        summary: 'Danh sách nhà cung cấp',
        tags: ['Nhà cung cấp'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Tìm theo mã, tên, mã số thuế, điện thoại hoặc người liên hệ', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'ACTIVE')),
            new OA\Parameter(name: 'supplier_group', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'supplier_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['TRADING', 'MANUFACTURER'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách nhà cung cấp, mới nhất trước'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
        ]
    )]
    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));
        $status = $request->input('status');
        $group = $request->input('supplier_group');

        $query = Supplier::orderBy('id', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('tax_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        if (! empty($status)) {
            $query->where('status', strtoupper($status));
        }

        if (! empty($group)) {
            $query->where('supplier_group', $group);
        }

        $type = $request->input('supplier_type');
        if (! empty($type)) {
            $query->where('supplier_type', strtoupper($type));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    /**
     * Sinh mã nhà cung cấp ngẫu nhiên:
     * - Trading Supplier: TS-xxxx (4 chữ số ngẫu nhiên)
     * - Manufacturer Supplier: MS-xxxx (4 chữ số ngẫu nhiên)
     */
    #[OA\Get(
        path: '/suppliers/generate-code',
        summary: 'Sinh mã nhà cung cấp chưa dùng',
        description: 'Yêu cầu quyền supplier.view. TRADING sinh TS-xxxx, MANUFACTURER sinh MS-xxxx.',
        tags: ['Nhà cung cấp'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'supplier_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'TRADING', enum: ['TRADING', 'MANUFACTURER', 'MS'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Mã mới và loại nhà cung cấp'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền supplier.view'),
        ]
    )]
    public function generateCode(Request $request)
    {
        $rawType = strtoupper(trim($request->input('supplier_type', $request->input('type', 'TRADING'))));
        $supplierType = in_array($rawType, ['MANUFACTURER', 'MS']) ? 'MANUFACTURER' : 'TRADING';
        $prefix = ($supplierType === 'MANUFACTURER') ? 'MS-' : 'TS-';

        do {
            $code = $prefix.str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        } while (Supplier::where('code', $code)->exists());

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $code,
                'supplier_type' => $supplierType,
            ],
        ]);
    }

    /**
     * Chi tiết nhà cung cấp
     */
    #[OA\Get(
        path: '/suppliers/{id}',
        summary: 'Chi tiết nhà cung cấp',
        tags: ['Nhà cung cấp'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Id hoặc mã nhà cung cấp', schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Thông tin nhà cung cấp'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 404, description: 'SUPPLIER_NOT_FOUND'),
        ]
    )]
    public function show($id)
    {
        $supplier = Supplier::where('id', $id)->orWhere('code', $id)->first();

        if (! $supplier) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUPPLIER_NOT_FOUND',
                    'message' => "Không tìm thấy nhà cung cấp với định danh {$id}.",
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $supplier,
        ]);
    }

    /**
     * Thêm mới nhà cung cấp chuẩn
     */
    #[OA\Post(
        path: '/suppliers',
        summary: 'Tạo nhà cung cấp',
        description: 'Yêu cầu quyền supplier.view. Bắt buộc có name.',
        tags: ['Nhà cung cấp'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SupplierInput')),
        responses: [
            new OA\Response(response: 201, description: 'Nhà cung cấp mới'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền supplier.view'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED (thiếu tên) hoặc DUPLICATE_CODE'),
        ]
    )]
    public function store(Request $request)
    {
        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Tên nhà cung cấp không được để trống.',
                ],
            ], 422);
        }

        $rawType = strtoupper(trim($request->input('supplier_type', 'TRADING')));
        $supplierType = in_array($rawType, ['MANUFACTURER', 'MS']) ? 'MANUFACTURER' : 'TRADING';

        $code = strtoupper(trim($request->input('code', '')));
        if (empty($code)) {
            // Tự động sinh mã NCC chuẩn:
            // Trading Supplier -> TS-xxxx (4 số ngẫu nhiên)
            // Manufacturer Supplier -> MS-xxxx (4 số ngẫu nhiên)
            $prefix = ($supplierType === 'MANUFACTURER') ? 'MS-' : 'TS-';
            do {
                $code = $prefix.str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            } while (Supplier::where('code', $code)->exists());
        } else {
            if (Supplier::where('code', $code)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DUPLICATE_CODE',
                        'message' => "Mã nhà cung cấp '{$code}' đã tồn tại trong hệ thống.",
                    ],
                ], 422);
            }
        }

        $supplier = Supplier::create([
            'code' => $code,
            'name' => $name,
            'tax_code' => trim($request->input('tax_code', '')) ?: null,
            'supplier_group' => trim($request->input('supplier_group', 'Vật tư & Thiết bị')) ?: 'Vật tư & Thiết bị',
            'supplier_type' => $supplierType,
            'phone' => trim($request->input('phone', '')) ?: null,
            'email' => trim($request->input('email', '')) ?: null,
            'address' => trim($request->input('address', '')) ?: null,
            'contact_person' => trim($request->input('contact_person', '')) ?: null,
            'contact_phone' => trim($request->input('contact_phone', '')) ?: null,
            'contact_email' => trim($request->input('contact_email', '')) ?: null,
            'bank_name' => trim($request->input('bank_name', '')) ?: null,
            'bank_account_number' => trim($request->input('bank_account_number', '')) ?: null,
            'bank_account_holder' => trim($request->input('bank_account_holder', '')) ?: null,
            'bank_branch' => trim($request->input('bank_branch', '')) ?: null,
            'payment_terms' => trim($request->input('payment_terms', 'Net 30')) ?: 'Net 30',
            'credit_limit' => (float) $request->input('credit_limit', 0),
            'status' => strtoupper($request->input('status', 'ACTIVE')) ?: 'ACTIVE',
            'notes' => trim($request->input('notes', '')) ?: null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $supplier,
            'message' => "Đã tạo nhà cung cấp {$supplier->name} ({$supplier->code}) thành công.",
        ], 201);
    }

    /**
     * Cập nhật thông tin nhà cung cấp
     */
    #[OA\Put(
        path: '/suppliers/{id}',
        summary: 'Cập nhật nhà cung cấp',
        description: 'Yêu cầu quyền supplier.view. Bắt buộc có name. Chấp nhận cả PUT và PATCH. Các trường liên hệ và ngân hàng không gửi sẽ bị đặt về rỗng.',
        tags: ['Nhà cung cấp'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SupplierInput')),
        responses: [
            new OA\Response(response: 200, description: 'Nhà cung cấp sau cập nhật'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền supplier.view'),
            new OA\Response(response: 404, description: 'SUPPLIER_NOT_FOUND'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED (thiếu tên) hoặc DUPLICATE_CODE'),
        ]
    )]
    public function update(Request $request, $id)
    {
        $supplier = Supplier::find($id);
        if (! $supplier) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUPPLIER_NOT_FOUND',
                    'message' => "Không tìm thấy nhà cung cấp ID {$id}.",
                ],
            ], 404);
        }

        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Tên nhà cung cấp không được để trống.',
                ],
            ], 422);
        }

        $code = trim($request->input('code', ''));
        if (! empty($code) && $code !== $supplier->code) {
            if (Supplier::where('code', $code)->where('id', '!=', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DUPLICATE_CODE',
                        'message' => "Mã nhà cung cấp '{$code}' đã được sử dụng.",
                    ],
                ], 422);
            }
            $supplier->code = $code;
        }

        $supplier->name = $name;
        $supplier->tax_code = trim($request->input('tax_code', '')) ?: null;
        if ($request->has('supplier_group')) {
            $supplier->supplier_group = trim($request->input('supplier_group', '')) ?: 'Vật tư & Thiết bị';
        }
        if ($request->has('supplier_type')) {
            $rawType = strtoupper(trim($request->input('supplier_type', '')));
            $supplier->supplier_type = in_array($rawType, ['MANUFACTURER', 'MS']) ? 'MANUFACTURER' : 'TRADING';
        }
        $supplier->phone = trim($request->input('phone', '')) ?: null;
        $supplier->email = trim($request->input('email', '')) ?: null;
        $supplier->address = trim($request->input('address', '')) ?: null;
        $supplier->contact_person = trim($request->input('contact_person', '')) ?: null;
        $supplier->contact_phone = trim($request->input('contact_phone', '')) ?: null;
        $supplier->contact_email = trim($request->input('contact_email', '')) ?: null;
        $supplier->bank_name = trim($request->input('bank_name', '')) ?: null;
        $supplier->bank_account_number = trim($request->input('bank_account_number', '')) ?: null;
        $supplier->bank_account_holder = trim($request->input('bank_account_holder', '')) ?: null;
        $supplier->bank_branch = trim($request->input('bank_branch', '')) ?: null;
        $supplier->payment_terms = trim($request->input('payment_terms', $supplier->payment_terms)) ?: 'Net 30';
        if ($request->has('credit_limit')) {
            $supplier->credit_limit = (float) $request->input('credit_limit', 0);
        }
        if ($request->has('status')) {
            $supplier->status = strtoupper($request->input('status', 'ACTIVE'));
        }
        if ($request->has('notes')) {
            $supplier->notes = trim($request->input('notes', '')) ?: null;
        }

        $supplier->save();

        return response()->json([
            'success' => true,
            'data' => $supplier,
            'message' => "Đã cập nhật nhà cung cấp {$supplier->name} thành công.",
        ]);
    }

    /**
     * Xóa nhà cung cấp
     */
    #[OA\Delete(
        path: '/suppliers/{id}',
        summary: 'Xóa nhà cung cấp',
        description: 'Yêu cầu quyền supplier.view.',
        tags: ['Nhà cung cấp'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền supplier.view'),
            new OA\Response(response: 404, description: 'SUPPLIER_NOT_FOUND'),
        ]
    )]
    public function destroy($id)
    {
        $supplier = Supplier::find($id);
        if (! $supplier) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUPPLIER_NOT_FOUND',
                    'message' => "Không tìm thấy nhà cung cấp ID {$id}.",
                ],
            ], 404);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => "Đã xóa nhà cung cấp {$supplier->name}.",
        ]);
    }
}
