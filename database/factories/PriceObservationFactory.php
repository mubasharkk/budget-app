<?php

namespace Database\Factories;

use App\Models\PriceObservation;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceObservation>
 */
class PriceObservationFactory extends Factory
{
    /**
     * @var class-string<\App\Models\PriceObservation>
     */
    protected $model = PriceObservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'receipt_item_id' => null,
            'vendor' => fake()->company(),
            'unit_price' => fake()->randomFloat(2, 1, 50),
            'currency' => 'EUR',
            'observed_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
