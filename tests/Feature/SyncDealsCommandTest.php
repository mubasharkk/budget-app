<?php

namespace Tests\Feature;

use App\Jobs\SyncDealsForPostalCode;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncDealsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_job_is_queued_per_postal_code_in_use_and_expired_offers_are_pruned(): void
    {
        Queue::fake();
        User::factory()->withPostalCode('10115')->count(2)->create();
        User::factory()->withPostalCode('80331')->create();
        $expired = Deal::factory()->availableIn('10115')->expired()->create();

        $this->artisan('deals:sync')->assertSuccessful();

        Queue::assertPushed(SyncDealsForPostalCode::class, 2);
        Queue::assertPushed(SyncDealsForPostalCode::class, fn (SyncDealsForPostalCode $job): bool => $job->postalCode === '80331');
        $this->assertModelMissing($expired);
    }

    public function test_specific_postal_codes_can_be_synced_and_invalid_ones_are_ignored(): void
    {
        Queue::fake();
        User::factory()->withPostalCode('10115')->create();

        $this->artisan('deals:sync', ['--postal-code' => ['31139', 'abc']])->assertSuccessful();

        Queue::assertPushed(SyncDealsForPostalCode::class, 1);
        Queue::assertPushed(SyncDealsForPostalCode::class, fn (SyncDealsForPostalCode $job): bool => $job->postalCode === '31139');
    }

    public function test_nothing_is_queued_without_postal_codes(): void
    {
        Queue::fake();

        $this->artisan('deals:sync')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_now_option_syncs_in_process_and_reports_failures(): void
    {
        config(['providers.marktguru.base_url' => 'https://marktguru.test/api/v1']);
        Http::fake(['*' => Http::response('Server error', 500)]);

        $this->artisan('deals:sync', ['--postal-code' => ['10115'], '--now' => true])->assertFailed();
    }

    public function test_the_sync_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('deals:sync')
            ->assertSuccessful();
    }
}
