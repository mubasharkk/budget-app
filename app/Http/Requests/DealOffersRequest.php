<?php

namespace App\Http\Requests;

use App\Enums\DealSort;
use App\Enums\DealWeek;
use App\Enums\Retailer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealOffersRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'retailer' => ['nullable', Rule::enum(Retailer::class)],
            'sort' => ['nullable', Rule::enum(DealSort::class)],
            'week' => ['nullable', Rule::enum(DealWeek::class)],
            'mine' => ['nullable', 'boolean'],
            'category' => ['nullable', 'integer', 'min:0'],
            'per_group' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.max' => 'Keep the search to 100 characters or fewer.',
            'retailer.enum' => 'That retailer is not supported.',
            'sort.enum' => 'That sort order is not supported.',
            'week.enum' => 'Choose this week or next week.',
            'category.integer' => 'That category does not exist.',
            'per_group.max' => 'Show at most 50 offers per category.',
        ];
    }
}
