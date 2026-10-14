<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleReturnRequest extends FormRequest
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
            'customer_id' => ['nullable', 'integer'],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', 'distinct', 'exists:invoices,id'],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.invoice_item_id' => ['required', 'integer', 'distinct', 'exists:invoice_items,id'],
            'items.*.requested_quantity' => ['required', 'integer', 'min:1'],
            'attachment_ids' => ['nullable', 'array'],
            'attachment_ids.*' => ['integer', 'distinct'],
        ];
    }
}
