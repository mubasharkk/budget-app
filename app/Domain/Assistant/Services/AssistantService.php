<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Contracts\Services\RenewalReminderService;
use App\Jobs\GenerateMonthlyDigest;
use App\Models\AgentMessage;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Digest;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Collection;

class AssistantService
{
    private const RECEIPT_MENTION_LIMIT = 10;

    public function __construct(
        private RecommendationService $recommendationService,
        private AnomalyDetectionService $anomalyDetectionService,
        private RenewalReminderService $renewalReminderService,
    ) {}

    /**
     * Latest digest plus current recommendations, anomalies and upcoming renewals.
     *
     * @return array{digest: ?Digest, recommendations: mixed, anomalies: mixed, renewals: mixed}
     */
    public function dashboard(int $userId): array
    {
        return [
            'digest' => Digest::query()
                ->where('user_id', $userId)
                ->orderByDesc('period_end')
                ->first(),
            'recommendations' => $this->recommendationService->recommendations($userId),
            'anomalies' => $this->anomalyDetectionService->detect($userId),
            'renewals' => $this->renewalReminderService->upcoming($userId),
        ];
    }

    /**
     * The user's preserved chat history, oldest first.
     *
     * @return Collection<int, AgentMessage>
     */
    public function history(int $userId): Collection
    {
        return AgentMessage::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'data', 'created_at']);
    }

    /**
     * Clear the user's chat history — starts a new chat.
     */
    public function clearHistory(int $userId): void
    {
        AgentMessage::query()->where('user_id', $userId)->delete();
    }

    /**
     * Entities the chat can @-mention: categories and contracts are always returned;
     * receipts (potentially many) only when matched by the search term.
     *
     * @return array{categories: Collection<int, array<string, mixed>>, contracts: Collection<int, array<string, mixed>>, receipts: Collection<int, array<string, mixed>>}
     */
    public function mentionables(int $userId, string $term = ''): array
    {
        $term = trim($term);

        return [
            'categories' => $this->categoryMentions(),
            'contracts' => $this->contractMentions($userId),
            'receipts' => $term === '' ? collect() : $this->receiptMentions($userId, $term),
        ];
    }

    public function queueMonthlyDigest(User $user, ?string $month = null): void
    {
        GenerateMonthlyDigest::dispatch($user, $month);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function categoryMentions(): Collection
    {
        return Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->map(fn (Category $category): array => [
                'id' => 'category:'.$category->id,
                'display' => $category->name,
                'type' => 'category',
                'is_parent' => $category->parent_id === null,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function contractMentions(int $userId): Collection
    {
        return Contract::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Contract $contract): array => [
                'id' => 'contract:'.$contract->id,
                'display' => $contract->name,
                'type' => 'contract',
            ])
            ->values();
    }

    /**
     * Receipts whose vendor matches the term, or whose id equals it when numeric.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function receiptMentions(int $userId, string $term): Collection
    {
        return Receipt::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($term): void {
                $query->where('vendor', 'like', '%'.$term.'%');

                if (ctype_digit($term)) {
                    $query->orWhere('id', (int) $term);
                }
            })
            ->orderByDesc('receipt_date')
            ->limit(self::RECEIPT_MENTION_LIMIT)
            ->get(['id', 'vendor', 'receipt_date'])
            ->map(fn (Receipt $receipt): array => [
                'id' => 'receipt:'.$receipt->id,
                'display' => '#'.$receipt->id.' '.($receipt->vendor ?? 'receipt').' '.$receipt->receipt_date?->format('Y-m-d'),
                'type' => 'receipt',
            ])
            ->values();
    }
}
