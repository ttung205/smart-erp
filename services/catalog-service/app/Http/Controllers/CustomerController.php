<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CustomerController extends Controller
{
    #[OA\Get(
        path: '/customers',
        summary: 'Danh sách khách hàng',
        description: 'Gọi bằng JWT hoặc khóa nội bộ. Service khác tra cứu theo lô bằng ?ids=1,2,3.',
        tags: ['Khách hàng'],
        security: [['bearerAuth' => []], ['internalServiceKey' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Tìm theo mã, tên hoặc số điện thoại', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'ids', in: 'query', required: false, description: 'Danh sách id phân tách bằng dấu phẩy', schema: new OA\Schema(type: 'string', example: '1,2,3')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Khách hàng kèm người liên hệ, sắp xếp theo tên'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu token hoặc khóa nội bộ'),
        ]
    )]
    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));
        $query = Customer::with('contacts')->orderBy('name', 'asc');

        if ($request->filled('ids')) {
            $ids = is_array($request->input('ids')) ? $request->input('ids') : explode(',', (string) $request->input('ids'));
            $query->whereIn('id', array_map('intval', $ids));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    #[OA\Get(
        path: '/customers/{id}',
        summary: 'Chi tiết khách hàng',
        description: 'Gọi bằng JWT hoặc khóa nội bộ. {id} nhận id hoặc mã khách hàng.',
        tags: ['Khách hàng'],
        security: [['bearerAuth' => []], ['internalServiceKey' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Id hoặc mã khách hàng (vd. RE-12345)', schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Khách hàng kèm người liên hệ'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu token hoặc khóa nội bộ'),
            new OA\Response(response: 404, description: 'NOT_FOUND: không tìm thấy khách hàng'),
        ]
    )]
    public function show($id)
    {
        $customer = Customer::with('contacts')->where('id', $id)->orWhere('code', $id)->first();
        if (! $customer) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy khách hàng']], 404);
        }

        return response()->json(['success' => true, 'data' => $customer]);
    }

    #[OA\Post(
        path: '/customers',
        summary: 'Tạo khách hàng',
        description: 'Yêu cầu quyền customer.view. Bỏ trống code thì sinh theo loại khách: RE, DE, DI, PR + 5 chữ số.',
        tags: ['Khách hàng'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'code', type: 'string', nullable: true),
                    new OA\Property(property: 'customer_type', type: 'string', default: 'Retail', enum: ['Retail', 'Dealer', 'Distributor', 'Project / Business', 'RE', 'DE', 'DI', 'PR']),
                    new OA\Property(property: 'tax_code', type: 'string', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', nullable: true),
                    new OA\Property(property: 'address', type: 'string', nullable: true),
                    new OA\Property(property: 'credit_limit', type: 'number', default: 0),
                    new OA\Property(property: 'status', type: 'string', default: 'ACTIVE'),
                    new OA\Property(
                        property: 'contacts',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'position', type: 'string', nullable: true),
                                new OA\Property(property: 'phone', type: 'string', nullable: true),
                                new OA\Property(property: 'email', type: 'string', nullable: true),
                                new OA\Property(property: 'is_primary', type: 'boolean', default: false),
                                new OA\Property(property: 'notes', type: 'string', nullable: true),
                            ],
                            type: 'object'
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Khách hàng mới kèm người liên hệ'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền customer.view'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED (thiếu tên) hoặc DUPLICATE (trùng mã)'),
        ]
    )]
    public function store(Request $request)
    {
        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Tên khách hàng là bắt buộc']], 422);
        }

        $type = $request->input('customer_type', 'Retail');
        $typeMap = [
            'Retail' => 'RE',
            'Dealer' => 'DE',
            'Distributor' => 'DI',
            'Project / Business' => 'PR',
            'RE' => 'RE',
            'DE' => 'DE',
            'DI' => 'DI',
            'PR' => 'PR',
        ];
        $prefix = $typeMap[$type] ?? 'RE';

        $code = trim($request->input('code', ''));
        if (empty($code)) {
            do {
                $code = $prefix.'-'.mt_rand(10000, 99999);
            } while (Customer::where('code', $code)->exists());
        } elseif (Customer::where('code', $code)->exists()) {
            return response()->json(['success' => false, 'error' => ['code' => 'DUPLICATE', 'message' => 'Mã khách hàng đã tồn tại']], 422);
        }

        $customer = Customer::create([
            'code' => $code,
            'name' => $name,
            'customer_type' => $type,
            'tax_code' => $request->input('tax_code'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'credit_limit' => (float) $request->input('credit_limit', 0),
            'status' => $request->input('status', 'ACTIVE'),
            'created_by' => $request->input('created_by', 'admin'),
        ]);

        if ($request->has('contacts') && is_array($request->input('contacts'))) {
            foreach ($request->input('contacts') as $c) {
                if (! empty($c['name'])) {
                    CustomerContact::create([
                        'customer_id' => $customer->id,
                        'name' => $c['name'],
                        'position' => $c['position'] ?? null,
                        'phone' => $c['phone'] ?? null,
                        'email' => $c['email'] ?? null,
                        'is_primary' => (bool) ($c['is_primary'] ?? false),
                        'notes' => $c['notes'] ?? null,
                    ]);
                }
            }
        }

        return response()->json(['success' => true, 'data' => $customer->load('contacts')], 201);
    }

    #[OA\Put(
        path: '/customers/{id}',
        summary: 'Cập nhật khách hàng',
        description: 'Yêu cầu quyền customer.view. Đổi customer_type mà không gửi code thì mã được đổi tiền tố theo loại mới.',
        tags: ['Khách hàng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Id hoặc mã khách hàng', schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'code', type: 'string'),
                    new OA\Property(property: 'customer_type', type: 'string'),
                    new OA\Property(property: 'tax_code', type: 'string', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', nullable: true),
                    new OA\Property(property: 'address', type: 'string', nullable: true),
                    new OA\Property(property: 'credit_limit', type: 'number'),
                    new OA\Property(property: 'status', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Khách hàng sau cập nhật'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền customer.view'),
            new OA\Response(response: 404, description: 'NOT_FOUND: không tìm thấy khách hàng'),
            new OA\Response(response: 422, description: 'DUPLICATE: mã khách hàng mới đã tồn tại'),
        ]
    )]
    public function update(Request $request, $id)
    {
        $customer = Customer::where('id', $id)->orWhere('code', $id)->first();
        if (! $customer) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy khách hàng']], 404);
        }

        $data = $request->only([
            'name', 'customer_type', 'tax_code', 'phone', 'email', 'address', 'credit_limit', 'status',
        ]);

        if ($request->has('code')) {
            $newCode = trim($request->input('code'));
            if (! empty($newCode) && $newCode !== $customer->code) {
                if (Customer::where('code', $newCode)->where('id', '!=', $customer->id)->exists()) {
                    return response()->json(['success' => false, 'error' => ['code' => 'DUPLICATE', 'message' => 'Mã khách hàng mới đã tồn tại']], 422);
                }
                $data['code'] = $newCode;
            }
        } elseif ($request->has('customer_type') && $request->input('customer_type') !== $customer->customer_type) {
            $typeMap = [
                'Retail' => 'RE',
                'Dealer' => 'DE',
                'Distributor' => 'DI',
                'Project / Business' => 'PR',
                'RE' => 'RE',
                'DE' => 'DE',
                'DI' => 'DI',
                'PR' => 'PR',
            ];
            $prefix = $typeMap[$request->input('customer_type')] ?? 'RE';
            preg_match('/(\d{5})$/', $customer->code, $matches);
            $digits = $matches[1] ?? mt_rand(10000, 99999);
            $newCode = $prefix.'-'.$digits;
            if (! Customer::where('code', $newCode)->where('id', '!=', $customer->id)->exists()) {
                $data['code'] = $newCode;
            }
        }

        $customer->update($data);

        return response()->json(['success' => true, 'data' => $customer->load('contacts')]);
    }

    #[OA\Post(
        path: '/customers/{id}/contacts',
        summary: 'Thêm người liên hệ cho khách hàng',
        description: 'Yêu cầu quyền customer.view.',
        tags: ['Khách hàng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Id hoặc mã khách hàng', schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'position', type: 'string', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', nullable: true),
                    new OA\Property(property: 'is_primary', type: 'boolean', default: false),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Người liên hệ mới'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền customer.view'),
            new OA\Response(response: 404, description: 'NOT_FOUND: không tìm thấy khách hàng'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: thiếu tên người liên hệ'),
        ]
    )]
    public function addContact(Request $request, $id)
    {
        $customer = Customer::where('id', $id)->orWhere('code', $id)->first();
        if (! $customer) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy khách hàng']], 404);
        }

        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Tên người liên hệ không được để trống']], 422);
        }

        $contact = CustomerContact::create([
            'customer_id' => $customer->id,
            'name' => $name,
            'position' => $request->input('position'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'is_primary' => (bool) $request->input('is_primary', false),
            'notes' => $request->input('notes'),
        ]);

        return response()->json(['success' => true, 'data' => $contact], 201);
    }

    #[OA\Put(
        path: '/customers/{customerId}/contacts/{contactId}',
        summary: 'Cập nhật người liên hệ',
        description: 'Yêu cầu quyền customer.view. Chỉ các trường được gửi lên mới được cập nhật.',
        tags: ['Khách hàng'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'customerId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'contactId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'position', type: 'string', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', nullable: true),
                    new OA\Property(property: 'is_primary', type: 'boolean'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Người liên hệ sau cập nhật'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền customer.view'),
            new OA\Response(response: 404, description: 'NOT_FOUND: không tìm thấy người liên hệ của khách hàng này'),
        ]
    )]
    public function updateContact(Request $request, $customerId, $contactId)
    {
        $contact = CustomerContact::where('customer_id', $customerId)->where('id', $contactId)->first();
        if (! $contact) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy người liên hệ']], 404);
        }

        $contact->update($request->only([
            'name', 'position', 'phone', 'email', 'is_primary', 'notes',
        ]));

        return response()->json(['success' => true, 'data' => $contact]);
    }

    #[OA\Delete(
        path: '/customers/{id}',
        summary: 'Xóa khách hàng',
        description: 'Yêu cầu quyền customer.view.',
        tags: ['Khách hàng'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Id hoặc mã khách hàng', schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền customer.view'),
            new OA\Response(response: 404, description: 'NOT_FOUND: không tìm thấy khách hàng'),
        ]
    )]
    public function destroy($id)
    {
        $customer = Customer::where('id', $id)->orWhere('code', $id)->first();
        if (! $customer) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy khách hàng']], 404);
        }

        $customer->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa khách hàng thành công']);
    }

    #[OA\Delete(
        path: '/customers/{customerId}/contacts/{contactId}',
        summary: 'Xóa người liên hệ',
        description: 'Yêu cầu quyền customer.view.',
        tags: ['Khách hàng'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'customerId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'contactId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Đã xóa'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền customer.view'),
            new OA\Response(response: 404, description: 'NOT_FOUND: không tìm thấy người liên hệ của khách hàng này'),
        ]
    )]
    public function deleteContact($customerId, $contactId)
    {
        $contact = CustomerContact::where('customer_id', $customerId)->where('id', $contactId)->first();
        if (! $contact) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy người liên hệ']], 404);
        }

        $contact->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa người liên hệ']);
    }
}
