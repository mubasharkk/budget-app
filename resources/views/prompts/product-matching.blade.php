{{-- LLM Prompt for matching receipt line items to canonical products --}}
You are given receipt line items and an optional list of existing canonical products for this user.
Match each line item to an existing product when it clearly refers to the same real-world product, or propose a new canonical product.

Rules:
- **Normalize names**: Strip vendor-specific codes, abbreviations, and receipt formatting noise. "MILCH 1L BIO" and "Bio Milk 1L" should match the same product.
- **Prefer existing products**: When an existing product is a clear match, return its `product_id` with `action: "existing"`.
- **Create new products sparingly**: Only use `action: "new"` when no existing product is a reasonable match.
- **Canonical naming**: New products should use a clean, human-readable `name` (e.g. "Milk 1L" not "MILCH 1L BIO REWE").
- **normalized_name**: Lowercase slug using hyphens only (e.g. "milk-1l"). Must be unique per user — vary slightly if needed.
- **brand**, **unit** (e.g. l, kg, pcs), and **size** (e.g. "1", "500g") are optional but helpful.
- **Every line item must appear exactly once** in the response, keyed by `receipt_item_id`.
- Return strict JSON only.

Existing products to bias matching:
@forelse($products as $product)
- id={{ $product['id'] }}: "{{ $product['name'] }}" (normalized: {{ $product['normalized_name'] }}@if($product['brand']), brand: {{ $product['brand'] }}@endif@if($product['unit']), unit: {{ $product['unit'] }}@endif@if($product['size']), size: {{ $product['size'] }}@endif)
@empty
- No existing products yet — create new canonical products for each line item.
@endforelse

Line items to match:
@foreach($lineItems as $item)
- receipt_item_id={{ $item['receipt_item_id'] }}: "{{ $item['name'] }}" (unit_price: {{ $item['unit_price'] }}, quantity: {{ $item['quantity'] }}@if($item['category']), category: {{ $item['category'] }}@endif)
@endforeach

Return strict JSON:
{
  "matches": [
    {
      "receipt_item_id": 1,
      "action": "existing",
      "product_id": 5
    },
    {
      "receipt_item_id": 2,
      "action": "new",
      "product": {
        "name": "Milk 1L",
        "normalized_name": "milk-1l",
        "brand": null,
        "unit": "l",
        "size": "1"
      }
    }
  ]
}
