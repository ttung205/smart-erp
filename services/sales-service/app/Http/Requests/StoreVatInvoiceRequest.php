<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVatInvoiceRequest extends FormRequest
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
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'invoice_series' => ['nullable', 'string', 'max:30'],
            'invoice_date' => ['required', 'date'],
            'sales_invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.revenue_report_item_id' => ['required', 'integer', 'exists:revenue_report_items,id'],
            'items.*.sales_invoice_item_id' => ['required', 'integer', 'exists:invoice_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
