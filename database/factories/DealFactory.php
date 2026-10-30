<?php

namespace Database\Factories;

use App\Enums\Retailer;
use App\Models\Category;
use App\Models\Deal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    /**
     * @var class-string<Deal>
     */
    protected $model = Deal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $retailer = fake()->randomElement(Retailer::cases());

        return [
            'source' => 'marktguru',
            'external_id' => (string) fake()->unique()->numberBetween(1, 9_999_999),
            'retailer' => $retailer,
            'category_id' => null,
            'advertiser_name' => $retailer->label(),
            'title' => fake()->words(2, true),
            'brand' => fake()->company(),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 0.5, 20),
            'old_price' => null,
            'discount_percent' => null,
            'unit' => 'kg',
            'unit_price' => null,
            'unit_price_unit' => null,
            'valid_from' => now()->subDay()->toDateString(),
            'valid_to' => now()->addDays(5)->toDateString(),
            'raw' => [],
            'fetched_at' => now(),
        ];
    }

    /**
     * List the deal for a postal code.
     */
    public function availableIn(string $postalCode): static
    {
        return $this->afterCreating(fn (Deal $deal) => $deal->postalCodes()->create([
            'postal_code' => $postalCode,
            'fetched_at' => now(),
        ]));
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->subDays(10)->toDateString(),
            'valid_to' => now()->subDays(3)->toDateString(),
        ]);
    }

    public function discounted(float $price, float $oldPrice): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price,
            'old_price' => $oldPrice,
            'discount_percent' => (int) round((1 - $price / $oldPrice) * 100),
        ]);
    }

    public function inCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->addDays(3)->toDateString(),
            'valid_to' => now()->addDays(9)->toDateString(),
        ]);
    }
}
