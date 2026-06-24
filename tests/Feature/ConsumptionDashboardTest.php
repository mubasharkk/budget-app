<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumptionDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumption_endpoint_returns_items_for_month_period(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        $receipt = Receipt::factory()->for($user)->create(['receipt_date' => '2026-06-10']);
        ReceiptItem::factory()->for($receipt)->create([
            'name' => 'Milk',
            'quantity' => 4,
            'unit_price' => 1,
            'category_id' => $groceries->id,
        ]);

        $response = $this->actingAs($user)->getJson('/dashboard/consumption?period=month&limit=20');

        $response->assertOk()
            ->assertJsonPath('period', 'month')
            ->assertJsonPath('limit', 20)
            ->assertJsonPath('items.0.item_name', 'Milk')
            ->assertJsonPath('items.0.total_quantity', 4);
    }

    public function test_consumption_endpoint_filters_by_category_and_quarter(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $other = Category::factory()->create(['name' => 'Other', 'parent_id' => null]);

        $receipt = Receipt::factory()->for($user)->create(['receipt_date' => '2026-05-15']);
        ReceiptItem::factory()->for($receipt)->create([
            'name' => 'Milk',
            'quantity' => 2,
            'category_id' => $groceries->id,
        ]);
        ReceiptItem::factory()->for($receipt)->create([
            'name' => 'Soap',
            'quantity' => 9,
            'category_id' => $other->id,
        ]);

        $response = $this->actingAs($user)->getJson(
            '/dashboard/consumption?period=quarter&limit=10&category_id='.$groceries->id
        );

        $response->assertOk()
            ->assertJsonPath('period', 'quarter')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.item_name', 'Milk');
    }

    public function test_consumption_endpoint_respects_limit_and_metric(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $receipt = Receipt::factory()->for($user)->create(['receipt_date' => now()->toDateString()]);
        ReceiptItem::factory()->for($receipt)->create([
            'name' => 'Cheap',
            'quantity' => 1,
            'unit_price' => 1,
            'category_id' => $category->id,
        ]);
        ReceiptItem::factory()->for($receipt)->create([
            'name' => 'Bulk',
            'quantity' => 50,
            'unit_price' => 0.1,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($user)->getJson(
            '/dashboard/consumption?period=week&limit=10&metric=spend'
        );

        $response->assertOk()
            ->assertJsonPath('metric', 'spend')
            ->assertJsonPath('limit', 10)
            ->assertJsonPath('period', 'week')
            ->assertJsonPath('items.0.item_name', 'Bulk')
            ->assertJsonPath('items.1.item_name', 'Cheap');
    }
}
