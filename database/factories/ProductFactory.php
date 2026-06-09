<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * @var class-string<\App\Models\Product>
     */
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'name' => Str::title($name),
            'normalized_name' => Str::slug($name),
            'brand' => fake()->optional()->company(),
            'unit' => fake()->randomElement(['l', 'kg', 'pcs', null]),
            'size' => null,
            'attributes' => null,
        ];
    }
}
