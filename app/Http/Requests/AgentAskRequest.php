<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgentAskRequest extends FormRequest
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
            'question' => 'required|string|min:5|max:500',
            'mentions' => 'sometimes|array|max:10',
            'mentions.*.type' => 'required_with:mentions|string|in:category,receipt,contract',
            'mentions.*.id' => 'required_with:mentions|integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'Please enter a question about your spending.',
            'question.min' => 'Your question is too short.',
        ];
    }
}
