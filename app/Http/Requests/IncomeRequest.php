<?php

namespace App\Http\Requests;

use App\Enums\IncomeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomeRequest extends FormRequest
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
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:EUR,USD,INR,PKR,TRY,GBP',
            'received_on' => 'required|date',
            'source' => 'nullable|string|max:255',
            'income_type' => ['nullable', Rule::enum(IncomeType::class)],
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Please enter an income amount.',
            'amount.min' => 'The income amount must be greater than zero.',
            'received_on.required' => 'Please enter when you received this income.',
        ];
    }
}
