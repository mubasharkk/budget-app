<?php

namespace App\Http\Requests;

use App\Enums\AnalysisPeriod;
use App\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
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
            'category_id.exists' => 'That category does not exist.',
            'end_date.required_with' => 'Choose when the period ends.',
            'end_date.after_or_equal' => 'The period must end after it starts.',
            'end_date.before_or_equal' => 'The period cannot end in the future.',
        ];
    }

    /**
     * The chosen preset or custom dates (default: DEFAULT_PERIOD), the period
     * to compare with, and a label for it, naming the category if filtered. A
     * whole calendar month picked by its dates is compared with the one before,
     * and the month so far is the same as "This month".
     *
     * @return array{label: string, start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable}
     */
    public function window(): array
    {
        $window = $this->periodWindow();

        if (($categoryId = $this->categoryId()) !== null) {
            $name = Category::query()->whereKey($categoryId)->value('name');
            $window['label'] = Str::limit($window['label'].' · '.$name, 47);
        }

        return $window;
    }

    public function categoryId(): ?int
    {
        $categoryId = $this->validated('category_id');

        return filled($categoryId) ? (int) $categoryId : null;
    }

    /**
     * @return array{label: string, start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable}
     */
    private function periodWindow(): array
    {
        if (filled($this->validated('start_date'))) {
            $start = CarbonImmutable::parse($this->validated('start_date'))->startOfDay();
            $end = CarbonImmutable::parse($this->validated('end_date'))->startOfDay();

            if ($start->day === 1 && $end->isSameDay($start->endOfMonth())) {
                $previous = $start->subMonthNoOverflow();

                return [
                    'label' => $start->format('F Y'),
                    'start' => $start,
                    'end' => $end,
                    'previous_start' => $previous,
                    'previous_end' => $previous->endOfMonth()->startOfDay(),
                ];
            }

            $today = CarbonImmutable::today();

            if ($start->isSameDay($today->startOfMonth()) && $end->isSameDay($today)) {
                return ['label' => AnalysisPeriod::ThisMonth->label(), ...AnalysisPeriod::ThisMonth->window($today)];
            }

            return ['label' => 'Custom dates', ...AnalysisPeriod::rolling($start, $end)];
        }

        $period = AnalysisPeriod::tryFrom((string) $this->validated('period')) ?? static::DEFAULT_PERIOD;

        return ['label' => $period->label(), ...$period->window(CarbonImmutable::today())];
    }
}
