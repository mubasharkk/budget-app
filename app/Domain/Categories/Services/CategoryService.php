<?php

namespace App\Domain\Categories\Services;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryService
{
    /**
     * Parent categories with their subcategories nested.
     *
     * @return Collection<int, Category>
     */
    public function tree(): Collection
    {
        return Category::query()
            ->with('subcategories')
            ->whereNull('parent_id')
            ->get();
    }

    /**
     * Parent categories as lightweight select options.
     *
     * @return Collection<int, Category>
     */
    public function parentOptions(): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
