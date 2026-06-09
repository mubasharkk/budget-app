<?php

namespace Database\Factories;

use App\Enums\BudgetPeriod;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * @var class-string<\App\Models\Budget>
     */
    protected $model = Budget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'period' => BudgetPeriod::Monthly,
            'amount' => fake()->randomFloat(2, 100, 1000),
            'currency' => 'EUR',
            'starts_on' => now()->startOfMonth()->toDateString(),
        ];
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'period' => BudgetPeriod::Weekly,
        ]);
    }

    public function overall(): static
    {
        return $this->state(fn (array $attributes): array => [
            'category_id' => null,
        ]);
    }
}
