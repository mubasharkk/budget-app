<?php

namespace App\Domain\Deals\Services;

use App\Domain\Products\Data\PurchasedProduct;
use App\Models\Category;
use App\Models\Deal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Decides whether an offer is for something the user already buys.
 *
 * A product matches when every meaningful word of its name (receipt name or
 * normalized name) appears in the offer's title or brand — "freil-eier"
 * matches "Bio Freiland Eier". Offers in a different top-level category
 * than the product never match, which filters out "Eierkocher" for eggs.
 */
class ProductDealMatcher
{
    /**
     * Words that say nothing about what the product is.
     */
    private const IGNORED_WORDS = [
        'bio', 'gut', 'und', 'mit', 'the', 'der', 'die', 'das', 'fur', 'fuer', 'von', 'aus',
        'stk', 'stck', 'stuck', 'stueck', 'pck', 'pack', 'packung', 'kg', 'ltr', 'liter', 'gramm',
        'ml', 'cl', 'dose', 'flasche', 'glas', 'beutel', 'becher', 'netz', 'schale', 'frisch', 'frische',
    ];

    private const MIN_WORD_LENGTH = 3;

    /**
     * @var array<int, int>|null category id => top-level category id
     */
    private ?array $topLevelCategoryIds = null;

    /**
     * The offers among $deals that are for the product.
     *
     * @param  Collection<int, Deal>  $deals
     * @return Collection<int, Deal>
     */
    public function matching(PurchasedProduct $product, Collection $deals): Collection
    {
        return $deals->filter(fn (Deal $deal): bool => $this->matches($product, $deal))->values();
    }

    public function matches(PurchasedProduct $product, Deal $deal): bool
    {
        if (! $this->sameTopLevelCategory($product->categoryId, $deal->category_id)) {
            return false;
        }

        $haystack = $this->words($deal->title.' '.$deal->brand);

        foreach ([$product->name, $product->normalizedName] as $name) {
            $words = $this->meaningfulWords($name);

            if ($words !== [] && collect($words)->every(fn (string $word): bool => str_contains($haystack, $word))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function meaningfulWords(string $name): array
    {
        return collect(explode(' ', $this->words($name)))
            ->filter(fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD_LENGTH
                && preg_match('/\d/', $word) !== 1
                && ! in_array($word, self::IGNORED_WORDS, true))
            ->values()
            ->all();
    }

    /**
     * Lower-case ASCII words; punctuation separates words, so "FREIL.EIER" is two.
     */
    private function words(string $text): string
    {
        return Str::slug((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text), ' ');
    }

    private function sameTopLevelCategory(?int $productCategoryId, ?int $dealCategoryId): bool
    {
        if ($productCategoryId === null || $dealCategoryId === null) {
            return true;
        }

        return $this->topLevelOf($productCategoryId) === $this->topLevelOf($dealCategoryId);
    }

    private function topLevelOf(int $categoryId): int
    {
        $this->topLevelCategoryIds ??= Category::query()
            ->get(['id', 'parent_id'])
            ->mapWithKeys(fn (Category $category): array => [$category->id => $category->parent_id ?? $category->id])
            ->all();

        return $this->topLevelCategoryIds[$categoryId] ?? $categoryId;
    }
}
