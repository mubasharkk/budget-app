<?php

namespace App\Services;

use App\Enums\BudgetPeriod;

class RecommendationService
{
    public function __construct(
        private PriceIntelligenceService $priceIntelligenceService,
        private BudgetService $budgetService,
    ) {}

    /**
     * Prioritized actionable recommendations from savings + budget data.
     *
     * @return array<int, array{priority: int, type: string, title: string, description: string, action: string, metadata: array<string, mixed>}>
     */
    public function recommendations(
        int $userId,
        ?string $startDate = null,
        ?string $endDate = null,
        int $limit = 15,
    ): array {
        $items = [];

        foreach ($this->priceIntelligenceService->savingsOpportunities($userId, $startDate, $endDate, 10) as $row) {
            $items[] = [
                'priority' => 80 + min((float) $row->potential_savings, 20),
                'type' => 'savings',
                'title' => 'Switch vendor for '.$row->product_name,
                'description' => sprintf(
                    'You paid €%s at %s; cheapest seen was €%s%s.',
                    number_format((float) $row->paid_price, 2),
                    $row->vendor,
                    number_format((float) $row->cheapest_price, 2),
                    $row->cheapest_vendor ? ' at '.$row->cheapest_vendor : '',
                ),
                'action' => 'Buy '.$row->product_name.' at '.$row->cheapest_vendor,
                'metadata' => [
                    'product_id' => $row->product_id,
                    'potential_savings' => (float) $row->potential_savings,
                ],
            ];
        }

        $budgetSummary = $this->budgetService->summary($userId, BudgetPeriod::Monthly);

        foreach ($budgetSummary['items'] as $row) {
            if ($row['status'] === 'over') {
                $items[] = [
                    'priority' => 95,
                    'type' => 'budget',
                    'title' => 'Over budget: '.$row['label'],
                    'description' => sprintf(
                        'Spent €%s of €%s budget (%.0f%%). Projected €%s by month end.',
                        number_format($row['actual'], 2),
                        number_format($row['budget_amount'], 2),
                        $row['percent_used'],
                        number_format($row['projected'], 2),
                    ),
                    'action' => 'Review '.$row['label'].' spending',
                    'metadata' => [
                        'budget_id' => $row['budget_id'],
                        'percent_used' => $row['percent_used'],
                    ],
                ];
            } elseif ($row['status'] === 'warning') {
                $items[] = [
                    'priority' => 70,
                    'type' => 'budget',
                    'title' => 'Near budget limit: '.$row['label'],
                    'description' => sprintf(
                        'At %.0f%% of your €%s budget with €%s remaining.',
                        $row['percent_used'],
                        number_format($row['budget_amount'], 2),
                        number_format($row['remaining'], 2),
                    ),
                    'action' => 'Slow down '.$row['label'].' spend',
                    'metadata' => [
                        'budget_id' => $row['budget_id'],
                        'percent_used' => $row['percent_used'],
                    ],
                ];
            } elseif ($row['projected_status'] === 'over') {
                $items[] = [
                    'priority' => 75,
                    'type' => 'budget',
                    'title' => 'Projected overspend: '.$row['label'],
                    'description' => sprintf(
                        'On pace to hit €%s vs €%s budget (%.0f%% projected).',
                        number_format($row['projected'], 2),
                        number_format($row['budget_amount'], 2),
                        $row['projected_percent'],
                    ),
                    'action' => 'Adjust '.$row['label'].' spending this month',
                    'metadata' => [
                        'budget_id' => $row['budget_id'],
                        'projected_percent' => $row['projected_percent'],
                    ],
                ];
            }
        }

        usort($items, fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);

        return array_slice($items, 0, $limit);
    }
}
