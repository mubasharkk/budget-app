<?php

namespace App\Domain\Products\Services;

use App\Domain\Analytics\Services\ConsumptionService;
use App\Domain\Identity\Services\UserSettingsService;
use App\Domain\Products\Data\ProductCorrection;
use App\Domain\Products\Exceptions\PackUnitMismatch;
use App\Domain\Products\Support\ProductQuantity;
use App\Models\ConsumableGroup;
use App\Models\Product;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The user's fixes to what makes consumption figures inexact. A fix applies
 * to every purchase of the product, past and future.
 */
class ProductCorrectionService
{
    public function __construct(
        private ConsumableGroupService $groupService,
        private ConsumptionService $consumptionService,
        private UserSettingsService $userSettingsService,
    ) {}

    /**
     * @throws PackUnitMismatch
     */
    public function correct(Product $product, ProductCorrection $correction): Product
    {
        return DB::transaction(function () use ($product, $correction): Product {
            if ($correction->changesPack()) {
                $product->update(['unit' => $correction->unit, 'size' => $correction->size]);
            }

            if ($correction->groupName !== null) {
                // Resolved first so a new name takes the product's unit, not its old name's.
                $group = $this->groupService->resolve($product->user_id, $correction->groupName, $product->unit);

                if ($product->consumable_group_id !== $group->id) {
                    $this->groupService->moveProduct($product, $group->name);
                }
            }

            if ($correction->changesPack()) {
                $this->alignGroupUnit($product->refresh(), $correction->unit);
            }

            return $product;
        });
    }

    /**
     * Make a product for receipt lines that were never matched to one, from
     * every line of that exact name (ignoring case) that counts as consumption.
     * Returns null when the user has no such lines.
     *
     * @throws PackUnitMismatch
     */
    public function nameUnmatchedItem(User $user, string $name, ProductCorrection $correction): ?Product
    {
        $lowerName = mb_strtolower($name);
        $lines = $this->consumptionService->consumedLines($user->id, $this->userSettingsService->consumptionScope($user))
            ->whereNull('receipt_items.product_id')
            ->get(['receipt_items.id', 'receipt_items.name', 'receipt_items.category_id'])
            ->filter(fn (ReceiptItem $item): bool => mb_strtolower($item->name) === $lowerName);

        if ($lines->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($user, $lines, $lowerName, $correction): Product {
            // Suffixed, so a similar name never overwrites another product's pack.
            $product = Product::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'normalized_name' => Str::limit(Str::slug($lowerName), 200, '').'-'.substr(sha1($lowerName), 0, 8),
                ],
                ['name' => $lines->first()->name, 'category_id' => $lines->first()->category_id],
            );

            // A query update: saving each model would recalculate the line's total.
            ReceiptItem::query()->whereKey($lines->modelKeys())->update(['product_id' => $product->id]);

            return $this->correct($product, $correction);
        });
    }

    /**
     * A general name takes the pack's unit unless other products are already
     * measured in its own, in which case the pack is refused.
     *
     * @throws PackUnitMismatch
     */
    private function alignGroupUnit(Product $product, string $unit): void
    {
        /** @var ConsumableGroup|null $group */
        $group = $product->consumableGroup;

        if ($group === null || $group->unit === $unit) {
            return;
        }

        $othersUseIt = $group->unit !== null && $group->products()->whereKeyNot($product->id)->get(['unit', 'size'])
            ->contains(fn (Product $other): bool => (ProductQuantity::perUnit($other->unit, $other->size)['unit'] ?? null) === $group->unit);

        if ($othersUseIt) {
            throw PackUnitMismatch::with($group->name, $group->unit);
        }

        $group->update(['unit' => $unit]);
    }
}
