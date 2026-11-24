<?php

namespace App\Domain\Receipts\Services;

use App\Domain\Shared\Support\SupportedCurrencies;
use App\Enums\ReceiptLinkType;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Finding a receipt to attach, and the receipts attached to contracts and
 * savings entries (receipt_links). Attaching never changes spending totals.
 */
class ReceiptLinkService
{
    public const SEARCH_LIMIT = 20;

    /**
     * The user's receipts matching every word of $search, newest first: a word
     * matches the shop or receipt number, an amount ("23,40", or "23" for
     * 23.00–23.99) or a date ("03.11", "03.11.2026", "11.2026", "2026").
     * Without a search, the most recent receipts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(int $userId, ?string $search, int $limit = self::SEARCH_LIMIT): array
    {
        $query = Receipt::query()
            ->where('user_id', $userId)
            ->whereNull('duplicate_of_id')
            ->latest('receipt_date')
            ->latest('id')
            ->limit($limit);

        foreach (preg_split('/\s+/', trim((string) $search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $query->where(fn (Builder $query) => $this->matchWord($query, $word));
        }

        $currency = SupportedCurrencies::codeForUser($userId);

        return $query->get()->map(fn (Receipt $receipt): array => $this->row($receipt, $currency))->all();
    }

    /**
     * One receipt for the picker, e.g. after a page load with it already chosen.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $userId, int $receiptId): ?array
    {
        $receipt = Receipt::query()->where('user_id', $userId)->find($receiptId);

        return $receipt === null ? null : $this->row($receipt, SupportedCurrencies::codeForUser($userId));
    }

    public function link(Receipt $receipt, Model $linkable): void
    {
        $linkable->receipts()->syncWithoutDetaching([$receipt->id]);
    }

    public function unlink(Receipt $receipt, Model $linkable): void
    {
        $linkable->receipts()->detach($receipt->id);
    }

    /**
     * The receipts attached to a contract or savings entry, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forLinked(Model $linkable): array
    {
        $currency = SupportedCurrencies::codeForUser((int) $linkable->getAttribute('user_id'));

        return $linkable->receipts()
            ->latest('receipt_date')
            ->latest('receipts.id')
            ->get()
            ->map(fn (Receipt $receipt): array => $this->row($receipt, $currency))
            ->all();
    }

    /**
     * What a receipt is attached to, for its page.
     *
     * @return array<int, array{type: string, type_label: string, id: int, label: string, url: string}>
     */
    public function linksOf(Receipt $receipt): array
    {
        $describe = fn (ReceiptLinkType $type, Collection $models): Collection => $models->map(fn (Model $model): array => [
            'type' => $type->value,
            'type_label' => $type->label(),
            'id' => $model->getKey(),
            'label' => $type->describe($model),
            'url' => route($type->routeName(), $model->getKey()),
        ]);

        return $describe(ReceiptLinkType::Contract, $receipt->linkedContracts()->orderBy('name')->get())
            ->concat($describe(ReceiptLinkType::Saving, $receipt->linkedSavings()->latest('saved_on')->get()))
            ->values()
            ->all();
    }

    /**
     * What else the receipt can be attached to, per link type: the user's
     * contracts (by name) and savings entries (newest first), minus those it
     * is already attached to.
     *
     * @return array<int, array{type: string, label: string, options: array<int, array{id: int, label: string}>}>
     */
    public function attachOptions(Receipt $receipt): array
    {
        $linked = collect($this->linksOf($receipt))->map(fn (array $link): string => $link['type'].':'.$link['id']);

        return collect(ReceiptLinkType::cases())
            ->map(fn (ReceiptLinkType $type): array => [
                'type' => $type->value,
                'label' => $type->label(),
                'options' => $type->modelClass()::query()
                    ->where('user_id', $receipt->user_id)
                    ->when($type === ReceiptLinkType::Contract, fn (Builder $query) => $query->orderBy('name'))
                    ->when($type === ReceiptLinkType::Saving, fn (Builder $query) => $query->latest('saved_on'))
                    ->latest('id')
                    ->get()
                    ->reject(fn (Model $model): bool => $linked->contains($type->value.':'.$model->getKey()))
                    ->map(fn (Model $model): array => ['id' => $model->getKey(), 'label' => $type->describe($model)])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Receipt $receipt, string $currency): array
    {
        $vendor = $receipt->vendor ?: 'Receipt';
        $date = $receipt->receipt_date?->format('d.m.Y');
        $amount = SupportedCurrencies::format($receipt->total_amount, $currency);

        return [
            'id' => $receipt->id,
            'vendor' => $vendor,
            'date_label' => $date,
            'amount_label' => $amount,
            'kind' => $receipt->kind->value,
            'label' => implode(' · ', array_filter([$vendor, $date, $amount])),
            'url' => route('receipts.show', $receipt),
        ];
    }

    private function matchWord(Builder $query, string $word): void
    {
        $query->where('vendor', 'like', '%'.$word.'%')
            ->orWhere('receipt_number', 'like', '%'.$word.'%');

        if (preg_match('/^\d+(?:[.,]\d{1,2})?$/', $word) === 1) {
            $amount = (float) str_replace(',', '.', $word);

            preg_match('/[.,]/', $word) === 1
                ? $query->orWhere('total_amount', round($amount, 2))
                : $query->orWhereBetween('total_amount', [$amount, $amount + 0.99]);
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.?(\d{4})?$/', $word, $date) === 1) {
            $query->orWhere(fn (Builder $query) => $query
                ->whereDay('receipt_date', (int) $date[1])
                ->whereMonth('receipt_date', (int) $date[2])
                ->when(($date[3] ?? '') !== '', fn (Builder $query) => $query->whereYear('receipt_date', (int) $date[3])));
        } elseif (preg_match('/^(\d{1,2})[.\/](\d{4})$/', $word, $month) === 1) {
            $query->orWhere(fn (Builder $query) => $query->whereMonth('receipt_date', (int) $month[1])->whereYear('receipt_date', (int) $month[2]));
        } elseif (preg_match('/^\d{4}$/', $word) === 1) {
            $query->orWhereYear('receipt_date', (int) $word);
        }
    }
}
