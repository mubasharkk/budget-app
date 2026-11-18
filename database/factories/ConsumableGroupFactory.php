<?php

namespace Database\Factories;

use App\Models\ConsumableGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsumableGroup>
 */
class ConsumableGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucfirst(fake()->unique()->word()),
            'unit' => fake()->randomElement(['l', 'kg', 'pcs']),
        ];
    }
}
