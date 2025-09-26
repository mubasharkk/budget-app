<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create parent categories
        $groceries = Category::firstOrCreate(
            ['name' => 'Groceries', 'parent_id' => null],
            ['name' => 'Groceries', 'parent_id' => null, 'slug' => 'Groceries']
        );

        $building = Category::firstOrCreate(
            ['name' => 'Building', 'parent_id' => null],
            ['name' => 'Building', 'parent_id' => null, 'slug' => 'Building']
        );

        $building = Category::firstOrCreate(
            ['name' => 'Pharmacy', 'parent_id' => null],
            ['name' => 'Pharmacy', 'parent_id' => null, 'slug' => 'Pharmacy']
        );

        // Create Groceries subcategories
        $grocerySubcategories = [
            'Fruits', 'Vegetables', 'Dairy', 'Bakery', 'Beverages',
            'Snacks', 'Meat', 'Frozen', 'Household', 'Personal Care'
        ];

        foreach ($grocerySubcategories as $subcategory) {
            Category::firstOrCreate(
                ['name' => $subcategory, 'parent_id' => $groceries->id, 'slug' => Str::slug($subcategory)],
                ['name' => $subcategory, 'parent_id' => $groceries->id, 'slug' => Str::slug($subcategory)]
            );
        }

        // Create Building subcategories
        $buildingSubcategories = [
            'Tools', 'Hardware', 'Plumbing', 'Electrical', 'Paint',
            'Lumber', 'Fasteners', 'Adhesives/Sealants', 'Safety'
        ];

        foreach ($buildingSubcategories as $subcategory) {
            Category::firstOrCreate(
                ['name' => $subcategory, 'parent_id' => $building->id, 'slug' => Str::slug($subcategory)],
                ['name' => $subcategory, 'parent_id' => $building->id, 'slug' => Str::slug($subcategory)]
            );
        }
    }
}
