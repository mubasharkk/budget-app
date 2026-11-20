<?php

namespace App\Domain\Tasks\Services;

use App\Enums\BillingCycle;
use App\Enums\TaskLinkType;
use App\Enums\TaskPriority;
use App\Http\Resources\TaskResource;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\Receipt;
use App\Models\Saving;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A user's finance to-do list. Tasks may repeat on a BillingCycle and may be
 * linked to a receipt, contract, budget or savings entry (TaskLinkType).
 */
class TaskService
{
    /**
     * How many finished tasks the list keeps showing.
     */
    public const RECENTLY_DONE_LIMIT = 20;

    /**
     * How many records of each type the link picker offers.
     */
    public const LINK_OPTIONS_LIMIT = 50;

    /**
     * Open tasks grouped by priority (highest first), plus the recently finished ones.
     *
     * @return array{high: array<int, mixed>, normal: array<int, mixed>, low: array<int, mixed>, done: array<int, mixed>}
     */
    public function board(int $userId): array
    {
        $open = $this->sorted($this->query($userId)->open()->get());
        $done = $this->query($userId)->done()->latest('completed_at')->limit(self::RECENTLY_DONE_LIMIT)->get();

        $group = fn (TaskPriority $priority): array => $this->toArray(
            $open->filter(fn (Task $task): bool => $task->priority === $priority)->values(),
        );

        return [
            TaskPriority::High->value => $group(TaskPriority::High),
            TaskPriority::Normal->value => $group(TaskPriority::Normal),
            TaskPriority::Low->value => $group(TaskPriority::Low),
            'done' => $this->toArray($done),
        ];
    }

    /**
     * Open tasks and what is left to pay, in total and past its deadline, and
     * what has already been spent on finished tasks.
     *
     * @return array{open_count: int, open_amount: float, overdue_count: int, overdue_amount: float, done_count: int, done_amount: float}
     */
    public function summary(int $userId): array
    {
        $open = Task::query()->where('user_id', $userId)->open();
        $overdue = (clone $open)->whereDate('due_date', '<', Carbon::today());
        $done = Task::query()->where('user_id', $userId)->done();

        return [
            'open_count' => (clone $open)->count(),
            'open_amount' => round((float) (clone $open)->sum('amount'), 2),
            'overdue_count' => (clone $overdue)->count(),
            'overdue_amount' => round((float) $overdue->sum('amount'), 2),
            'done_count' => (clone $done)->count(),
            'done_amount' => round((float) $done->sum('amount'), 2),
        ];
    }

    /**
     * Overdue tasks and those due in the next $days days, for the dashboard widget.
     *
     * @return array{tasks: array<int, mixed>, overdue_count: int, open_count: int}
     */
    public function dueSoon(int $userId, int $days = 7): array
    {
        $tasks = $this->sorted($this->query($userId)->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', Carbon::today()->addDays($days))
            ->get());

        return [
            'tasks' => $this->toArray($tasks),
            'overdue_count' => $tasks->filter(fn (Task $task): bool => $task->due_date->lt(Carbon::today()))->count(),
            'open_count' => Task::query()->where('user_id', $userId)->open()->count(),
        ];
    }

    /**
     * Tasks linked to one record: open ones first, then the recently finished.
     *
     * @return array<int, mixed>
     */
    public function forLinked(Model $linked): array
    {
        $tasks = $this->query((int) $linked->getAttribute('user_id'))
            ->where('taskable_type', $linked::class)
            ->where('taskable_id', $linked->getKey())
            ->get();

        return $this->toArray($this->sorted($tasks->reject->isDone())->concat(
            $tasks->filter->isDone()->sortByDesc('completed_at')->take(self::RECENTLY_DONE_LIMIT),
        ));
    }

    /**
     * Records the user can link a task to, per TaskLinkType value.
     *
     * @return array<string, array<int, array{value: int, label: string}>>
     */
    public function linkOptions(int $userId): array
    {
        $options = fn (TaskLinkType $type, Collection $records): array => $records
            ->map(fn (Model $record): array => ['value' => $record->getKey(), 'label' => $type->describe($record)])
            ->values()
            ->all();

        return [
            TaskLinkType::Contract->value => $options(TaskLinkType::Contract, Contract::query()->where('user_id', $userId)->orderBy('name')->get(['id', 'name'])),
            TaskLinkType::Budget->value => $options(TaskLinkType::Budget, Budget::query()->where('user_id', $userId)->with('category:id,name')->get()),
            TaskLinkType::Receipt->value => $options(TaskLinkType::Receipt, Receipt::query()->where('user_id', $userId)->latest('receipt_date')->latest('id')->limit(self::LINK_OPTIONS_LIMIT)->get(['id', 'vendor', 'receipt_date'])),
            TaskLinkType::Saving->value => $options(TaskLinkType::Saving, Saving::query()->where('user_id', $userId)->latest('saved_on')->latest('id')->limit(self::LINK_OPTIONS_LIMIT)->get(['id', 'source', 'saved_on'])),
        ];
    }

    /**
     * Choices for the task form.
     *
     * @return array{priorities: array<int, array{value: string, label: string}>, repeatOptions: array<int, array{value: string, label: string}>, linkTypes: array<int, array{value: string, label: string}>}
     */
    public function formOptions(): array
    {
        return [
            'priorities' => TaskPriority::options(),
            'repeatOptions' => array_map(
                fn (BillingCycle $cycle): array => ['value' => $cycle->value, 'label' => $cycle->label()],
                BillingCycle::cases(),
            ),
            'linkTypes' => array_map(
                fn (TaskLinkType $type): array => ['value' => $type->value, 'label' => $type->label()],
                TaskLinkType::cases(),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Task
    {
        return $user->tasks()->create([...$data, 'currency' => $user->default_currency]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data): Task
    {
        $task->update($data);

        return $task;
    }

    /**
     * Tick the task off. A repeating task gets its next occurrence, once, even
     * if it is ticked off, reopened and ticked off again.
     */
    public function complete(Task $task): Task
    {
        return DB::transaction(function () use ($task): Task {
            $task->update(['completed_at' => now()]);

            if ($task->repeats === null || $task->due_date === null) {
                return $task;
            }

            $nextDueDate = $task->repeats->nextDate($task->due_date)->toDateString();
            $alreadyScheduled = Task::query()
                ->where('user_id', $task->user_id)
                ->where('title', $task->title)
                ->where('taskable_type', $task->taskable_type)
                ->where('taskable_id', $task->taskable_id)
                ->whereDate('due_date', $nextDueDate)
                ->exists();

            if (! $alreadyScheduled) {
                $task->replicate(['completed_at'])->fill(['due_date' => $nextDueDate])->save();
            }

            return $task;
        });
    }

    public function reopen(Task $task): Task
    {
        $task->update(['completed_at' => null]);

        return $task;
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    /**
     * @return Builder<Task>
     */
    private function query(int $userId): Builder
    {
        return Task::query()
            ->where('user_id', $userId)
            ->with(['taskable' => fn (MorphTo $morphTo) => $morphTo->morphWith([Budget::class => ['category:id,name']])]);
    }

    /**
     * Higher priority first, then earliest due (undated last), then the
     * largest amount, then oldest.
     *
     * @param  \Illuminate\Support\Collection<int, Task>  $tasks
     * @return \Illuminate\Support\Collection<int, Task>
     */
    private function sorted(\Illuminate\Support\Collection $tasks): \Illuminate\Support\Collection
    {
        return $tasks->sortBy([
            fn (Task $a, Task $b): int => $b->priority->weight() <=> $a->priority->weight(),
            fn (Task $a, Task $b): int => ($a->due_date?->timestamp ?? PHP_INT_MAX) <=> ($b->due_date?->timestamp ?? PHP_INT_MAX),
            fn (Task $a, Task $b): int => (float) $b->amount <=> (float) $a->amount,
            fn (Task $a, Task $b): int => $a->id <=> $b->id,
        ])->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Task>  $tasks
     * @return array<int, mixed>
     */
    private function toArray(\Illuminate\Support\Collection $tasks): array
    {
        return TaskResource::collection($tasks)->resolve();
    }
}
