<?php

namespace Tests\Feature;

use App\Domain\Products\Services\PurchaseHistoryService;
use App\Models\Category;
use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_products_carry_frequency_and_the_latest_price_paid(): void
    {
        $user = User::factory()->create();
        $eggs = Product::factory()->for($user)->create(['name' => 'Eier']);
        $milk = Product::factory()->for($user)->create(['name' => 'Milch']);
        PriceObservation::factory()->for($eggs)->create(['unit_price' => 2.29, 'vendor' => 'Aldi', 'observed_at' => now()->subDays(20)]);
        PriceObservation::factory()->for($eggs)->create(['unit_price' => 2.49, 'vendor' => 'Rewe', 'observed_at' => now()->subDays(2)]);
        PriceObservation::factory()->for($milk)->create(['unit_price' => 0.99, 'observed_at' => now()->subDays(5)]);

        $products = app(PurchaseHistoryService::class)->recentProducts($user->id);

        $this->assertSame(['Eier', 'Milch'], $products->pluck('name')->all());
        $this->assertSame(2, $products[0]->purchaseCount);
        $this->assertSame(2.49, $products[0]->lastPaidPrice);
        $this->assertSame('Rewe', $products[0]->lastVendor);
    }

    public function test_old_purchases_and_other_users_products_are_left_out(): void
    {
        $user = User::factory()->create();
        $old = Product::factory()->for($user)->create();
        PriceObservation::factory()->for($old)->create(['observed_at' => now()->subDays(PurchaseHistoryService::LOOKBACK_DAYS + 5)]);
        PriceObservation::factory()->create(['observed_at' => now()]);

        $this->assertCount(0, app(PurchaseHistoryService::class)->recentProducts($user->id));
    }

    public function test_purchased_category_ids_include_categories_and_subcategories_of_own_receipts_only(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create();
        $dairy = Category::factory()->childOf($groceries)->create();
        $other = Category::factory()->create();
        ReceiptItem::factory()->for(Receipt::factory()->for($user))->create(['category_id' => $groceries->id, 'subcategory_id' => $dairy->id]);
        ReceiptItem::factory()->create(['category_id' => $other->id]);

        $ids = app(PurchaseHistoryService::class)->purchasedCategoryIds($user->id);

        $this->assertEqualsCanonicalizing([$groceries->id, $dairy->id], $ids);
    }
}
