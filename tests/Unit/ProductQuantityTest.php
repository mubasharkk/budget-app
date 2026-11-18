<?php

namespace Tests\Unit;

use App\Domain\Products\Support\ProductQuantity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductQuantityTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: float, 3: ?array{amount: float, unit: string}}>
     */
    public static function amounts(): array
    {
        return [
            'litres from size' => ['l', '1', 6, ['amount' => 6.0, 'unit' => 'l']],
            'comma decimal size' => ['l', '1,5', 2, ['amount' => 3.0, 'unit' => 'l']],
            'grams in the size' => ['g', '500g', 2, ['amount' => 1.0, 'unit' => 'kg']],
            'size unit wins over product unit' => ['pcs', '250 ml', 4, ['amount' => 1.0, 'unit' => 'l']],
            'multipack' => ['l', '6x0.5l', 1, ['amount' => 3.0, 'unit' => 'l']],
            'egg pack' => ['pcs', '10', 3, ['amount' => 30.0, 'unit' => 'pcs']],
            'weighed item' => ['kg', null, 1.234, ['amount' => 1.234, 'unit' => 'kg']],
            'counted pieces' => ['Stück', null, 3, ['amount' => 3.0, 'unit' => 'pcs']],
            'litres of unknown size' => ['l', null, 2, null],
            'no unit' => [null, null, 2, null],
            'unknown unit' => ['box', '1', 2, null],
        ];
    }

    #[DataProvider('amounts')]
    public function test_it_converts_what_was_bought_into_a_base_unit(?string $unit, ?string $size, float $quantity, ?array $expected): void
    {
        $this->assertEquals($expected, ProductQuantity::total($unit, $size, $quantity));
    }
}
