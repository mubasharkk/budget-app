<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * @var class-string<Receipt>
     */
    protected $model = Receipt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_filename' => fake()->word().'.png',
            'file_type' => 'png',
            'mime' => 'image/png',
            'file_size' => fake()->numberBetween(10_000, 5_000_000),
            'vendor' => fake()->company(),
            'currency' => 'EUR',
            'total_amount' => fake()->randomFloat(2, 1, 500),
            'receipt_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'receipt_timezone' => 'Europe/Berlin',
            'status' => 'processed',
        ];
    }

    /**
     * Indicate the receipt is still pending processing.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'pending',
            'vendor' => null,
            'total_amount' => null,
            'receipt_date' => null,
        ]);
    }

    /**
     * Indicate the receipt failed processing.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'failed',
            'error_message' => 'Processing failed',
        ]);
    }
}
