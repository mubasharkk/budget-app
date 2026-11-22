<?php

namespace App\Http\Requests;

use App\Domain\Products\Data\ProductCorrection;
use Illuminate\Foundation\Http\FormRequest;

class ProductCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_name' => ['nullable', 'string', 'max:100', 'required_without:unit'],
            'unit' => ['nullable', 'in:g,kg,ml,l,pcs', 'required_with:size'],
            'size' => ['nullable', 'numeric', 'decimal:0,4', 'min:0.001', 'max:100000', 'required_if:unit,g,ml,l'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'group_name.required_without' => 'Give a general name or a pack size.',
            'group_name.required' => 'Give it a general name, e.g. "Milk".',
            'group_name.max' => 'Keep the name to 100 characters or fewer.',
            'unit.in' => 'Choose g, kg, ml, l or pieces.',
            'unit.required_with' => 'Choose the unit of the pack size.',
            'size.required_if' => 'Give the size of one pack.',
            'size.numeric' => 'The pack size must be a number.',
            'size.decimal' => 'Use at most 4 decimal places.',
            'size.min' => 'The pack size must be more than zero.',
            'size.max' => 'That pack size is too large.',
        ];
    }

    public function correction(): ProductCorrection
    {
        return ProductCorrection::fromArray($this->validated());
    }
}
