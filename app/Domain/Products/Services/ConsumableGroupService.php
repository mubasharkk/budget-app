<?php

namespace App\Domain\Products\Services;

use App\Domain\Products\Support\ProductQuantity;
use App\Domain\Shared\Services\LlmService;
use App\Models\Category;
use App\Models\ConsumableGroup;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * General names ("Milk", "Bananas") that group a user's products of any brand
 * or size for the consumption insights.
 */
class ConsumableGroupService
{
    /**
     * How many products one AI call names.
     */
    public const BATCH_SIZE = 100;

    public function __construct(private LlmService $llmService) {}

    /**
     * The user's general names, for the AI to reuse.
     *
     * @return array<int, array{name: string, unit: ?string}>
     */
    public function namesForPrompt(int $userId): array
    {
        return ConsumableGroup::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get(['name', 'unit'])
            ->map(fn (ConsumableGroup $group): array => ['name' => $group->name, 'unit' => $group->unit])
            ->all();
    }

    /**
     * Find the user's general name case-insensitively, or create it. A unit is
     * only kept when it is one of the base units (l, kg, pcs).
     */
    public function resolve(int $userId, ?string $name, ?string $unit = null): ?ConsumableGroup
    {
        $name = Str::ucfirst(Str::squish((string) $name));

        if ($name === '') {
            return null;
        }

        $baseUnit = ProductQuantity::baseUnit($unit);
        $group = ConsumableGroup::query()
            ->where('user_id', $userId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();

        if ($group === null) {
            return ConsumableGroup::query()->create(['user_id' => $userId, 'name' => $name, 'unit' => $baseUnit]);
        }

        if ($group->unit === null && $baseUnit !== null) {
            $group->update(['unit' => $baseUnit]);
        }

        return $group;
    }

    /**
     * Give a product its general name unless it already has one (a name the
     * user may have corrected is never overwritten).
     */
    public function assign(Product $product, ?string $name, ?string $unit = null): void
    {
        if ($product->consumable_group_id !== null) {
            return;
        }

        $group = $this->resolve($product->user_id, $name, $unit);

        if ($group !== null) {
            $product->update(['consumable_group_id' => $group->id]);
        }
    }

    /**
     * Ask the AI for the general names of products that have none yet, in
     * batches. Returns how many products were named.
     */
    public function nameUngroupedProducts(int $userId): int
    {
        $named = 0;

        Product::query()
            ->where('user_id', $userId)
            ->whereNull('consumable_group_id')
            ->orderBy('id')
            ->chunkById(self::BATCH_SIZE, function ($products) use ($userId, &$named): void {
                $categories = Category::query()->whereIn('id', $products->pluck('category_id')->filter())->pluck('name', 'id');

                $result = $this->llmService->groupProducts(
                    $products->map(fn (Product $product): array => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'brand' => $product->brand,
                        'unit' => $product->unit,
                        'size' => $product->size,
                        'category' => $categories[$product->category_id] ?? null,
                    ])->values()->all(),
                    $this->namesForPrompt($userId),
                );

                if (! $result['success']) {
                    Log::warning('Naming products failed', ['user_id' => $userId, 'error' => $result['error'] ?? null]);

                    return;
                }

                $answers = collect($result['data']['groups'] ?? [])->keyBy('product_id');

                foreach ($products as $product) {
                    $answer = $answers->get($product->id);

                    if ($answer !== null && filled($answer['group'] ?? null)) {
                        $this->assign($product, $answer['group'], $answer['group_unit'] ?? null);
                        $named++;
                    }
                }
            });

        return $named;
    }
}
