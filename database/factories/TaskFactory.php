<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\TaskPriority;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'notes' => null,
            'due_date' => fake()->dateTimeBetween('now', '+3 weeks')->format('Y-m-d'),
            'priority' => TaskPriority::Normal,
        ];
    }

    public function dueOn(string $date): static
    {
        return $this->state(fn (): array => ['due_date' => $date]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['completed_at' => now()]);
    }

    public function repeating(BillingCycle $cycle): static
    {
        return $this->state(fn (): array => ['repeats' => $cycle]);
    }

    public function linkedTo(Model $model): static
    {
        return $this->state(fn (): array => [
            'user_id' => $model->getAttribute('user_id'),
            'taskable_type' => $model::class,
            'taskable_id' => $model->getKey(),
        ]);
    }
}
