<?php

namespace Tests\Feature;

use App\Domain\Deals\Services\DealCategorizer;
use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DealCategorizerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function offers(): array
    {
        return [
            'milk' => ['Frische Vollmilch 3,5%', 'groceries-dairy'],
            'eggs' => ['Bio Freiland Eier', 'groceries-dairy'],
            'cheese with umlaut' => ['Bergkäse am Stück', 'groceries-dairy'],
            'milk chocolate is a snack' => ['Alpenmilch Schokolade', 'groceries-snacks'],
            'orange juice is a drink' => ['Orangensaft 1 l', 'groceries-beverages'],
            'coffee' => ['Jacobs Krönung Kaffee', 'groceries-beverages'],
            'compound bread' => ['Vollkornbrot', 'groceries-bakery'],
            'meat is not ice cream' => ['Hackfleisch gemischt', 'groceries-meat-fish'],
            'ice cream' => ['Eis am Stiel', 'groceries-frozen'],
            'rice is not ice cream' => ['Basmati Reis', 'groceries'],
            'apples' => ['Äpfel Elstar', 'groceries-fruits'],
            'salad' => ['Feldsalat', 'groceries-vegetables'],
            'detergent' => ['Persil Waschmittel', 'groceries-household-supplies'],
            'shower gel' => ['Duschgel Sport', 'personal-care-cosmetics'],
            'unknown falls back to groceries' => ['Spaghetti No. 5', 'groceries'],
        ];
    }

    #[DataProvider('offers')]
    public function test_offers_are_placed_in_the_expected_category(string $title, string $expectedSlug): void
    {
        $this->seed(CategorySeeder::class);

        $categoryId = (new DealCategorizer)->categoryIdFor($title);

        $this->assertSame($expectedSlug, Category::find($categoryId)?->slug);
    }

    public function test_the_sources_own_category_names_are_used(): void
    {
        $this->seed(CategorySeeder::class);

        $categoryId = (new DealCategorizer)->categoryIdFor('Angebot der Woche', raw: ['categories' => [['name' => 'Käse & Milchprodukte']]]);

        $this->assertSame('groceries-dairy', Category::find($categoryId)?->slug);
    }

    public function test_without_seeded_categories_nothing_is_assigned(): void
    {
        $this->assertNull((new DealCategorizer)->categoryIdFor('Vollmilch'));
    }
}
