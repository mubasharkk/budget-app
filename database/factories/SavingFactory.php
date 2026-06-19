<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Saving>
 */
class SavingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 50, 2000),
            'currency' => 'EUR',
            'saved_on' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'source' => fake()->randomElement(['Emergency fund', 'Vacation', 'Investment', 'Rainy day']),
            'notes' => null,
        ];
    }
}
