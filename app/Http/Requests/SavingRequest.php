<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavingRequest extends FormRequest
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
            'saved_on' => 'required|date',
            'source' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Please enter a savings amount.',
            'amount.min' => 'The savings amount must be greater than zero.',
            'saved_on.required' => 'Please enter when you set this money aside.',
        ];
    }
}
