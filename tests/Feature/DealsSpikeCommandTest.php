<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DealsSpikeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config([
            'providers.marktguru.api_key' => 'config-api-key',
            'providers.marktguru.client_key' => 'config-client-key',
            'providers.marktguru.base_url' => 'https://marktguru.test/api/v1',
        ]);
    }

    public function test_it_reads_the_keys_and_base_url_from_config(): void
    {
        Http::fake(['marktguru.test/*' => Http::response(['results' => [['id' => 1, 'price' => 0.99]]])]);

        $this->artisan('deals:spike', ['--zip' => '10115', '--query' => 'krombacher'])->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://marktguru.test/api/v1/offers/search')
            && $request['q'] === 'krombacher'
            && $request['zipCode'] === '10115'
            && $request->hasHeader('X-Apikey', 'config-api-key')
            && $request->hasHeader('X-Clientkey', 'config-client-key'));
    }

    public function test_options_override_the_config_values(): void
    {
        Http::fake(['other.test/*' => Http::response(['results' => []])]);

        $this->artisan('deals:spike', [
            '--query' => 'eier',
            '--apikey' => 'cli-api-key',
            '--clientkey' => 'cli-client-key',
            '--base' => 'https://other.test/v2',
        ])->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://other.test/v2/offers/search')
            && $request->hasHeader('X-Apikey', 'cli-api-key')
            && $request->hasHeader('X-Clientkey', 'cli-client-key'));
    }

    public function test_it_fails_without_sending_a_request_when_the_query_is_missing(): void
    {
        Http::fake();

        $this->artisan('deals:spike', ['--zip' => '10115'])->assertFailed();
        $this->artisan('deals:spike', ['--query' => '   '])->assertFailed();

        Http::assertNothingSent();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_each_request_is_saved_to_its_own_file(): void
    {
        Http::fake(['marktguru.test/*' => Http::response(['results' => [['id' => 1]]])]);

        Carbon::setTestNow('2026-10-30 19:18:00.123');
        $this->artisan('deals:spike', ['--zip' => '10115', '--query' => 'Bio Eier'])->assertSuccessful();

        Carbon::setTestNow('2026-10-30 19:18:05.456');
        $this->artisan('deals:spike', ['--zip' => '10115', '--query' => 'Bio Eier'])->assertSuccessful();

        Carbon::setTestNow('2026-10-30 19:18:05.456');
        $this->artisan('deals:spike', ['--zip' => '31139', '--query' => 'krombacher'])->assertSuccessful();

        Carbon::setTestNow();

        Storage::disk('local')->assertExists('deals-spike/10115-bio-eier-20261030-191800-123.json');
        Storage::disk('local')->assertExists('deals-spike/10115-bio-eier-20261030-191805-456.json');
        Storage::disk('local')->assertExists('deals-spike/31139-krombacher-20261030-191805-456.json');
        $this->assertCount(3, Storage::disk('local')->allFiles('deals-spike'));
    }

    public function test_it_fails_without_saving_when_no_keys_are_configured(): void
    {
        config([
            'providers.marktguru.api_key' => null,
            'providers.marktguru.client_key' => null,
        ]);
        Http::fake();

        $this->artisan('deals:spike', ['--query' => 'eier'])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_fails_without_saving_when_the_api_rejects_the_keys(): void
    {
        Http::fake(['marktguru.test/*' => Http::response('Unauthorized', 401)]);

        $this->artisan('deals:spike', ['--query' => 'eier'])->assertFailed();

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_the_shipped_config_provides_default_public_keys(): void
    {
        $this->refreshApplication();

        $this->assertNotEmpty(config('providers.marktguru.api_key'));
        $this->assertNotEmpty(config('providers.marktguru.client_key'));
        $this->assertSame('https://api.marktguru.de/api/v1', config('providers.marktguru.base_url'));
    }
}
