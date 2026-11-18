<?php

namespace App\Http\Requests;

use App\Enums\AnalysisPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsumptionAnalysisRequest extends FormRequest
{
    /**
     * The period analysed when none is chosen.
     */
    public const DEFAULT_PERIOD = AnalysisPeriod::LastThreeMonths;

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
            'period' => ['nullable', Rule::enum(AnalysisPeriod::class)],
            'start_date' => ['nullable', 'date', 'required_with:end_date', 'prohibits:period'],
            'end_date' => ['nullable', 'date', 'required_with:start_date', 'after_or_equal:start_date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.enum' => 'Choose one of the listed periods.',
            'start_date.required_with' => 'Choose when the period starts.',
            'start_date.prohibits' => 'Choose either a period or your own dates.',
            'end_date.required_with' => 'Choose when the period ends.',
            'end_date.after_or_equal' => 'The period must end after it starts.',
            'end_date.before_or_equal' => 'The period cannot end in the future.',
        ];
    }

    /**
     * The chosen preset or custom dates (default: the last 3 months), the
     * period to compare with, and a label for it.
     *
     * @return array{label: string, start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable}
     */
    public function window(): array
    {
        if (filled($this->validated('start_date'))) {
            $start = CarbonImmutable::parse($this->validated('start_date'))->startOfDay();
            $end = CarbonImmutable::parse($this->validated('end_date'))->startOfDay();

            return ['label' => 'Custom dates', ...AnalysisPeriod::rolling($start, $end)];
        }

        $period = AnalysisPeriod::tryFrom((string) $this->validated('period')) ?? self::DEFAULT_PERIOD;

        return ['label' => $period->label(), ...$period->window(CarbonImmutable::today())];
    }
}
