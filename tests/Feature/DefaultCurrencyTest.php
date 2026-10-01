<?php

namespace Tests\Feature;

use App\Domain\Assistant\Services\AnomalyDetectionService;
use App\Domain\Shared\Services\LlmService;
use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DefaultCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_default_to_eur(): void
    {
        $this->assertSame('EUR', User::factory()->create()->fresh()->default_currency);
    }

    public function test_user_can_set_their_default_currency_and_it_is_shared_with_the_frontend(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('incomes.default-currency.update'), ['default_currency' => 'USD'])
            ->assertRedirect(route('incomes.index'));

        $this->assertSame('USD', $user->fresh()->default_currency);

        $this->actingAs($user->fresh())
            ->get(route('incomes.index'))
            ->assertInertia(fn ($page) => $page->where('auth.user.default_currency', 'USD'));
    }

    public function test_unsupported_currency_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('incomes.default-currency.update'), ['default_currency' => 'XYZ'])
            ->assertSessionHasErrors('default_currency');

        $this->assertSame('EUR', $user->fresh()->default_currency);
    }

    public function test_receipt_without_a_detected_currency_falls_back_to_the_users_default(): void
    {
        Queue::fake();
        Storage::fake('local');
        $user = User::factory()->create(['default_currency' => 'GBP']);
        $receipt = Receipt::factory()->for($user)->pending()->create(['mime' => 'image/png']);
        $receipt->addMediaFromString('fake-image-bytes')
            ->usingFileName('test.png')
            ->toMediaCollection(Receipt::RECEIPT_COLLECTION);

        $this->mock(LlmService::class, function ($mock): void {
            $mock->shouldReceive('parseReceiptFromFile')->once()->andReturn([
                'success' => true,
                'data' => ['is_receipt' => true, 'vendor' => 'Tesco', 'total_amount' => 4.2, 'items' => []],
            ]);
        });

        app()->call([new ProcessReceipt($receipt), 'handle']);

        $this->assertSame('GBP', $receipt->fresh()->currency);
    }

    public function test_assistant_text_uses_the_users_currency_symbol(): void
    {
        CarbonImmutable::setTestNow('2026-06-10');
        $user = User::factory()->create(['default_currency' => 'USD']);
        Receipt::factory()->for($user)->count(2)->create([
            'vendor' => 'Netflix',
            'total_amount' => 12.99,
            'receipt_date' => '2026-06-09',
        ]);

        $anomalies = app(AnomalyDetectionService::class)->detect($user->id);
        CarbonImmutable::setTestNow();

        $this->assertNotEmpty($anomalies);
        $this->assertStringContainsString('$12.99', $anomalies[0]['description']);
        $this->assertStringNotContainsString('€', $anomalies[0]['description']);
    }
}
