<?php

namespace App\Http\Requests;

use App\Enums\BudgetPeriod;
use App\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BudgetRequest extends FormRequest
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
            'category_id' => 'nullable|exists:categories,id',
            'period' => ['required', Rule::enum(BudgetPeriod::class)],
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:EUR,USD,INR,PKR,TRY,GBP',
            'starts_on' => 'required|date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Please enter a budget amount.',
            'amount.min' => 'The budget amount must be greater than zero.',
            'period.required' => 'Please select a budget period.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $userId = $this->user()?->id;
            $categoryId = $this->input('category_id') ?: null;
            $period = $this->input('period');

            $query = Budget::query()
                ->where('user_id', $userId)
                ->where('period', $period);

            if ($categoryId === null) {
                $query->whereNull('category_id');
            } else {
                $query->where('category_id', $categoryId);
            }

            if ($this->route('budget')) {
                $query->where('id', '!=', $this->route('budget')->id);
            }

            if ($query->exists()) {
                $label = $categoryId ? 'this category and period' : 'an overall budget for this period';
                $validator->errors()->add('period', "You already have a budget for {$label}.");
            }
        });
    }
}
