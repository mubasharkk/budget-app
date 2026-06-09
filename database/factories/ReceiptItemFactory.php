<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\ReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReceiptItem>
 *
 * Note: ReceiptItem recalculates `total` as quantity * unit_price on save,
 * so the persisted total always reflects those two values.
 */
class ReceiptItemFactory extends Factory
{
    /**
     * @var class-string<\App\Models\ReceiptItem>
     */
    protected $model = ReceiptItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 1, 50);

        return [
            'receipt_id' => Receipt::factory(),
            'name' => fake()->words(2, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
            'category_id' => null,
            'subcategory_id' => null,
        ];
    }
}
