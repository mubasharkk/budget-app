<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DealsForYouRequest extends FormRequest
{
    public const DEFAULT_LIMIT = 6;

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
            'limit' => ['nullable', 'integer', 'min:1', 'max:24'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'limit.max' => 'Show at most 24 recommendations.',
        ];
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? self::DEFAULT_LIMIT);
    }
}
