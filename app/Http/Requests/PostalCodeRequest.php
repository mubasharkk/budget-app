<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostalCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['postal_code' => trim((string) $this->input('postal_code'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'postal_code' => ['required', 'string', 'regex:/^\d{5}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postal_code.required' => 'Please enter your postal code to see deals near you.',
            'postal_code.regex' => 'Enter a 5-digit German postal code, e.g. 10115.',
        ];
    }
}
