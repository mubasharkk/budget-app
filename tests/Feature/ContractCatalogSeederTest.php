<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Provider;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ContractCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ContractCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $backupContents = '';

    protected function tearDown(): void
    {
        if ($this->backupContents !== '') {
            File::put(database_path('data/contracts.json'), $this->backupContents);
        }

        parent::tearDown();
    }

    public function test_seeds_contracts_from_json_catalog(): void
    {
        $this->seed(CategorySeeder::class);

        $user = User::factory()->create();
        Provider::factory()->for($user)->create(['name' => 'GESOBAU AG']);

        $this->writeCatalog([
            [
                'name' => 'Apartment Rent',
                'provider_name' => 'GESOBAU AG',
                'category_slug' => 'rent-housing',
                'amount' => '837.00',
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'billing_day' => 1,
                'start_date' => '2024-01-01',
                'next_billing_date' => '2026-07-01',
                'status' => 'active',
                'auto_renew' => true,
            ],
        ]);

        $this->seed(ContractCatalogSeeder::class);

        $contract = Contract::query()->where('user_id', $user->id)->where('name', 'Apartment Rent')->first();

        $this->assertNotNull($contract);
        $this->assertSame('837.00', $contract->amount);
        $this->assertSame('rent-housing', $contract->category?->slug);
        $this->assertSame('GESOBAU AG', $contract->provider?->name);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CategorySeeder::class);

        User::factory()->create();

        $this->writeCatalog([
            [
                'name' => 'Internet',
                'provider_name' => null,
                'category_slug' => 'internet-phone',
                'amount' => '39.99',
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'start_date' => '2026-01-01',
                'status' => 'active',
                'auto_renew' => true,
            ],
        ]);

        $this->seed(ContractCatalogSeeder::class);
        $this->seed(ContractCatalogSeeder::class);

        $this->assertSame(1, Contract::count());
    }

    public function test_json_catalog_contains_expected_local_contracts(): void
    {
        $catalog = json_decode(File::get(database_path('data/contracts.json')), true);

        $this->assertArrayHasKey('contracts', $catalog);
        $this->assertGreaterThanOrEqual(10, count($catalog['contracts']));

        $names = array_column($catalog['contracts'], 'name');
        $this->assertContains('Apartment Rent', $names);
    }

    /**
     * @param  array<int, array<string, mixed>>  $contracts
     */
    private function writeCatalog(array $contracts): void
    {
        $path = database_path('data/contracts.json');

        if (File::exists($path)) {
            $this->backupContents = File::get($path);
        }

        File::put($path, json_encode(['contracts' => $contracts], JSON_PRETTY_PRINT));
    }
}
