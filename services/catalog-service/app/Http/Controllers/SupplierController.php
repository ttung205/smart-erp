<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Supplier;

class SupplierController extends Controller
{
    /**
     * Danh sách nhà cung cấp (hỗ trợ tìm kiếm theo tên, mã, MST, SĐT)
     */
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

        if (!empty($status)) {
            $query->where('status', strtoupper($status));
        }

        if (!empty($group)) {
            $query->where('supplier_group', $group);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    /**
     * Chi tiết nhà cung cấp
     */
    public function show($id)
    {
        $supplier = Supplier::where('id', $id)->orWhere('code', $id)->first();

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUPPLIER_NOT_FOUND',
                    'message' => "Không tìm thấy nhà cung cấp với định danh {$id}."
                ]
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
    public function store(Request $request)
    {
        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Tên nhà cung cấp không được để trống.'
                ]
            ], 422);
        }

        $code = trim($request->input('code', ''));
        if (empty($code)) {
            // Tự động sinh mã NCC chuẩn: NCC-XXXXX
            do {
                $code = 'NCC-' . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            } while (Supplier::where('code', $code)->exists());
        } else {
            if (Supplier::where('code', $code)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DUPLICATE_CODE',
                        'message' => "Mã nhà cung cấp '{$code}' đã tồn tại trong hệ thống."
                    ]
                ], 422);
            }
        }

        $supplier = Supplier::create([
            'code' => $code,
            'name' => $name,
            'tax_code' => trim($request->input('tax_code', '')) ?: null,
            'supplier_group' => trim($request->input('supplier_group', 'Vật tư & Thiết bị')) ?: 'Vật tư & Thiết bị',
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
            'credit_limit' => (float)$request->input('credit_limit', 0),
            'status' => strtoupper($request->input('status', 'ACTIVE')) ?: 'ACTIVE',
            'notes' => trim($request->input('notes', '')) ?: null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $supplier,
            'message' => "Đã tạo nhà cung cấp {$supplier->name} ({$supplier->code}) thành công."
        ], 201);
    }

    /**
     * Cập nhật thông tin nhà cung cấp
     */
    public function update(Request $request, $id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUPPLIER_NOT_FOUND',
                    'message' => "Không tìm thấy nhà cung cấp ID {$id}."
                ]
            ], 404);
        }

        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Tên nhà cung cấp không được để trống.'
                ]
            ], 422);
        }

        $code = trim($request->input('code', ''));
        if (!empty($code) && $code !== $supplier->code) {
            if (Supplier::where('code', $code)->where('id', '!=', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DUPLICATE_CODE',
                        'message' => "Mã nhà cung cấp '{$code}' đã được sử dụng."
                    ]
                ], 422);
            }
            $supplier->code = $code;
        }

        $supplier->name = $name;
        $supplier->tax_code = trim($request->input('tax_code', '')) ?: null;
        if ($request->has('supplier_group')) {
            $supplier->supplier_group = trim($request->input('supplier_group', '')) ?: 'Vật tư & Thiết bị';
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
            $supplier->credit_limit = (float)$request->input('credit_limit', 0);
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
            'message' => "Đã cập nhật nhà cung cấp {$supplier->name} thành công."
        ]);
    }

    /**
     * Xóa nhà cung cấp
     */
    public function destroy($id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUPPLIER_NOT_FOUND',
                    'message' => "Không tìm thấy nhà cung cấp ID {$id}."
                ]
            ], 404);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => "Đã xóa nhà cung cấp {$supplier->name}."
        ]);
    }
}
