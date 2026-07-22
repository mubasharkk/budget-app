<?php

namespace App\Services;

use App\Models\AgentMessage;
use App\Models\Category;
use Carbon\CarbonImmutable;

class NaturalLanguageQueryService
{
    /**
     * How many recent messages are fed back into the parser for follow-ups.
     */
    private const HISTORY_LIMIT = 6;

    public function __construct(
        private LlmService $llmService,
        private SpendingQueryExecutor $executor,
    ) {}

    /**
     * Answer a natural-language spending question for a user, persisting the
     * exchange so the conversation is preserved and follow-ups have context.
     *
     * @return array{answer: string, data: array<string, mixed>, parsed: array<string, mixed>}
     */
    public function ask(int $userId, string $question): array
    {
        $history = $this->recentHistory($userId);

        AgentMessage::create([
            'user_id' => $userId,
            'role' => 'user',
            'content' => $question,
        ]);

        $context = $this->buildContext($userId, $history);
        $parseResult = $this->llmService->parseSpendingQuestion($question, $context);

        if (! $parseResult['success']) {
            throw new \RuntimeException($parseResult['error'] ?? 'Could not understand the question.');
        }

        $validated = $this->executor->validateParsedQuery($userId, $parseResult['data']);
        $data = $this->executor->execute($userId, $validated);

        $answerResult = $this->llmService->formatSpendingAnswer($question, $data);

        if (! $answerResult['success']) {
            throw new \RuntimeException($answerResult['error'] ?? 'Could not format the answer.');
        }

        $answer = $answerResult['data']['answer'] ?? 'No answer available.';

        AgentMessage::create([
            'user_id' => $userId,
            'role' => 'assistant',
            'content' => $answer,
            'data' => $data,
        ]);

        return [
            'answer' => $answer,
            'data' => $data,
            'parsed' => $validated,
        ];
    }

    /**
     * The most recent conversation turns, oldest first.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function recentHistory(int $userId): array
    {
        return AgentMessage::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn (AgentMessage $message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{categories: array<int, string>, today: string, history: array<int, array{role: string, content: string}>}
     */
    private function buildContext(int $userId, array $history = []): array
    {
        return [
            'categories' => Category::query()
                ->whereNull('parent_id')
                ->orderBy('name')
                ->pluck('name')
                ->all(),
            'today' => CarbonImmutable::today()->toDateString(),
            'history' => $history,
        ];
    }
}
