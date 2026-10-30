<?php

namespace App\Domain\Deals\Services;

use App\Models\Category;

/**
 * Places a supermarket offer in the app's category taxonomy using German
 * keyword rules. Rules are checked in order, so more specific ones
 * ("Milchschokolade" → Snacks) come before broader ones ("Milch" → Dairy).
 * Anything unmatched falls back to the Groceries parent.
 */
class DealCategorizer
{
    public const FALLBACK_SLUG = 'groceries';

    /**
     * @var array<string, string> category slug => case-insensitive regex
     */
    public const RULES = [
        'groceries-household-supplies' => '/waschmittel|spülmittel|weichspüler|toilettenpapier|klopapier|küchenrolle|küchentücher|reiniger|müllbeutel|taschentücher|spülmaschinen|geschirrspül|waschpulver|vollwaschmittel|colorwaschmittel|putz/u',
        'personal-care-cosmetics' => '/shampoo|duschgel|dusche\b|zahnpasta|zahncreme|zahnbürste|deo|lotion|bodylotion|rasier|seife|hautpflege|gesichtscreme|nivea|haarspray|spülung/u',
        'groceries-frozen' => '/tiefkühl|\btk\b|\beis\b|eiscreme|pizza|fischstäbchen|pommes|\bfrost/u',
        'groceries-snacks' => '/schoko|chips|keks|gummi|fruchtgummi|riegel|praline|cracker|nüsse|nuss|erdnüss|popcorn|bonbon|lakritz|waffel|salzstangen|flips|kaugummi/u',
        'groceries-beverages' => '/bier(?!schinken|wurst)|pils|radler|weizen\b|\bwein\b|rotwein|weißwein|rosé|sekt|prosecco|saft|nektar|mineralwasser|wasser\b|cola|limo|schorle|kaffee|espresso|\btee\b|whisky|wodka|vodka|likör|\bgin\b|\brum\b|energy|eistee|smoothie/u',
        'groceries-dairy' => '/milch|käse|gouda|emmentaler|mozzarella|feta|camembert|joghurt|jogurt|quark|butter|sahne|skyr|kefir|buttermilch|schmand|crème fraîche|creme fraiche|margarine|\beier\b|\bei\b|freilandeier/u',
        'groceries-meat-fish' => '/fleisch|hähnchen|hühn|huhn|\brind|schwein|\bhack|wurst|salami|schinken|lachs|fisch|thunfisch|garnelen|filet|steak|bratwurst|\bpute|putenbrust|kassler|gulasch|schnitzel|frikadelle|würstchen|aufschnitt|forelle|hering/u',
        'groceries-bakery' => '/brot|brötchen|toast|croissant|kuchen|baguette|semmel|brezel|laugen|ciabatta|berliner|krapfen|torte|muffin/u',
        'groceries-fruits' => '/äpfel|apfel|banane|orange|mandarine|clementine|trauben|erdbeer|heidelbeer|himbeer|birne|kiwi|zitrone|limette|beeren|mango|ananas|melone|pfirsich|nektarine|pflaume|kirsche|avocado/u',
        'groceries-vegetables' => '/tomate|gurke|kartoffel|zwiebel|paprika|salat|möhre|karotte|brokkoli|blumenkohl|zucchini|pilz|champignon|spinat|kohl|lauch|porree|spargel|aubergine|kürbis|radieschen|sellerie|knoblauch|\bmais\b/u',
    ];

    /**
     * @var array<string, int>|null slug => id, loaded once per instance
     */
    private ?array $categoryIds = null;

    /**
     * @param  array<string, mixed>  $raw  the source payload; its own category names help the match
     */
    public function categoryIdFor(string $title, ?string $brand = null, ?string $description = null, array $raw = []): ?int
    {
        $sourceCategories = collect((array) data_get($raw, 'categories', []))
            ->map(fn ($category): string => is_array($category) ? (string) ($category['name'] ?? '') : (string) $category)
            ->implode(' ');

        $haystack = mb_strtolower(implode(' ', array_filter([$title, $brand, $description, $sourceCategories])));

        foreach (self::RULES as $slug => $pattern) {
            if (preg_match($pattern, $haystack) === 1 && ($id = $this->idFor($slug)) !== null) {
                return $id;
            }
        }

        return $this->idFor(self::FALLBACK_SLUG);
    }

    private function idFor(string $slug): ?int
    {
        $this->categoryIds ??= Category::query()
            ->whereIn('slug', [...array_keys(self::RULES), self::FALLBACK_SLUG])
            ->pluck('id', 'slug')
            ->all();

        return $this->categoryIds[$slug] ?? null;
    }
}
