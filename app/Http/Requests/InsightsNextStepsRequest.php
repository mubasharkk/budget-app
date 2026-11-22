<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InsightsNextStepsRequest extends FormRequest
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
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'That category does not exist.',
        ];
    }

    public function categoryId(): ?int
    {
        $categoryId = $this->validated('category_id');

        return filled($categoryId) ? (int) $categoryId : null;
    }
}
