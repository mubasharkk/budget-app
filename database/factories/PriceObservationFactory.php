<?php

namespace Database\Factories;

use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceObservation>
 */
class PriceObservationFactory extends Factory
{
    /**
     * @var class-string<PriceObservation>
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

    /**
     * Seen on a purchase, as the app records it: a line on the product owner's
     * receipt at the observation's vendor, date and price.
     */
    public function bought(): static
    {
        return $this->afterCreating(function (PriceObservation $observation): void {
            $receipt = Receipt::factory()->create([
                'user_id' => $observation->product->user_id,
                'vendor' => $observation->vendor,
                'receipt_date' => $observation->observed_at,
            ]);
            $item = ReceiptItem::factory()->for($receipt)->create([
                'product_id' => $observation->product_id,
                'unit_price' => $observation->unit_price,
            ]);

            $observation->update(['receipt_item_id' => $item->id]);
        });
    }
}
