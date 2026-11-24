<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\TaskPriority;
use App\Models\Contract;
use App\Models\Receipt;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_list_groups_open_tasks_by_priority_then_deadline_then_amount_and_shows_only_the_users_own(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->dueOn('2026-11-15')->create(['title' => 'High later', 'priority' => TaskPriority::High]);
        Task::factory()->for($user)->dueOn('2026-11-10')->create(['title' => 'High overdue', 'priority' => TaskPriority::High]);
        Task::factory()->for($user)->dueOn('2026-11-12')->create(['title' => 'Normal small', 'amount' => 10]);
        Task::factory()->for($user)->dueOn('2026-11-12')->create(['title' => 'Normal big', 'amount' => 500]);
        Task::factory()->for($user)->create(['title' => 'Normal undated', 'due_date' => null, 'amount' => 9999]);
        Task::factory()->for($user)->dueOn('2026-11-01')->create(['title' => 'Low', 'priority' => TaskPriority::Low]);
        Task::factory()->for($user)->dueOn('2026-11-01')->completed()->create(['title' => 'Done', 'priority' => TaskPriority::High]);
        Task::factory()->dueOn('2026-11-12')->create(['title' => 'Someone else', 'priority' => TaskPriority::High]);

        $this->actingAs($user)->get(route('tasks.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tasks/Index')
                ->where('board.high.0.title', 'High overdue')
                ->where('board.high.1.title', 'High later')
                ->has('board.high', 2)
                ->where('board.normal.0.title', 'Normal big')
                ->where('board.normal.1.title', 'Normal small')
                ->where('board.normal.2.title', 'Normal undated')
                ->where('board.low.0.title', 'Low')
                ->where('board.done.0.title', 'Done')
            );
    }

    public function test_the_list_totals_what_is_left_to_pay(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->dueOn('2026-11-01')->create(['amount' => 120.50, 'priority' => TaskPriority::High]);
        Task::factory()->for($user)->dueOn('2026-11-20')->create(['amount' => 30]);
        Task::factory()->for($user)->dueOn('2026-11-05')->completed()->create(['amount' => 999]);
        Task::factory()->for($user)->completed()->create(['amount' => 0.5]);
        Task::factory()->dueOn('2026-11-01')->create(['amount' => 500]);
        Task::factory()->completed()->create(['amount' => 500]);

        $this->actingAs($user)->get(route('tasks.index'))
            ->assertInertia(fn ($page) => $page
                ->where('summary.open_count', 2)
                ->where('summary.open_amount', 150.5)
                ->where('summary.overdue_count', 1)
                ->where('summary.overdue_amount', 120.5)
                ->where('summary.done_count', 2)
                ->where('summary.done_amount', 999.5)
                ->where('board.high.0.priority', 'high')
                ->where('board.high.0.amount', '120.50')
            );
    }

    public function test_a_task_can_be_linked_to_the_users_own_contract_only(): void
    {
        $user = User::factory()->create(['default_currency' => 'USD']);
        $contract = Contract::factory()->for($user)->create(['name' => 'Gym']);
        $othersContract = Contract::factory()->create();

        $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Cancel before 31 Dec',
            'due_date' => '2026-12-15',
            'amount' => 29.90,
            'link_type' => 'contract',
            'link_id' => $contract->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $task = Task::sole();
        $this->assertTrue($task->taskable->is($contract));
        $this->assertSame('USD', $task->currency);
        $this->assertSame(TaskPriority::Normal, $task->priority);

        $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Not mine',
            'link_type' => 'contract',
            'link_id' => $othersContract->id,
        ])->assertSessionHasErrors('link_id');

        $this->actingAs($user)->post(route('tasks.store'), ['title' => 'Repeats', 'repeats' => 'monthly'])
            ->assertSessionHasErrors('due_date');

        $this->assertSame(1, Task::count());
    }

    public function test_the_receipt_choices_show_each_receipts_total_in_the_users_currency(): void
    {
        $user = User::factory()->create(['default_currency' => 'USD']);
        Receipt::factory()->for($user)->create(['vendor' => 'REWE', 'receipt_date' => '2026-11-03', 'total_amount' => 1234.5, 'currency' => 'EUR']);

        $this->actingAs($user)->getJson(route('receipts.search'))
            ->assertJsonPath('receipts.0.label', 'REWE · 03.11.2026 · $1,234.50');
        $this->actingAs($user)->get(route('tasks.index'))
            ->assertInertia(fn ($page) => $page->missing('linkOptions.receipt'));
    }

    public function test_other_users_tasks_cannot_be_changed(): void
    {
        $task = Task::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->put(route('tasks.update', $task), ['title' => 'Mine now'])->assertForbidden();
        $this->actingAs($intruder)->patch(route('tasks.complete', $task))->assertForbidden();
        $this->actingAs($intruder)->delete(route('tasks.destroy', $task))->assertForbidden();

        $this->assertModelExists($task);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_ticking_off_a_repeating_task_schedules_the_next_one_once(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->dueOn('2026-01-31')->repeating(BillingCycle::Monthly)->create(['title' => 'File VAT']);

        $this->actingAs($user)->patch(route('tasks.complete', $task))->assertRedirect();
        $this->actingAs($user)->patch(route('tasks.reopen', $task));
        $this->actingAs($user)->patch(route('tasks.complete', $task));

        $this->assertNotNull($task->fresh()->completed_at);
        $next = Task::query()->open()->sole();
        $this->assertSame('File VAT', $next->title);
        $this->assertSame('2026-02-28', $next->due_date->toDateString());
        $this->assertSame(BillingCycle::Monthly, $next->repeats);
    }

    public function test_deleting_a_linked_record_keeps_its_tasks(): void
    {
        $contract = Contract::factory()->create();
        $task = Task::factory()->linkedTo($contract)->create();

        $contract->delete();

        $this->assertNull($task->fresh()->taskable_type);
        $this->assertNull($task->fresh()->taskable_id);
    }

    public function test_the_contract_page_lists_its_tasks(): void
    {
        $contract = Contract::factory()->create();
        Task::factory()->linkedTo($contract)->create(['title' => 'Ask for a discount']);
        Task::factory()->for($contract->user)->create(['title' => 'Unrelated']);

        $this->actingAs($contract->user)->get(route('contracts.show', $contract))
            ->assertInertia(fn ($page) => $page
                ->has('tasks', 1)
                ->where('tasks.0.title', 'Ask for a discount')
                ->where('tasks.0.link.type', 'contract')
                ->where('tasks.0.link.url', route('contracts.show', $contract))
            );
    }

    public function test_the_dashboard_widget_lists_overdue_tasks_and_those_due_within_a_week(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->dueOn('2026-11-01')->create(['title' => 'Overdue']);
        Task::factory()->for($user)->dueOn('2026-11-19')->create(['title' => 'In a week']);
        Task::factory()->for($user)->dueOn('2026-11-20')->create(['title' => 'Later']);
        Task::factory()->for($user)->dueOn('2026-11-13')->completed()->create();

        $this->actingAs($user)->getJson(route('dashboard.tasks'))
            ->assertOk()
            ->assertJsonPath('tasks.*.title', ['Overdue', 'In a week'])
            ->assertJsonPath('overdue_count', 1)
            ->assertJsonPath('open_count', 3);
    }
}
