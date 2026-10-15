<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            'request_number' => ['nullable', 'string', 'max:50', 'unique:delivery_requests,request_number'],
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'warehouse_name' => ['required', 'string', 'max:150'],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'expected_delivery_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:sales_order_items,id'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.sku' => ['required', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
