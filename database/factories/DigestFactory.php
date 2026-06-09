<?php

namespace Database\Factories;

use App\Models\Digest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Digest>
 */
class DigestFactory extends Factory
{
    /**
     * @var class-string<\App\Models\Digest>
     */
    protected $model = Digest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subMonth()->startOfMonth();

        return [
            'user_id' => User::factory(),
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'summary' => fake()->paragraph(),
            'payload' => [
                'recommendations' => [],
                'anomalies' => [],
                'renewals' => [],
            ],
            'emailed_at' => null,
        ];
    }
}
