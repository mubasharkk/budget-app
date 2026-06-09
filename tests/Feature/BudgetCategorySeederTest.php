<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_budgeting_parent_categories(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertNotNull(Category::where('slug', 'groceries')->whereNull('parent_id')->first());
        $this->assertNotNull(Category::where('slug', 'rent-housing')->whereNull('parent_id')->first());
        $this->assertNotNull(Category::where('slug', 'utilities')->whereNull('parent_id')->first());
        $this->assertNotNull(Category::where('slug', 'internet-phone')->whereNull('parent_id')->first());
        $this->assertNotNull(Category::where('slug', 'transport')->whereNull('parent_id')->first());
        $this->assertNotNull(Category::where('slug', 'child-support-family')->whereNull('parent_id')->first());
        $this->assertNotNull(Category::where('slug', 'business-work')->whereNull('parent_id')->first());
        $this->assertNotNull(Category::where('slug', 'credit-loan-payments')->whereNull('parent_id')->first());
        $this->assertNotNull(Category::where('slug', 'bills-subscriptions')->whereNull('parent_id')->first());

        $parents = Category::whereNull('parent_id')->count();
        $this->assertGreaterThanOrEqual(20, $parents);
    }

    public function test_seeds_expected_subcategories(): void
    {
        $this->seed(CategorySeeder::class);

        $groceries = Category::where('slug', 'groceries')->first();
        $utilities = Category::where('slug', 'utilities')->first();
        $credit = Category::where('slug', 'credit-loan-payments')->first();

        $this->assertNotNull($groceries);
        $this->assertNotNull(Category::where('slug', 'groceries-dairy')->where('parent_id', $groceries->id)->first());
        $this->assertNotNull(Category::where('slug', 'utilities-electricity')->where('parent_id', $utilities->id)->first());
        $this->assertNotNull(Category::where('slug', 'credit-loan-payments-credit-card-payment')->where('parent_id', $credit->id)->first());
        $this->assertNotNull(Category::where('slug', 'child-support-family-child-support')->first());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CategorySeeder::class);
        $countAfterFirst = Category::count();

        $this->seed(CategorySeeder::class);
        $countAfterSecond = Category::count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }
}
