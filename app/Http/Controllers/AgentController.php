<?php

namespace App\Http\Controllers;

use App\Domain\Assistant\Services\AssistantService;
use App\Domain\Assistant\Services\NaturalLanguageQueryService;
use App\Http\Requests\AgentAskRequest;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function __construct(
        private AssistantService $assistantService,
        private NaturalLanguageQueryService $queryService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Agent');
    }

    /**
     * Agent dashboard data: latest digest, recommendations, anomalies, renewals.
     */
    public function dashboard(Request $request): JsonResponse
    {
        return response()->json($this->assistantService->dashboard($request->user()->id));
    }

    /**
     * Answer a natural-language spending question.
     */
    public function ask(AgentAskRequest $request): JsonResponse
    {
        try {
            return response()->json($this->queryService->ask(
                $request->user()->id,
                $request->validated('question'),
                $request->validated('mentions', []),
            ));
        } catch (Exception $e) {
            return response()->json([
                'answer' => 'Sorry, I could not answer that question. Try rephrasing it.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json(['messages' => $this->assistantService->history($request->user()->id)]);
    }

    public function clearHistory(Request $request): JsonResponse
    {
        $this->assistantService->clearHistory($request->user()->id);

        return response()->json(['messages' => []]);
    }

    /**
     * Entities the chat can @-mention; receipts are matched by the `q` query as the user types.
     */
    public function mentionables(Request $request): JsonResponse
    {
        return response()->json(
            $this->assistantService->mentionables($request->user()->id, (string) $request->query('q', ''))
        );
    }

    public function generateDigest(Request $request): JsonResponse
    {
        $this->assistantService->queueMonthlyDigest($request->user(), $request->input('month'));

        return response()->json(['message' => 'Digest generation queued.']);
    }
}
