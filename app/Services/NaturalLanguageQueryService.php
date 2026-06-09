<?php

namespace App\Services;

use App\Models\Category;
use Carbon\CarbonImmutable;

class NaturalLanguageQueryService
{
    public function __construct(
        private LlmService $llmService,
        private SpendingQueryExecutor $executor,
    ) {}

    /**
     * Answer a natural-language spending question for a user.
     *
     * @return array{answer: string, data: array<string, mixed>, parsed: array<string, mixed>}
     */
    public function ask(int $userId, string $question): array
    {
        $context = $this->buildContext($userId);
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

        return [
            'answer' => $answerResult['data']['answer'] ?? 'No answer available.',
            'data' => $data,
            'parsed' => $validated,
        ];
    }

    /**
     * @return array{categories: array<int, string>, today: string}
     */
    private function buildContext(int $userId): array
    {
        return [
            'categories' => Category::query()
                ->whereNull('parent_id')
                ->orderBy('name')
                ->pluck('name')
                ->all(),
            'today' => CarbonImmutable::today()->toDateString(),
        ];
    }
}
