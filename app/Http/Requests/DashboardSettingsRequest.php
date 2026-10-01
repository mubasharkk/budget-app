<?php

namespace App\Http\Requests;

use App\Enums\DashboardSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardSettingsRequest extends FormRequest
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
            'sections' => 'present|array',
            'sections.*' => ['string', 'distinct', Rule::enum(DashboardSection::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sections.present' => 'Please choose which dashboard sections to show.',
            'sections.*.enum' => 'That dashboard section does not exist.',
        ];
    }
}
