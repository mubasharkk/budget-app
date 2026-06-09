<?php

namespace Tests\Feature;

use App\Models\Digest;
use App\Models\User;
use App\Services\DigestService;
use App\Services\LlmService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DigestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_and_stores_monthly_digest(): void
    {
        Mail::fake();
        CarbonImmutable::setTestNow('2026-07-05');

        $user = User::factory()->create();

        $this->mock(LlmService::class, function ($mock): void {
            $mock->shouldReceive('summarizeMonthlyDigest')
                ->once()
                ->andReturn([
                    'success' => true,
                    'data' => [
                        'summary' => 'You spent less on groceries this month.',
                        'highlights' => ['Groceries down 10%'],
                    ],
                ]);
        });

        $digest = (new DigestService(
            app(\App\Services\ExpenseService::class),
            app(\App\Services\BudgetService::class),
            app(\App\Services\RecommendationService::class),
            app(\App\Services\AnomalyDetectionService::class),
            app(\App\Services\RenewalReminderService::class),
            app(LlmService::class),
        ))->generateForUser($user, CarbonImmutable::parse('2026-06-01'), sendEmail: true);

        $this->assertInstanceOf(Digest::class, $digest);
        $this->assertSame('You spent less on groceries this month.', $digest->summary);
        $this->assertDatabaseHas('digests', ['user_id' => $user->id]);

        Mail::assertSent(\App\Mail\MonthlyDigestMail::class);

        CarbonImmutable::setTestNow();
    }
}
