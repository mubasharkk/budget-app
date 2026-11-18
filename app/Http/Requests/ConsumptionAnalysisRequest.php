<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class ConsumptionAnalysisRequest extends FormRequest
{
    /**
     * The period analysed when none is given.
     */
    public const DEFAULT_DAYS = 90;

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
            'start_date' => ['nullable', 'date', 'required_with:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.required_with' => 'Choose when the period starts.',
            'end_date.after_or_equal' => 'The period must end after it starts.',
            'end_date.before_or_equal' => 'The period cannot end in the future.',
        ];
    }

    /**
     * The chosen period, or the last DEFAULT_DAYS days.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(): array
    {
        $end = filled($this->validated('end_date'))
            ? CarbonImmutable::parse($this->validated('end_date'))
            : CarbonImmutable::today();
        $start = filled($this->validated('start_date'))
            ? CarbonImmutable::parse($this->validated('start_date'))
            : $end->subDays(self::DEFAULT_DAYS - 1);

        return [$start->startOfDay(), $end->startOfDay()];
    }
}
