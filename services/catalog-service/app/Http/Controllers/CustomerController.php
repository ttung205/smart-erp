<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\CustomerContact;

class CustomerController extends Controller
{
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

    public function show($id)
    {
        $customer = Customer::with('contacts')->where('id', $id)->orWhere('code', $id)->first();
        if (!$customer) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy khách hàng']], 404);
        }
        return response()->json(['success' => true, 'data' => $customer]);
    }

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
                $code = $prefix . '-' . mt_rand(10000, 99999);
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
            'credit_limit' => (float)$request->input('credit_limit', 0),
            'status' => $request->input('status', 'ACTIVE'),
            'created_by' => $request->input('created_by', 'admin'),
        ]);

        if ($request->has('contacts') && is_array($request->input('contacts'))) {
            foreach ($request->input('contacts') as $c) {
                if (!empty($c['name'])) {
                    CustomerContact::create([
                        'customer_id' => $customer->id,
                        'name' => $c['name'],
                        'position' => $c['position'] ?? null,
                        'phone' => $c['phone'] ?? null,
                        'email' => $c['email'] ?? null,
                        'is_primary' => (bool)($c['is_primary'] ?? false),
                        'notes' => $c['notes'] ?? null,
                    ]);
                }
            }
        }

        return response()->json(['success' => true, 'data' => $customer->load('contacts')], 201);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::where('id', $id)->orWhere('code', $id)->first();
        if (!$customer) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy khách hàng']], 404);
        }

        $data = $request->only([
            'name', 'customer_type', 'tax_code', 'phone', 'email', 'address', 'credit_limit', 'status'
        ]);

        if ($request->has('code')) {
            $newCode = trim($request->input('code'));
            if (!empty($newCode) && $newCode !== $customer->code) {
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
            $newCode = $prefix . '-' . $digits;
            if (!Customer::where('code', $newCode)->where('id', '!=', $customer->id)->exists()) {
                $data['code'] = $newCode;
            }
        }

        $customer->update($data);

        return response()->json(['success' => true, 'data' => $customer->load('contacts')]);
    }

    public function addContact(Request $request, $id)
    {
        $customer = Customer::where('id', $id)->orWhere('code', $id)->first();
        if (!$customer) {
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
            'is_primary' => (bool)$request->input('is_primary', false),
            'notes' => $request->input('notes'),
        ]);

        return response()->json(['success' => true, 'data' => $contact], 201);
    }

    public function updateContact(Request $request, $customerId, $contactId)
    {
        $contact = CustomerContact::where('customer_id', $customerId)->where('id', $contactId)->first();
        if (!$contact) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy người liên hệ']], 404);
        }

        $contact->update($request->only([
            'name', 'position', 'phone', 'email', 'is_primary', 'notes'
        ]));

        return response()->json(['success' => true, 'data' => $contact]);
    }

    public function destroy($id)
    {
        $customer = Customer::where('id', $id)->orWhere('code', $id)->first();
        if (!$customer) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy khách hàng']], 404);
        }

        $customer->delete();
        return response()->json(['success' => true, 'message' => 'Đã xóa khách hàng thành công']);
    }

    public function deleteContact($customerId, $contactId)
    {
        $contact = CustomerContact::where('customer_id', $customerId)->where('id', $contactId)->first();
        if (!$contact) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy người liên hệ']], 404);
        }

        $contact->delete();
        return response()->json(['success' => true, 'message' => 'Đã xóa người liên hệ']);
    }
}
