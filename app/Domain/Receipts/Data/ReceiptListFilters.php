<?php

namespace App\Domain\Receipts\Data;

/**
 * Sanitised listing options for the receipts index; unknown values fall back to defaults.
 */
final readonly class ReceiptListFilters
{
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public const SORTABLE_COLUMNS = ['created_at', 'receipt_date', 'total_amount', 'vendor'];

    public const STATUSES = ['pending', 'processed', 'failed'];

    public function __construct(
        public int $perPage = 50,
        public string $sort = 'created_at',
        public string $direction = 'desc',
        public ?string $status = null,
        public ?string $search = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $perPage = (int) ($input['per_page'] ?? 50);
        $sort = $input['sort'] ?? null;
        $status = $input['status'] ?? null;

        return new self(
            perPage: in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 50,
            sort: in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'created_at',
            direction: ($input['direction'] ?? null) === 'asc' ? 'asc' : 'desc',
            status: in_array($status, self::STATUSES, true) ? $status : null,
            search: trim((string) ($input['search'] ?? '')) ?: null,
        );
    }

    /**
     * @return array{search: ?string, status: ?string, sort: string, direction: string, per_page: int}
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'per_page' => $this->perPage,
        ];
    }
}
