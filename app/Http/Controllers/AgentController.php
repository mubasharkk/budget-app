<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgentAskRequest;
use App\Jobs\GenerateMonthlyDigest;
use App\Models\AgentMessage;
use App\Models\Digest;
use App\Services\AnomalyDetectionService;
use App\Services\NaturalLanguageQueryService;
use App\Services\RecommendationService;
use App\Services\RenewalReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function __construct(
        private RecommendationService $recommendationService,
        private AnomalyDetectionService $anomalyDetectionService,
        private RenewalReminderService $renewalReminderService,
        private NaturalLanguageQueryService $queryService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Agent');
    }

    /**
     * Agent dashboard data: latest digest, recommendations, anomalies, renewals.
     */
    public function dashboard(Request $request)
    {
        $userId = Auth::id();

        $latestDigest = Digest::query()
            ->where('user_id', $userId)
            ->orderByDesc('period_end')
            ->first();

        return response()->json([
            'digest' => $latestDigest,
            'recommendations' => $this->recommendationService->recommendations($userId),
            'anomalies' => $this->anomalyDetectionService->detect($userId),
            'renewals' => $this->renewalReminderService->upcoming($userId),
        ]);
    }

    /**
     * Answer a natural-language spending question.
     */
    public function ask(AgentAskRequest $request)
    {
        try {
            $result = $this->queryService->ask(Auth::id(), $request->validated('question'));

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'answer' => 'Sorry, I could not answer that question. Try rephrasing it.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * The current user's preserved chat history, oldest first.
     */
    public function history(): JsonResponse
    {
        $messages = AgentMessage::query()
            ->where('user_id', Auth::id())
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'data', 'created_at']);

        return response()->json(['messages' => $messages]);
    }

    /**
     * Clear the current user's chat history — starts a new chat.
     */
    public function clearHistory(): JsonResponse
    {
        AgentMessage::query()->where('user_id', Auth::id())->delete();

        return response()->json(['messages' => []]);
    }

    /**
     * Manually queue a monthly digest for the current user.
     */
    public function generateDigest(Request $request)
    {
        GenerateMonthlyDigest::dispatch(Auth::user(), $request->get('month'));

        return response()->json(['message' => 'Digest generation queued.']);
    }
}
