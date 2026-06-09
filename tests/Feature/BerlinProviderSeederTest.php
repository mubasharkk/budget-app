<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\User;
use Database\Seeders\BerlinProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BerlinProviderSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_berlin_providers_for_user(): void
    {
        $user = User::factory()->create();

        $this->seed(BerlinProviderSeeder::class);

        $providers = Provider::where('user_id', $user->id)->get();

        $this->assertGreaterThanOrEqual(30, $providers->count());
        $this->assertTrue($providers->contains('name', 'degewo AG'));
        $this->assertTrue($providers->contains('name', 'Gewobag'));
        $this->assertTrue($providers->contains('name', 'BVG'));
        $this->assertTrue($providers->contains('name', 'REWE'));

        $degewo = $providers->firstWhere('name', 'degewo AG');
        $this->assertNotNull($degewo->logo);
        $this->assertStringStartsWith('/images/providers/', $degewo->logo);
        $this->assertStringContainsString('degewo.de', $degewo->website);
    }

    public function test_seeder_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->seed(BerlinProviderSeeder::class);
        $countAfterFirst = Provider::where('user_id', $user->id)->count();

        $this->seed(BerlinProviderSeeder::class);
        $countAfterSecond = Provider::where('user_id', $user->id)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_seeder_updates_logo_when_catalog_changes(): void
    {
        $user = User::factory()->create();

        $this->seed(BerlinProviderSeeder::class);

        $degewo = Provider::query()
            ->where('user_id', $user->id)
            ->where('name', 'degewo AG')
            ->firstOrFail();

        $degewo->update(['logo' => 'https://example.com/stale-logo.png']);

        $this->seed(BerlinProviderSeeder::class);

        $degewo->refresh();

        $this->assertStringStartsWith('/images/providers/', $degewo->logo);
        $this->assertStringNotContainsString('example.com', $degewo->logo);
    }
}
