<?php

namespace App\Domain\Categories\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Files a category and subcategory name (as read from a receipt) under the
 * right category rows, creating them when they do not exist yet.
 */
class CategoryFilingService
{
    /**
     * Find or create a top-level category by name. A name that only exists as
     * a subcategory stands for that subcategory's parent.
     */
    public function categoryId(string $categoryName): ?int
    {
        if ($categoryName === '') {
            return null;
        }

        $matches = fn (): Builder => Category::query()->where(fn (Builder $query) => $query
            ->where('name', $categoryName)
            ->orWhere('slug', Str::slug($categoryName)));

        $category = $matches()->whereNull('parent_id')->first() ?? $matches()->first();

        if ($category !== null) {
            return $category->parent_id ?? $category->id;
        }

        return Category::create([
            'name' => $categoryName,
            'slug' => Str::slug($categoryName),
            'parent_id' => null,
        ])->id;
    }

    /**
     * Find or create a subcategory under the given parent category. Slugs are
     * unique across parents, so a shared name gets a suffixed slug.
     */
    public function subcategoryId(?string $subcategoryName, ?int $categoryId): ?int
    {
        if (empty($subcategoryName) || ! $categoryId) {
            return null;
        }

        $slug = Str::slug($subcategoryName);
        $slugUnderParent = $slug.'-'.$categoryId;
        $subcategory = Category::query()
            ->where('parent_id', $categoryId)
            ->where(fn (Builder $query) => $query->where('name', $subcategoryName)->orWhereIn('slug', [$slug, $slugUnderParent]))
            ->first();

        if ($subcategory !== null) {
            return $subcategory->id;
        }

        if (Category::query()->where('slug', $slug)->exists()) {
            $slug = $slugUnderParent;
        }

        return Category::create([
            'name' => $subcategoryName,
            'slug' => $slug,
            'parent_id' => $categoryId,
        ])->id;
    }
}
