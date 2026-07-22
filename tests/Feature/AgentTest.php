<?php

namespace Tests\Feature;

use App\Jobs\GenerateMonthlyDigest;
use App\Models\AgentMessage;
use App\Models\Category;
use App\Models\Digest;
use App\Models\Receipt;
use App\Models\ReceiptItem;
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

    public function test_mentionables_endpoint_returns_categories(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Groceries']);

        $this->actingAs($user)
            ->getJson(route('agent.mentionables'))
            ->assertOk()
            ->assertJsonPath('categories.0.type', 'category')
            ->assertJsonPath('categories.0.display', 'Groceries')
            ->assertJsonPath('categories.0.id', 'category:'.Category::first()->id);
    }

    public function test_ask_rejects_unsupported_mention_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('agent.ask'), [
                'question' => 'How much did I spend?',
                'mentions' => [['type' => 'provider', 'id' => 1]],
            ])
            ->assertStatus(422);
    }

    public function test_ask_receipt_mention_routes_to_lookup_without_calling_the_parser(): void
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user)->create(['vendor' => 'REWE']);
        ReceiptItem::factory()->for($receipt)->create(['name' => 'Milk', 'quantity' => 2, 'unit_price' => 1]);

        $this->mock(LlmService::class, function ($mock): void {
            $mock->shouldReceive('parseSpendingQuestion')->never();
            $mock->shouldReceive('formatSpendingAnswer')->once()->andReturn([
                'success' => true,
                'data' => ['answer' => 'That receipt has 1 item.'],
            ]);
        });

        $this->actingAs($user)
            ->postJson(route('agent.ask'), [
                'question' => 'what did I buy on this receipt',
                'mentions' => [['type' => 'receipt', 'id' => $receipt->id]],
            ])
            ->assertOk()
            ->assertJsonPath('data.intent', 'receipt_lookup')
            ->assertJsonPath('data.receipt.vendor', 'REWE')
            ->assertJsonCount(1, 'data.items');
    }

    public function test_ask_ignores_a_receipt_mention_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $otherReceipt = Receipt::factory()->create(); // belongs to a different user

        $this->mock(LlmService::class, function ($mock): void {
            // The forged mention is dropped, so it falls back to the normal parse path.
            $mock->shouldReceive('parseSpendingQuestion')->once()->andReturn([
                'success' => true,
                'data' => ['intent' => 'budget_status', 'start_date' => null, 'end_date' => null],
            ]);
            $mock->shouldReceive('formatSpendingAnswer')->once()->andReturn([
                'success' => true,
                'data' => ['answer' => 'Budget looks fine.'],
            ]);
        });

        $this->actingAs($user)
            ->postJson(route('agent.ask'), [
                'question' => 'what did I buy on this receipt',
                'mentions' => [['type' => 'receipt', 'id' => $otherReceipt->id]],
            ])
            ->assertOk()
            ->assertJsonPath('data.intent', 'budget_status'); // not receipt_lookup — no leak
    }

    public function test_ask_applies_a_category_mention_and_persists_it(): void
    {
        $user = User::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $electronics = Category::factory()->create(['name' => 'Electronics']);

        $receipt = Receipt::factory()->for($user)->create(['receipt_date' => '2026-06-10']);
        ReceiptItem::factory()->for($receipt)->create([
            'name' => 'Battery', 'quantity' => 2, 'unit_price' => 5, 'category_id' => $groceries->id,
        ]);
        ReceiptItem::factory()->for($receipt)->create([
            'name' => 'Battery', 'quantity' => 9, 'unit_price' => 5, 'category_id' => $electronics->id,
        ]);

        $this->mock(LlmService::class, function ($mock): void {
            $mock->shouldReceive('parseSpendingQuestion')->once()->andReturn([
                'success' => true,
                'data' => [
                    'intent' => 'item_search',
                    'item' => 'battery',
                    'metric' => 'quantity',
                    'start_date' => '2026-06-01',
                    'end_date' => '2026-06-30',
                ],
            ]);
            $mock->shouldReceive('formatSpendingAnswer')->once()->andReturn([
                'success' => true,
                'data' => ['answer' => 'You bought 2 batteries.'],
            ]);
        });

        $this->actingAs($user)
            ->postJson(route('agent.ask'), [
                'question' => 'how many batteries did I buy',
                'mentions' => [['type' => 'category', 'id' => $groceries->id]],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 2); // electronics batteries excluded by the mention

        $this->assertDatabaseHas('agent_messages', [
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'how many batteries did I buy',
        ]);
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
