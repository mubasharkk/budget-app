<?php

namespace Tests\Feature;

use App\Enums\IncomeType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Services\DashboardSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_returns_all_aspect_sections(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        $user = User::factory()->create([
            'monthly_income' => 3000,
            'income_type' => IncomeType::Net,
        ]);
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        $receipt = Receipt::factory()->for($user)->create([
            'receipt_date' => '2026-06-10',
            'total_amount' => 50,
        ]);
        ReceiptItem::factory()->for($receipt)->create([
            'name' => 'Milk',
            'quantity' => 1,
            'unit_price' => 50,
            'category_id' => $groceries->id,
        ]);

        Contract::factory()->for($user)->create([
            'amount' => 30,
            'billing_cycle' => 'monthly',
        ]);

        Budget::factory()->for($user)->create([
            'category_id' => $groceries->id,
            'amount' => 500,
            'starts_on' => '2026-06-01',
        ]);

        $snapshot = app(DashboardSnapshotService::class)->snapshot($user->id, 'month');

        $this->assertArrayHasKey('expenses', $snapshot);
        $this->assertArrayHasKey('budgets', $snapshot);
        $this->assertArrayHasKey('savings', $snapshot);
        $this->assertArrayHasKey('consumption', $snapshot);
        $this->assertArrayHasKey('contracts', $snapshot);
        $this->assertArrayHasKey('agent', $snapshot);
        $this->assertArrayHasKey('income', $snapshot);
        $this->assertSame(3000.0, $snapshot['income']['period_income']);
        $this->assertSame(50.0, $snapshot['expenses']['variable']);
        $this->assertSame(30.0, $snapshot['contracts']['monthly_fixed']);
        $this->assertSame('Milk', $snapshot['consumption']['top_item']);

        CarbonImmutable::setTestNow();
    }

    public function test_snapshot_endpoint_is_accessible(): void
    {
        $user = User::factory()->create([
            'monthly_income' => 3000,
            'income_type' => IncomeType::Net,
        ]);

        $this->actingAs($user)
            ->getJson('/dashboard/snapshot?period=month')
            ->assertOk()
            ->assertJsonStructure([
                'expenses',
                'budgets',
                'savings',
                'consumption',
                'contracts',
                'agent',
                'income',
            ]);
    }
}
