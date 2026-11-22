<?php

namespace App\Http\Requests;

class UnmatchedItemRequest extends ProductCorrectionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['required', 'string', 'max:255'],
            'group_name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'name.required' => 'Say which receipt line this is.',
        ];
    }
}
