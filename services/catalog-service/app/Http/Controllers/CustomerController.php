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
        $code = trim($request->input('code', ''));
        $name = trim($request->input('name', ''));

        if (empty($code) || empty($name)) {
            return response()->json(['success' => false, 'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Mã và tên khách hàng là bắt buộc']], 422);
        }

        if (Customer::where('code', $code)->exists()) {
            return response()->json(['success' => false, 'error' => ['code' => 'DUPLICATE', 'message' => 'Mã khách hàng đã tồn tại']], 422);
        }

        $customer = Customer::create([
            'code' => $code,
            'name' => $name,
            'tax_code' => $request->input('tax_code'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'credit_limit' => (float)$request->input('credit_limit', 0),
            'status' => $request->input('status', 'ACTIVE'),
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

        $customer->update($request->only([
            'name', 'tax_code', 'phone', 'email', 'address', 'credit_limit', 'status'
        ]));

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
