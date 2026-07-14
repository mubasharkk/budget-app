<?php

namespace App\Http\Requests;

use App\Enums\ExpenseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
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
            'spent_on' => 'required|date',
            'description' => 'nullable|string|max:255',
            'expense_type' => ['required', Rule::enum(ExpenseType::class)],
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Please enter an expense amount.',
            'amount.min' => 'The expense amount must be greater than zero.',
            'spent_on.required' => 'Please enter when you made this expense.',
        ];
    }
}
