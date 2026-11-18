<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveProductRequest extends FormRequest
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
            'group_name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'group_name.required' => 'Type the general name to move it to.',
            'group_name.max' => 'Keep the name to 100 characters or fewer.',
        ];
    }
}
