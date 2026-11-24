<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ReceiptItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairMisfiledCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_misfiled_categories_and_repairs_them_only_with_fix(): void
    {
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $dairy = Category::factory()->create(['name' => 'Dairy', 'parent_id' => $groceries->id]);
        $household = Category::factory()->create(['name' => 'Household']);
        $cleaning = Category::factory()->create(['name' => 'Cleaning', 'parent_id' => $household->id]);

        $filedAsSubcategory = ReceiptItem::factory()->create(['category_id' => $dairy->id, 'subcategory_id' => null]);
        $underOtherParent = ReceiptItem::factory()->create(['category_id' => $groceries->id, 'subcategory_id' => $cleaning->id]);
        $topLevelAsSubcategory = ReceiptItem::factory()->create(['category_id' => $groceries->id, 'subcategory_id' => $household->id]);
        $onlySubcategory = ReceiptItem::factory()->create(['category_id' => null, 'subcategory_id' => $dairy->id]);
        $fine = ReceiptItem::factory()->create(['category_id' => $household->id, 'subcategory_id' => $cleaning->id]);
        $product = Product::factory()->create(['category_id' => $dairy->id]);

        $this->artisan('categories:repair-misfiled')
            ->expectsTable(['Receipt lines', 'Filed as', 'Becomes'], [
                [1, 'Dairy', 'Groceries › Dairy'],
                [1, 'Groceries › Cleaning (under Household)', 'Groceries › Cleaning'],
                [1, 'Groceries › Household (a main category)', 'Groceries › Household'],
                [1, '— › Dairy (under Groceries)', 'Groceries › Dairy'],
            ])
            ->expectsOutput('Run again with --fix to apply these changes.')
            ->assertSuccessful();
        $this->assertSame($dairy->id, $filedAsSubcategory->fresh()->category_id);

        $this->artisan('categories:repair-misfiled', ['--fix' => true])
            ->expectsOutput('Repaired 4 receipt line(s) and 1 product(s).')
            ->assertSuccessful();

        $cleaningUnderGroceries = Category::query()->where('name', 'Cleaning')->where('parent_id', $groceries->id)->sole();
        $householdUnderGroceries = Category::query()->where('name', 'Household')->where('parent_id', $groceries->id)->sole();
        $this->assertSame([$groceries->id, $dairy->id], [$filedAsSubcategory->fresh()->category_id, $filedAsSubcategory->fresh()->subcategory_id]);
        $this->assertSame([$groceries->id, $cleaningUnderGroceries->id], [$underOtherParent->fresh()->category_id, $underOtherParent->fresh()->subcategory_id]);
        $this->assertSame([$groceries->id, $householdUnderGroceries->id], [$topLevelAsSubcategory->fresh()->category_id, $topLevelAsSubcategory->fresh()->subcategory_id]);
        $this->assertSame([$groceries->id, $dairy->id], [$onlySubcategory->fresh()->category_id, $onlySubcategory->fresh()->subcategory_id]);
        $this->assertSame([$household->id, $cleaning->id], [$fine->fresh()->category_id, $fine->fresh()->subcategory_id]);
        $this->assertSame($groceries->id, $product->fresh()->category_id);

        $this->artisan('categories:repair-misfiled')->expectsOutput('No misfiled categories found.')->assertSuccessful();
    }
}
