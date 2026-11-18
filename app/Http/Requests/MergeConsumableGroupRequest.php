<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergeConsumableGroupRequest extends FormRequest
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
            'into' => [
                'required',
                'integer',
                Rule::notIn([$this->route('consumableGroup')?->id]),
                Rule::exists('consumable_groups', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'into.required' => 'Choose what to merge it into.',
            'into.not_in' => 'Choose a different name to merge into.',
            'into.exists' => 'That name does not exist.',
        ];
    }
}
