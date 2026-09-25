<?php

namespace App\Http\Requests;

use App\Domain\Shared\Support\SupportedCurrencies;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('receipt'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vendor' => 'nullable|string|max:255',
            'receipt_number' => 'nullable|string|max:255',
            'currency' => 'nullable|string|'.SupportedCurrencies::rule(),
            'total_amount' => 'required|numeric|min:0',
            'receipt_date' => 'required|date|before_or_equal:now',
            'items' => 'nullable|array',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'items.*.category_id' => 'nullable|exists:categories,id',
            'items.*.subcategory_id' => 'nullable|exists:categories,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt_date.before_or_equal' => 'The receipt date cannot be in the future.',
            'items.*.name.required' => 'Every line item needs a name.',
        ];
    }
}
