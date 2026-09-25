<?php

namespace Tests\Feature;

use App\Domain\Analytics\Services\ExpenseService;
use App\Domain\Assistant\Services\AnomalyDetectionService;
use App\Domain\Assistant\Services\DigestService;
use App\Domain\Assistant\Services\RecommendationService;
use App\Domain\Budgets\Services\BudgetService;
use App\Domain\Contracts\Services\RenewalReminderService;
use App\Domain\Shared\Services\LlmService;
use App\Mail\MonthlyDigestMail;
use App\Models\Digest;
use App\Models\User;
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
            app(ExpenseService::class),
            app(BudgetService::class),
            app(RecommendationService::class),
            app(AnomalyDetectionService::class),
            app(RenewalReminderService::class),
            app(LlmService::class),
        ))->generateForUser($user, CarbonImmutable::parse('2026-06-01'), sendEmail: true);

        $this->assertInstanceOf(Digest::class, $digest);
        $this->assertSame('You spent less on groceries this month.', $digest->summary);
        $this->assertDatabaseHas('digests', ['user_id' => $user->id]);

        Mail::assertSent(MonthlyDigestMail::class);

        CarbonImmutable::setTestNow();
    }
}
