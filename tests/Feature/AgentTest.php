<?php

namespace Tests\Feature;

use App\Jobs\GenerateMonthlyDigest;
use App\Models\AgentMessage;
use App\Models\Digest;
use App\Models\User;
use App\Services\LlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_page_is_accessible(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('agent'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Agent'));
    }

    public function test_dashboard_agent_endpoint_returns_digest_and_insights(): void
    {
        $user = User::factory()->create();
        Digest::factory()->for($user)->create();

        $this->actingAs($user)
            ->getJson('/dashboard/agent')
            ->assertOk()
            ->assertJsonStructure([
                'digest',
                'recommendations',
                'anomalies',
                'renewals',
            ]);
    }

    public function test_ask_endpoint_returns_llm_formatted_answer(): void
    {
        $user = User::factory()->create();

        $this->mock(LlmService::class, function ($mock): void {
            $mock->shouldReceive('parseSpendingQuestion')
                ->once()
                ->andReturn([
                    'success' => true,
                    'data' => [
                        'intent' => 'budget_status',
                        'category' => null,
                        'vendor' => null,
                        'start_date' => null,
                        'end_date' => null,
                    ],
                ]);
            $mock->shouldReceive('formatSpendingAnswer')
                ->once()
                ->andReturn([
                    'success' => true,
                    'data' => ['answer' => 'You are on track with your budgets this month.'],
                ]);
        });

        $this->actingAs($user)
            ->postJson(route('agent.ask'), [
                'question' => 'How am I doing against my budgets?',
            ])
            ->assertOk()
            ->assertJsonPath('answer', 'You are on track with your budgets this month.');

        // The exchange is persisted so the conversation is preserved.
        $this->assertDatabaseHas('agent_messages', [
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'How am I doing against my budgets?',
        ]);
        $this->assertDatabaseHas('agent_messages', [
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => 'You are on track with your budgets this month.',
        ]);
    }

    public function test_history_returns_only_the_users_messages_oldest_first(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        AgentMessage::factory()->for($user)->create(['content' => 'first']);
        AgentMessage::factory()->for($user)->assistant()->create(['content' => 'second']);
        AgentMessage::factory()->for($other)->create(['content' => 'not mine']);

        $this->actingAs($user)
            ->getJson(route('agent.history'))
            ->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.content', 'first')
            ->assertJsonPath('messages.1.content', 'second');
    }

    public function test_clear_history_deletes_only_the_users_messages(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        AgentMessage::factory()->for($user)->count(3)->create();
        AgentMessage::factory()->for($other)->create();

        $this->actingAs($user)
            ->deleteJson(route('agent.history.clear'))
            ->assertOk();

        $this->assertSame(0, AgentMessage::where('user_id', $user->id)->count());
        $this->assertSame(1, AgentMessage::where('user_id', $other->id)->count());
    }

    public function test_generate_digest_queues_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('agent.digest'))
            ->assertOk();

        Queue::assertPushed(GenerateMonthlyDigest::class);
    }
}
