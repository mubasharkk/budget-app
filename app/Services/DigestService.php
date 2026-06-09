<?php

namespace App\Services;

use App\Enums\BudgetPeriod;
use App\Models\Digest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;

class DigestService
{
    public function __construct(
        private ExpenseService $expenseService,
        private BudgetService $budgetService,
        private RecommendationService $recommendationService,
        private AnomalyDetectionService $anomalyDetectionService,
        private RenewalReminderService $renewalReminderService,
        private LlmService $llmService,
    ) {}

    /**
     * Generate and persist a monthly digest for a user.
     */
    public function generateForUser(User $user, ?CarbonInterface $month = null, bool $sendEmail = true): Digest
    {
        $month = CarbonImmutable::instance($month ?? CarbonImmutable::today()->subMonth());
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $overview = $this->expenseService->overview($user->id, $start, $end, 'month');
        $budgetSummary = $this->budgetService->summary($user->id, BudgetPeriod::Monthly, $end);
        $recommendations = $this->recommendationService->recommendations(
            $user->id,
            $start->toDateString(),
            $end->toDateString(),
        );
        $anomalies = $this->anomalyDetectionService->detect($user->id, $start, $end);
        $renewals = $this->renewalReminderService->upcoming($user->id, 30)
            ->map(fn (object $row): array => (array) $row)
            ->all();

        $payload = [
            'overview' => $overview,
            'budget' => $budgetSummary,
            'recommendations' => $recommendations,
            'anomalies' => $anomalies,
            'renewals' => $renewals,
        ];

        $llmResult = $this->llmService->summarizeMonthlyDigest([
            'period' => $start->format('F Y'),
            'overview' => $overview,
            'budget' => $budgetSummary,
            'recommendations' => array_slice($recommendations, 0, 5),
            'anomalies' => array_slice($anomalies, 0, 5),
            'renewals' => array_slice($renewals, 0, 5),
        ]);

        $summary = $llmResult['success']
            ? ($llmResult['data']['summary'] ?? 'Monthly digest generated.')
            : 'Monthly digest generated.';

        $digest = Digest::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ],
            [
                'summary' => $summary,
                'payload' => $payload,
            ],
        );

        if ($sendEmail && $user->email) {
            Mail::to($user)->send(new \App\Mail\MonthlyDigestMail($digest));
            $digest->update(['emailed_at' => now()]);
        }

        return $digest->fresh();
    }
}
