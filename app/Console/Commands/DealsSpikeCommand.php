<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * THROWAWAY spike — validates the marktguru aggregator payload before we commit
 * to a Deals schema (see docs/deals-page-plan.md §7). Delete once the data shape
 * is confirmed; this is not production code.
 *
 * The endpoint is UNOFFICIAL. The X-Apikey / X-Clientkey are marktguru's public
 * web-app keys, defaulted in config('providers.marktguru'); the --apikey /
 * --clientkey / --base options override them for a single run.
 */
class DealsSpikeCommand extends Command
{
    protected $signature = 'deals:spike
        {--zip=10115 : Postal code to fetch offers for}
        {--query= : Product search term, e.g. "eier" (required)}
        {--base= : API base URL (defaults to providers.marktguru.base_url)}
        {--path=/offers/search : Offers search path}
        {--apikey= : X-Apikey header (defaults to providers.marktguru.api_key)}
        {--clientkey= : X-Clientkey header (defaults to providers.marktguru.client_key)}
        {--limit=20 : Number of offers to request}';

    protected $description = 'THROWAWAY: fetch one region of marktguru offers and summarize the payload';

    public function handle(): int
    {
        $searchTerm = trim((string) $this->option('query'));

        if ($searchTerm === '') {
            $this->error('Missing --query. Pass a product search term, e.g. --query=eier.');

            return self::FAILURE;
        }

        $apikey = (string) ($this->option('apikey') ?: config('providers.marktguru.api_key'));
        $clientkey = (string) ($this->option('clientkey') ?: config('providers.marktguru.client_key'));
        $base = (string) ($this->option('base') ?: config('providers.marktguru.base_url'));

        if ($apikey === '' || $clientkey === '') {
            $this->error('Missing marktguru keys. config/providers.php ships defaults; check MARKTGURU_API_KEY / MARKTGURU_CLIENT_KEY are not set to empty values in .env, or pass --apikey / --clientkey.');

            return self::FAILURE;
        }

        $zip = (string) $this->option('zip');
        $url = rtrim($base, '/').$this->option('path');

        $query = [
            'as' => 'web',
            'limit' => (int) $this->option('limit'),
            'offset' => 0,
            'zipCode' => $zip,
            'q' => $searchTerm,
        ];

        $this->line("GET {$url}");
        $this->line('params: '.json_encode($query));
        $this->newLine();

        $response = Http::withHeaders([
            'X-Apikey' => $apikey,
            'X-Clientkey' => $clientkey,
            'User-Agent' => 'budget-app-spike/1.0',
        ])->acceptJson()->timeout(30)->get($url, $query);

        $this->line('HTTP status: '.$response->status());

        if ($response->failed()) {
            $this->error('Request failed. Body (first 500 chars):');
            $this->line(mb_substr($response->body(), 0, 500));
            $this->newLine();
            $this->warn('If this is 401/403, the keys are wrong/expired. If 404, the --path is off. Adjust and retry.');

            return self::FAILURE;
        }

        $json = $response->json();

        $file = $this->resultFilePath($zip, $searchTerm);
        Storage::disk('local')->put($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info('Full payload saved to '.Storage::disk('local')->path($file));
        $this->newLine();

        $this->line('Top-level keys: '.implode(', ', array_keys((array) $json)));

        $results = $this->locateResults($json);

        if (empty($results)) {
            $this->warn('Could not locate an offers array — inspect the saved file and adjust parsing.');

            return self::SUCCESS;
        }

        $this->info('Found '.count($results).' offers. First offer, flattened:');
        $this->newLine();

        foreach ($this->flatten($results[0]) as $key => $value) {
            $this->line(sprintf('  %-42s %s', $key, $this->preview($value)));
        }

        $this->newLine();
        $this->comment('Map these fields against docs/deals-page-plan.md §4 (title, brand, product_name, price, old_price, unit, valid_from/to, category, image, retailer).');

        return self::SUCCESS;
    }

    /**
     * One file per request, e.g. deals-spike/10115-eier-20261030-191800-123.json.
     */
    private function resultFilePath(string $zip, string $searchTerm): string
    {
        $slug = Str::slug($searchTerm) ?: 'query';

        return sprintf('deals-spike/%s-%s-%s.json', Str::slug($zip), $slug, now()->format('Ymd-His-v'));
    }

    /**
     * Find the offers array regardless of the exact envelope key.
     *
     * @return array<int, mixed>|null
     */
    private function locateResults(mixed $json): ?array
    {
        if (! is_array($json)) {
            return null;
        }

        foreach (['results', 'data', 'offers', 'items'] as $key) {
            if (isset($json[$key]) && is_array($json[$key]) && array_is_list($json[$key])) {
                return $json[$key];
            }
        }

        return array_is_list($json) ? $json : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function flatten(mixed $value, string $prefix = ''): array
    {
        if (! is_array($value)) {
            return [$prefix => $value];
        }

        $out = [];

        foreach ($value as $k => $v) {
            $key = $prefix === '' ? (string) $k : "{$prefix}.{$k}";
            $out += $this->flatten($v, $key);
        }

        return $out;
    }

    private function preview(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            is_array($value) => '['.count($value).' items]',
            default => mb_substr((string) $value, 0, 60),
        };
    }
}
