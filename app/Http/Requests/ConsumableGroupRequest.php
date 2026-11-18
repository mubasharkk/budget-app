<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsumableGroupRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['nullable', 'in:l,kg,pcs'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give it a name, e.g. "Milk".',
            'name.max' => 'Keep the name to 100 characters or fewer.',
            'unit.in' => 'Count it in litres, kilograms or pieces.',
        ];
    }
}
