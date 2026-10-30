<?php

namespace App\Http\Requests;

use App\Domain\Deals\Data\DealFilters;
use App\Enums\DealSort;
use App\Enums\DealWeek;
use App\Enums\Retailer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Deals page state kept in the URL: the open tab, the offer filters and the
 * date range of the "My prices" tab.
 */
class DealsPageRequest extends FormRequest
{
    public const TAB_OFFERS = 'offers';

    public const TAB_PRICES = 'prices';

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
            'tab' => ['nullable', Rule::in([self::TAB_OFFERS, self::TAB_PRICES])],
            'q' => ['nullable', 'string', 'max:100'],
            'retailer' => ['nullable', Rule::enum(Retailer::class)],
            'sort' => ['nullable', Rule::enum(DealSort::class)],
            'week' => ['nullable', Rule::enum(DealWeek::class)],
            'mine' => ['nullable', 'boolean'],
            'expand' => ['nullable', 'integer', 'min:0'],
            'per_group' => ['nullable', 'integer', 'min:1', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tab.in' => 'That tab does not exist.',
            'q.max' => 'Keep the search to 100 characters or fewer.',
            'retailer.enum' => 'That retailer is not supported.',
            'sort.enum' => 'That sort order is not supported.',
            'week.enum' => 'Choose this week or next week.',
            'expand.integer' => 'That category does not exist.',
            'per_group.max' => 'Show at most 50 offers per category.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }

    public function tab(): string
    {
        return $this->validated('tab') ?? self::TAB_OFFERS;
    }

    public function onPricesTab(): bool
    {
        return $this->tab() === self::TAB_PRICES;
    }

    public function filters(): DealFilters
    {
        return DealFilters::fromArray($this->validated());
    }

    /**
     * @return array{start_date: string, end_date: string}
     */
    public function priceRange(): array
    {
        return [
            'start_date' => (string) $this->validated('start_date', ''),
            'end_date' => (string) $this->validated('end_date', ''),
        ];
    }
}
