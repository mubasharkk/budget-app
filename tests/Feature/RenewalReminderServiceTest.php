<?php

namespace Tests\Feature;

use App\Domain\Contracts\Services\RenewalReminderService;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenewalReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_active_contracts_billing_or_ending_soon(): void
    {
        $this->travelTo('2026-06-10');
        $user = User::factory()->create();
        $provider = Provider::factory()->for($user)->create(['name' => 'Vodafone']);

        $billing = Contract::factory()->for($user)->create([
            'name' => 'Mobile', 'provider_id' => $provider->id, 'amount' => 20,
            'next_billing_date' => '2026-06-15', 'end_date' => null, 'status' => ContractStatus::Active,
        ]);
        Contract::factory()->for($user)->create([
            'name' => 'Gym', 'next_billing_date' => '2026-09-01', 'end_date' => '2026-06-20', 'status' => ContractStatus::Active,
        ]);
        Contract::factory()->for($user)->create(['name' => 'Later', 'next_billing_date' => '2026-07-30', 'end_date' => null]);
        Contract::factory()->for($user)->create(['name' => 'Paused', 'next_billing_date' => '2026-06-12', 'status' => ContractStatus::Paused]);
        Contract::factory()->create(['name' => 'Not mine', 'next_billing_date' => '2026-06-12']);

        $upcoming = app(RenewalReminderService::class)->upcoming($user->id);

        $this->assertSame(['Mobile', 'Gym'], $upcoming->pluck('name')->all());

        $mobile = $upcoming->first();
        $this->assertSame($billing->id, $mobile->contract_id);
        $this->assertSame('Vodafone', $mobile->provider);
        $this->assertSame(20.0, $mobile->amount);
        $this->assertSame('2026-06-15', $mobile->next_billing_date);
        $this->assertEquals(5, $mobile->days_until_billing);
        $this->assertFalse($mobile->is_renewal);
        $this->assertTrue($upcoming->last()->is_renewal);
        $this->assertSame('2026-06-20', $upcoming->last()->end_date);
    }
}
