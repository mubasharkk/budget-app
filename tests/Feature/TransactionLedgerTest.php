<?php

namespace Tests\Feature;

use App\Enums\IncomeType;
use App\Enums\ReceiptKind;
use App\Models\Contract;
use App\Models\Income;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_income_and_expenses_in_range_newest_first_with_totals(): void
    {
        $user = User::factory()->create();

        Receipt::factory()->for($user)->create(['vendor' => 'REWE', 'receipt_date' => '2026-06-10', 'total_amount' => 40]);
        Receipt::factory()->for($user)->create(['vendor' => 'Old', 'receipt_date' => '2026-05-10', 'total_amount' => 99]);
        Receipt::factory()->for($user)->create([
            'vendor' => 'Acme payslip',
            'receipt_date' => '2026-06-28',
            'total_amount' => 2000,
            'kind' => ReceiptKind::Income,
        ]);
        Income::factory()->for($user)->create(['source' => 'Acme', 'received_on' => '2026-06-28', 'amount' => 2000]);
        Income::factory()->for($user)->create(['source' => 'Refund', 'received_on' => '2026-07-02', 'amount' => 15]);
        Contract::factory()->for($user)->create(['name' => 'Netflix', 'amount' => 12.99, 'last_paid_at' => '2026-06-30']);
        Receipt::factory()->for(User::factory())->create(['receipt_date' => '2026-06-11', 'total_amount' => 500]);

        $response = $this->actingAs($user)
            ->getJson('/dashboard/transactions?start_date=2026-06-01&end_date=2026-06-30')
            ->assertOk()
            ->assertJsonPath('start', '2026-06-01')
            ->assertJsonPath('end', '2026-06-30')
            ->assertJsonPath('totals.income', 2000)
            ->assertJsonPath('totals.expenses', 52.99)
            ->assertJsonPath('totals.net', 1947.01);

        $this->assertSame(
            ['Netflix', 'Acme', 'REWE'],
            array_column($response->json('transactions'), 'description'),
        );
        $this->assertSame(
            ['expense', 'income', 'expense'],
            array_column($response->json('transactions'), 'type'),
        );
    }

    public function test_defaults_to_the_current_month(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/dashboard/transactions')
            ->assertOk()
            ->assertJsonPath('start', now()->startOfMonth()->toDateString())
            ->assertJsonPath('end', now()->endOfMonth()->toDateString())
            ->assertJsonPath('transactions', []);
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/dashboard/transactions?start_date=2026-06-30&end_date=2026-06-01')
            ->assertJsonValidationErrors('end_date');
    }

    public function test_monthly_income_is_booked_on_the_first_of_each_started_month(): void
    {
        $this->travelTo('2026-08-15');
        $user = User::factory()->create(['monthly_income' => 3000, 'income_type' => IncomeType::Net]);
        Receipt::factory()->for($user)->create(['receipt_date' => '2026-07-03', 'total_amount' => 100]);

        $response = $this->actingAs($user)
            ->getJson('/dashboard/transactions?start_date=2026-06-10&end_date=2026-12-31')
            ->assertOk()
            ->assertJsonPath('totals.income', 6000)
            ->assertJsonPath('totals.net', 5900);

        $monthly = collect($response->json('transactions'))->where('source', 'monthly_income')->values();
        $this->assertSame(['2026-08-01', '2026-07-01'], $monthly->pluck('date')->all());
        $this->assertSame('Monthly income (Net)', $monthly->first()['description']);
    }
}
