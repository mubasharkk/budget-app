<?php

namespace App\Domain\Categories\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ReceiptItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repairs categories filed under the wrong parent by receipts read before
 * CategoryFilingService existed, so each receipt line has a top-level
 * category with a subcategory under that same parent:
 * - a line whose category is a subcategory moves to its parent, keeping it
 *   as the subcategory when the line had none;
 * - a subcategory under another parent (or a top-level one) is replaced by
 *   the same name under the line's own category, found or created;
 * - a product whose category is a subcategory moves to its parent.
 */
class MisfiledCategoryRepairService
{
    public function __construct(private CategoryFilingService $categoryFilingService) {}

    /**
     * What a repair would change, one row per misfiled combination of
     * category and subcategory. Changes nothing.
     *
     * @return Collection<int, array{category_id: ?int, subcategory_id: ?int, to_category_id: ?int, to_subcategory_name: ?string, to_subcategory_id: ?int, lines: int, from: string, to: string}>
     */
    public function lineFixes(): Collection
    {
        $categories = Category::query()->get(['id', 'name', 'parent_id'])->keyBy('id');

        return ReceiptItem::query()
            ->where(fn ($query) => $query->whereNotNull('category_id')->orWhereNotNull('subcategory_id'))
            ->groupBy('category_id', 'subcategory_id')
            ->select('category_id', 'subcategory_id')
            ->selectRaw('COUNT(*) as lines')
            ->get()
            ->map(fn (ReceiptItem $row): ?array => $this->fixFor($row->category_id, $row->subcategory_id, (int) $row->lines, $categories))
            ->filter()
            ->sortBy('from')
            ->values();
    }

    /**
     * Products whose category is a subcategory, with the parent they move to.
     *
     * @return Collection<int, array{category_id: int, to_category_id: int, products: int, from: string, to: string}>
     */
    public function productFixes(): Collection
    {
        $categories = Category::query()->get(['id', 'name', 'parent_id'])->keyBy('id');

        return Product::query()
            ->whereIn('category_id', $categories->whereNotNull('parent_id')->keys())
            ->groupBy('category_id')
            ->select('category_id')
            ->selectRaw('COUNT(*) as products')
            ->get()
            ->map(function (Product $row) use ($categories): array {
                $category = $categories->get($row->category_id);

                return [
                    'category_id' => $category->id,
                    'to_category_id' => $category->parent_id,
                    'products' => (int) $row->products,
                    'from' => $this->path($category->id, null, $categories),
                    'to' => $this->path($category->parent_id, null, $categories),
                ];
            })
            ->sortBy('from')
            ->values();
    }

    /**
     * Apply the fixes. Returns how many receipt lines and products changed.
     *
     * @return array{lines: int, products: int}
     */
    public function repair(): array
    {
        return DB::transaction(function (): array {
            $lines = $this->lineFixes()->sum(fn (array $fix): int => ReceiptItem::query()
                ->where(fn ($query) => $fix['category_id'] === null ? $query->whereNull('category_id') : $query->where('category_id', $fix['category_id']))
                ->where(fn ($query) => $fix['subcategory_id'] === null ? $query->whereNull('subcategory_id') : $query->where('subcategory_id', $fix['subcategory_id']))
                ->update([
                    'category_id' => $fix['to_category_id'],
                    'subcategory_id' => $fix['to_subcategory_id']
                        ?? $this->categoryFilingService->subcategoryId($fix['to_subcategory_name'], $fix['to_category_id']),
                ]));

            $products = $this->productFixes()->sum(fn (array $fix): int => Product::query()
                ->where('category_id', $fix['category_id'])
                ->update(['category_id' => $fix['to_category_id']]));

            return ['lines' => $lines, 'products' => $products];
        });
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array<string, mixed>|null
     */
    private function fixFor(?int $categoryId, ?int $subcategoryId, int $lines, Collection $categories): ?array
    {
        $category = $categoryId !== null ? $categories->get($categoryId) : null;
        $subcategory = $subcategoryId !== null ? $categories->get($subcategoryId) : null;

        $toCategoryId = $categoryId;
        $toSubcategoryId = $subcategoryId;
        $toSubcategoryName = null;

        if ($category?->parent_id !== null) {
            $toCategoryId = $category->parent_id;
            $toSubcategoryId ??= $category->id;
            $subcategory ??= $category;
        }

        if ($subcategory !== null && $subcategory->id === $toCategoryId) {
            $toSubcategoryId = null;
        } elseif ($subcategory !== null && $subcategory->parent_id !== $toCategoryId) {
            if ($toCategoryId === null) {
                $toCategoryId = $subcategory->parent_id ?? $subcategory->id;
                $toSubcategoryId = $subcategory->parent_id !== null ? $subcategory->id : null;
            } else {
                $toSubcategoryId = null;
                $toSubcategoryName = $subcategory->name;
            }
        }

        if ($toCategoryId === $categoryId && $toSubcategoryId === $subcategoryId && $toSubcategoryName === null) {
            return null;
        }

        return [
            'category_id' => $categoryId,
            'subcategory_id' => $subcategoryId,
            'to_category_id' => $toCategoryId,
            'to_subcategory_name' => $toSubcategoryName,
            'to_subcategory_id' => $toSubcategoryId,
            'lines' => $lines,
            'from' => $this->path($categoryId, $subcategoryId, $categories),
            'to' => $this->path($toCategoryId, $toSubcategoryId, $categories, $toSubcategoryName),
        ];
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function path(?int $categoryId, ?int $subcategoryId, Collection $categories, ?string $subcategoryName = null): string
    {
        $name = fn (?int $id): string => $id === null ? '—' : ($categories->get($id)?->name ?? "#{$id}");
        $parentId = $subcategoryId !== null ? $categories->get($subcategoryId)?->parent_id : null;
        $subcategory = $subcategoryName ?? match (true) {
            $subcategoryId === null => null,
            $parentId === null => $name($subcategoryId).' (a main category)',
            $parentId !== $categoryId => $name($subcategoryId).' (under '.$name($parentId).')',
            default => $name($subcategoryId),
        };

        return $name($categoryId).($subcategory !== null ? ' › '.$subcategory : '');
    }
}
