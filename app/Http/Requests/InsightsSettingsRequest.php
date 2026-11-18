<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InsightsSettingsRequest extends FormRequest
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
            'excluded_category_ids' => ['present', 'array'],
            'excluded_category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'excluded_category_ids.present' => 'Choose which categories to leave out.',
            'excluded_category_ids.*.exists' => 'That category does not exist.',
        ];
    }
}
