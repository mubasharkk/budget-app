<?php

namespace App\Http\Requests;

use App\Enums\IncomeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomeUpdateRequest extends FormRequest
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
            'monthly_income' => 'nullable|numeric|min:0',
            'income_type' => [
                Rule::requiredIf(fn (): bool => $this->filled('monthly_income') && (float) $this->input('monthly_income') > 0),
                'nullable',
                Rule::enum(IncomeType::class),
            ],
            'income_currency' => 'nullable|string|in:EUR,USD,INR,PKR,TRY,GBP',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monthly_income.min' => 'Income must be zero or greater.',
            'income_type.required' => 'Please specify whether income is net or brutto.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('monthly_income') === '') {
            $this->merge(['monthly_income' => null]);
        }
    }
}
