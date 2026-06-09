<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contract;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContractCatalogSeeder extends Seeder
{
    /**
     * Seed recurring contracts from a static JSON catalog.
     *
     * @see database/data/contracts.json
     */
    public function run(): void
    {
        $user = User::query()->orderBy('id')->first();

        if (! $user) {
            $this->command?->warn('No user found — create a user first, then re-run ContractCatalogSeeder.');

            return;
        }

        $path = database_path('data/contracts.json');

        if (! file_exists($path)) {
            $this->command?->error('contracts.json not found.');

            return;
        }

        $catalog = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $created = 0;
        $skipped = 0;

        foreach ($catalog['contracts'] as $entry) {
            $providerId = isset($entry['provider_name'])
                ? Provider::query()
                    ->where('user_id', $user->id)
                    ->where('name', $entry['provider_name'])
                    ->value('id')
                : null;

            $categoryId = isset($entry['category_slug'])
                ? Category::query()->where('slug', $entry['category_slug'])->value('id')
                : null;

            $match = [
                'user_id' => $user->id,
                'name' => $entry['name'],
                'provider_id' => $providerId,
            ];

            $exists = Contract::query()->where($match)->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            Contract::query()->create([
                'user_id' => $user->id,
                'provider_id' => $providerId,
                'category_id' => $categoryId,
                'name' => $entry['name'],
                'description' => $entry['description'] ?? null,
                'amount' => $entry['amount'],
                'currency' => $entry['currency'] ?? 'EUR',
                'billing_cycle' => $entry['billing_cycle'],
                'billing_day' => $entry['billing_day'] ?? null,
                'start_date' => $entry['start_date'],
                'end_date' => $entry['end_date'] ?? null,
                'next_billing_date' => $entry['next_billing_date'] ?? null,
                'status' => $entry['status'] ?? 'active',
                'auto_renew' => $entry['auto_renew'] ?? true,
                'notes' => $entry['notes'] ?? null,
            ]);

            $created++;
        }

        $this->command?->info("Contracts for {$user->email}: {$created} created, {$skipped} skipped (already exist).");
    }
}
