<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\User;
use App\Services\AnomalyDetectionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnomalyDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_duplicate_charges(): void
    {
        CarbonImmutable::setTestNow('2026-06-10');

        $user = User::factory()->create();

        Receipt::factory()->for($user)->count(2)->create([
            'vendor' => 'REWE',
            'total_amount' => 42.50,
            'receipt_date' => '2026-06-09',
        ]);

        $anomalies = (new AnomalyDetectionService)->detect($user->id);

        $this->assertNotEmpty($anomalies);
        $this->assertSame('duplicate_charge', $anomalies[0]['type']);

        CarbonImmutable::setTestNow();
    }

    public function test_detects_category_spend_spike(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);

        $prev = Receipt::factory()->for($user)->create(['receipt_date' => '2026-05-10']);
        ReceiptItem::factory()->for($prev)->create([
            'quantity' => 1, 'unit_price' => 40, 'category_id' => $groceries->id,
        ]);

        $curr = Receipt::factory()->for($user)->create(['receipt_date' => '2026-06-10']);
        ReceiptItem::factory()->for($curr)->create([
            'quantity' => 1, 'unit_price' => 120, 'category_id' => $groceries->id,
        ]);

        $anomalies = (new AnomalyDetectionService)->detect($user->id);

        $spike = collect($anomalies)->firstWhere('type', 'category_spike');
        $this->assertNotNull($spike);
        $this->assertSame('Groceries', $spike['metadata']['category']);

        CarbonImmutable::setTestNow();
    }
}
