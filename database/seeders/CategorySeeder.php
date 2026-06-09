<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Seed general budgeting categories for receipts, contracts, and budgets.
     *
     * @see database/data/budget-categories.json
     */
    public function run(): void
    {
        $path = database_path('data/budget-categories.json');

        if (! file_exists($path)) {
            $this->command?->error('budget-categories.json not found.');

            return;
        }

        $catalog = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $parents = 0;
        $children = 0;

        foreach ($catalog as $entry) {
            $parent = Category::updateOrCreate(
                ['slug' => $entry['slug']],
                [
                    'name' => $entry['name'],
                    'parent_id' => null,
                    'description' => $entry['description'] ?? null,
                    'color' => $entry['color'] ?? null,
                    'icon' => $entry['icon'] ?? null,
                    'is_active' => true,
                    'sort_order' => $entry['sort_order'] ?? 0,
                ],
            );

            $parents++;

            foreach ($entry['subcategories'] ?? [] as $index => $subcategoryName) {
                $childSlug = $entry['slug'].'-'.Str::slug($subcategoryName);

                Category::updateOrCreate(
                    ['slug' => $childSlug],
                    [
                        'name' => $subcategoryName,
                        'parent_id' => $parent->id,
                        'is_active' => true,
                        'sort_order' => ($index + 1) * 10,
                    ],
                );

                $children++;
            }
        }

        $this->command?->info("Seeded {$parents} parent categories and {$children} subcategories.");
    }
}
