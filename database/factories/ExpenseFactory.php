<?php

namespace Database\Factories;

use App\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 5, 500),
            'currency' => 'EUR',
            'spent_on' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'description' => fake()->randomElement(['Client lunch', 'Office supplies', 'Repair', 'Gift', 'Parking']),
            'expense_type' => fake()->randomElement(ExpenseType::cases()),
            'notes' => null,
        ];
    }
}
