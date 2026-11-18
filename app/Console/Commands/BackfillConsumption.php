<?php

namespace App\Console\Commands;

use App\Domain\Products\Services\ConsumableGroupService;
use App\Domain\Products\Support\LineItemClassifier;
use App\Enums\LineItemType;
use App\Jobs\MatchReceiptItems;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * One-off preparation of receipts read before consumption insights existed:
 * labels deposit, bag, discount and fee lines, matches unmatched lines to
 * products and gives products their general name.
 */
class BackfillConsumption extends Command
{
    protected $signature = 'consumption:backfill
        {--user= : Only the given user id}
        {--skip-ai : Only label lines by name; make no OpenAI calls}';

    protected $description = 'Label non-product receipt lines, match unmatched lines to products and give products a general name';

    public function handle(ConsumableGroupService $consumableGroupService): int
    {
        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;

        $this->info("Labelled {$this->labelLines($userId)} deposit, bag, discount or fee lines.");

        if ($this->option('skip-ai')) {
            return self::SUCCESS;
        }

        $this->info("Queued product matching for {$this->queueMatching($userId)} receipts with unmatched lines.");

        $named = $this->userIds($userId)->sum(fn (int $id): int => $consumableGroupService->nameUngroupedProducts($id));
        $this->info("Gave {$named} products a general name.");

        return self::SUCCESS;
    }

    private function labelLines(?int $userId): int
    {
        $labelled = 0;

        ReceiptItem::query()
            ->with('category:id,name')
            ->where('line_type', LineItemType::Product->value)
            ->when($userId !== null, fn ($query) => $query->whereHas('receipt', fn ($receipt) => $receipt->where('user_id', $userId)))
            ->chunkById(500, function (Collection $items) use (&$labelled): void {
                foreach ($items as $item) {
                    $type = LineItemClassifier::classify((string) $item->name, (float) $item->total, $item->category?->name);

                    if (! $type->isConsumption()) {
                        $item->update(['line_type' => $type]);
                        $labelled++;
                    }
                }
            });

        return $labelled;
    }

    private function queueMatching(?int $userId): int
    {
        $receipts = Receipt::query()
            ->where('status', 'processed')
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->whereHas('items', fn ($items) => $items
                ->whereNull('product_id')
                ->where('line_type', LineItemType::Product->value))
            ->get();

        $receipts->each(fn (Receipt $receipt) => MatchReceiptItems::dispatch($receipt));

        return $receipts->count();
    }

    /**
     * @return Collection<int, int>
     */
    private function userIds(?int $userId): Collection
    {
        return $userId !== null
            ? collect([$userId])
            : Product::query()->whereNull('consumable_group_id')->distinct()->pluck('user_id');
    }
}
