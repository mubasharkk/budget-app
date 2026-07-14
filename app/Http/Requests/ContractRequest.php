<?php

namespace App\Http\Requests;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\ExpenseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|in:EUR,USD,INR,PKR,TRY,GBP',
            'expense_type' => ['nullable', Rule::enum(ExpenseType::class)],
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            'billing_day' => 'nullable|integer|min:1|max:31',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'next_billing_date' => 'nullable|date',
            'status' => ['required', Rule::enum(ContractStatus::class)],
            'auto_renew' => 'boolean',
            'provider_id' => [
                'nullable',
                Rule::exists('providers', 'id')->where('user_id', $this->user()?->id),
            ],
            'category_id' => 'nullable|exists:categories,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A contract name is required.',
            'amount.required' => 'Please enter the contract amount.',
            'end_date.after_or_equal' => 'The end date cannot be before the start date.',
            'provider_id.exists' => 'The selected provider is invalid.',
        ];
    }
}
