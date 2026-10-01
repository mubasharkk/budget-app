<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpcomingBillsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_lists_active_bills_in_the_window_soonest_first(): void
    {
        CarbonImmutable::setTestNow('2026-06-10');
        $user = User::factory()->create();

        Contract::factory()->for($user)->create(['name' => 'Rent', 'amount' => 900, 'next_billing_date' => '2026-06-30']);
        Contract::factory()->for($user)->create(['name' => 'Netflix', 'amount' => 12.99, 'next_billing_date' => '2026-06-10']);
        Contract::factory()->for($user)->create(['name' => 'Gym', 'amount' => 30, 'next_billing_date' => '2026-06-17']);
        Contract::factory()->for($user)->create(['name' => 'Insurance', 'amount' => 200, 'next_billing_date' => '2026-08-01']);
        Contract::factory()->for($user)->create(['name' => 'Already billed', 'amount' => 50, 'next_billing_date' => '2026-06-09']);
        Contract::factory()->for($user)->cancelled()->create(['name' => 'Cancelled', 'amount' => 70, 'next_billing_date' => '2026-06-12']);
        Contract::factory()->for(User::factory())->create(['amount' => 500, 'next_billing_date' => '2026-06-12']);

        $response = $this->actingAs($user)
            ->getJson('/dashboard/upcoming-bills?days=30')
            ->assertOk()
            ->assertJsonPath('days', 30)
            ->assertJsonPath('count', 3)
            ->assertJsonPath('total', 942.99)
            ->assertJsonPath('due_this_week.count', 2)
            ->assertJsonPath('due_this_week.total', 42.99)
            ->assertJsonPath('bills.0.days_until_due', 0)
            ->assertJsonPath('bills.1.days_until_due', 7);

        $this->assertSame(['Netflix', 'Gym', 'Rent'], array_column($response->json('bills'), 'name'));
    }

    public function test_window_can_be_narrowed_and_invalid_values_fall_back_to_thirty_days(): void
    {
        CarbonImmutable::setTestNow('2026-06-10');
        $user = User::factory()->create();
        Contract::factory()->for($user)->create(['name' => 'Rent', 'amount' => 900, 'next_billing_date' => '2026-06-30']);

        $this->actingAs($user)->getJson('/dashboard/upcoming-bills?days=7')
            ->assertJsonPath('days', 7)
            ->assertJsonPath('count', 0);

        $this->actingAs($user)->getJson('/dashboard/upcoming-bills?days=999')
            ->assertJsonPath('days', 30)
            ->assertJsonPath('count', 1);
    }
}
