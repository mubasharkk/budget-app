<?php

namespace Database\Factories;

use App\Enums\IncomeType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Income>
 */
class IncomeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'currency' => 'EUR',
            'received_on' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'source' => fake()->randomElement(['Freelance project', 'Bonus', 'Tax refund', 'Gift']),
            'income_type' => IncomeType::Net,
            'notes' => null,
        ];
    }
}
