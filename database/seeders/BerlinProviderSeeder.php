<?php

namespace Database\Seeders;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Seeder;

class BerlinProviderSeeder extends Seeder
{
    /**
     * Seed Berlin living-service providers for a user.
     *
     * Data curated from the Geofabrik Berlin OSM extract (shop/brand POIs)
     * plus Berlin Senate housing companies and public utilities.
     *
     * @see database/data/berlin-providers.json
     * @see https://download.geofabrik.de/europe/germany/berlin.html
     */
    public function run(): void
    {
        $user = $this->resolveUser();

        if (! $user) {
            $this->command?->warn('No user found — create a user first, then re-run BerlinProviderSeeder.');

            return;
        }

        $path = database_path('data/berlin-providers.json');

        if (! file_exists($path)) {
            $this->command?->error('berlin-providers.json not found.');

            return;
        }

        $catalog = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $created = 0;
        $skipped = 0;

        foreach ($catalog['providers'] as $entry) {
            $exists = Provider::query()
                ->where('user_id', $user->id)
                ->where('name', $entry['name'])
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            Provider::query()->create([
                'user_id' => $user->id,
                'name' => $entry['name'],
                'logo' => $entry['logo'] ?? null,
                'website' => $entry['website'] ?? null,
                'notes' => $this->formatNotes($entry),
            ]);

            $created++;
        }

        $this->command?->info("Berlin providers for {$user->email}: {$created} created, {$skipped} skipped (already exist).");
    }

    private function resolveUser(): ?User
    {
        $email = config('services.berlin_providers.seed_email');

        if ($email) {
            return User::query()->where('email', $email)->first();
        }

        return User::query()->orderBy('id')->first();
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function formatNotes(array $entry): string
    {
        $parts = array_filter([
            $entry['notes'] ?? null,
            isset($entry['category']) ? 'Category: '.$entry['category'] : null,
            isset($entry['source']) ? 'Source: '.$entry['source'] : null,
        ]);

        return implode(' | ', $parts);
    }
}
