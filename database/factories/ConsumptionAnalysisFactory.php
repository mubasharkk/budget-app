<?php

namespace Database\Factories;

use App\Models\ConsumptionAnalysis;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsumptionAnalysis>
 */
class ConsumptionAnalysisFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'period_start' => '2026-08-01',
            'period_end' => '2026-10-31',
            'summary' => fake()->sentence(),
            'findings' => [['title' => fake()->words(3, true), 'detail' => fake()->sentence()]],
            'tips' => [fake()->sentence()],
            'payload' => [],
        ];
    }
}
