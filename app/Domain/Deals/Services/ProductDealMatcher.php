<?php

namespace App\Domain\Deals\Services;

use App\Domain\Products\Data\PurchasedProduct;
use App\Models\Category;
use App\Models\Deal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use WeakMap;

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
     * Words an offer may add without becoming a different thing.
     */
    private const DESCRIPTIVE_WORDS = [
        'deutsche', 'deutscher', 'deutsches', 'haltbare', 'haltbar', 'gemahlen',
        'lose', 'versch', 'verschiedene', 'sorte', 'sorten', 'oder', 'aktion',
    ];

    private const ORGANIC_WORDS = ['bio', 'organic'];

    /**
     * @var array<int, int>|null category id => top-level category id
     */
    private ?array $topLevelCategoryIds = null;

    /**
     * Keyed by the model itself: reading its id for every product and offer is too slow.
     *
     * @var WeakMap<Deal, array<int, string>>|null the words of each offer's title and brand
     */
    private ?WeakMap $offerWords = null;

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
     * The stricter match, for when money is claimed: every meaningful word of
     * one of the names must be a whole word of the offer, so "Eis" matches
     * neither "Reis" nor "Fleisch" and "Milch" does not match "Buttermilch".
     * Beyond those and its brand the offer may only add descriptive words, so
     * "Tomaten" does not match "Tomaten Ketchup"; organic only matches organic.
     *
     * @param  array<int, string>  $names
     * @param  Collection<int, Deal>  $deals
     * @return Collection<int, Deal>
     */
    public function matchingWholeWords(array $names, ?int $categoryId, Collection $deals): Collection
    {
        $wanted = array_filter(array_map(fn (string $name): array => $this->meaningfulWords($name), $names));

        if ($wanted === []) {
            return collect();
        }

        $this->offerWords ??= new WeakMap;
        $organic = $this->isOrganic(explode(' ', $this->words(implode(' ', $names))));

        return $deals->filter(function (Deal $deal) use ($wanted, $categoryId, $organic): bool {
            $offered = $this->offerWords[$deal] ??= explode(' ', $this->words($deal->title.' '.$deal->brand));

            if ($organic && ! $this->isOrganic($offered)) {
                return false;
            }

            foreach ($wanted as $words) {
                if (array_diff($words, $offered) === [] && $this->addsOnlyDescription($words, $deal)) {
                    return $this->sameTopLevelCategory($categoryId, $deal->category_id);
                }
            }

            return false;
        })->values();
    }

    /**
     * @param  array<int, string>  $words  of the product
     */
    private function addsOnlyDescription(array $words, Deal $deal): bool
    {
        return array_diff(
            $this->meaningfulWords($deal->title),
            $words,
            $this->meaningfulWords((string) $deal->brand),
            self::DESCRIPTIVE_WORDS,
        ) === [];
    }

    /**
     * @param  array<int, string>  $words
     */
    private function isOrganic(array $words): bool
    {
        return array_intersect($words, self::ORGANIC_WORDS) !== [];
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
