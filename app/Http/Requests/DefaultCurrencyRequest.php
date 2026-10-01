<?php

namespace App\Http\Requests;

use App\Domain\Shared\Support\SupportedCurrencies;
use Illuminate\Foundation\Http\FormRequest;

class DefaultCurrencyRequest extends FormRequest
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
            'default_currency' => 'required|string|'.SupportedCurrencies::rule(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_currency.required' => 'Please choose a default currency.',
            'default_currency.in' => 'That currency is not supported.',
        ];
    }
}
