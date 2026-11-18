{{-- LLM Prompt for matching receipt line items to canonical products and general names --}}
You are given receipt line items, the user's existing canonical products and the user's existing general names (consumable groups).
For each line item decide what kind of line it is, match it to an existing product or propose a new one, and give the product's general name.

Line types (`line_type`):
- **product**: something bought to be consumed or used (food, drinks, toiletries, household goods, services…).
- **deposit**: a bottle/can deposit charged (e.g. "PFAND 0,25").
- **deposit_return**: a deposit refunded (e.g. "LEERGUT", negative Pfand).
- **bag**: a shopping or carrier bag.
- **discount**: a discount, coupon or price reduction line (usually negative).
- **fee**: delivery, service or shipping fees.
Only `product` lines get a product. For every other line type return `action: "skip"` and no product.

Product rules:
- **Normalize names**: Strip vendor-specific codes, abbreviations, and receipt formatting noise. "MILCH 1L BIO" and "Bio Milk 1L" should match the same product.
- **Prefer existing products**: When an existing product is a clear match, return its `product_id` with `action: "existing"`.
- **Create new products sparingly**: Only use `action: "new"` when no existing product is a reasonable match.
- **Canonical naming**: New products should use a clean, human-readable `name` (e.g. "Milk 1L" not "MILCH 1L BIO REWE").
- **normalized_name**: Lowercase slug using hyphens only (e.g. "milk-1l"). Must be unique per user — vary slightly if needed.
- **brand**, **unit** (l, kg or pcs) and **size** (the pack size in that unit, e.g. "1" for 1 l, "0.5" for 500 g, "10" for a 10-pack) are optional but helpful. Loose weighed items (e.g. "BANANEN 1,234 kg") have unit "kg" and no size.

General name rules (`group`, for every product line, existing or new):
- The general name of the item without brand, size, fat level or packaging: "Milk", "Bananas", "Coffee beans", "Toilet paper".
- Always in English, whatever the receipt language. Use the form people say: "Bread", "Eggs", "Bananas".
- Keep genuinely different things apart: "Oat drink" is not "Milk"; "Ground coffee" is not "Coffee beans".
- **Reuse an existing general name** whenever one fits — never create a near-duplicate like "Fresh milk" when "Milk" exists.
- `group_unit` is the unit amounts of this general name are counted in: "l", "kg" or "pcs".

- **Every line item must appear exactly once** in the response, keyed by `receipt_item_id`.
- Return strict JSON only.

Existing general names:
@forelse($groups as $group)
- "{{ $group['name'] }}"{{ $group['unit'] ? ' ('.$group['unit'].')' : '' }}
@empty
- None yet.
@endforelse

Existing products to bias matching:
@forelse($products as $product)
- id={{ $product['id'] }}: "{{ $product['name'] }}" (normalized: {{ $product['normalized_name'] }}{{ $product['brand'] ? ', brand: '.$product['brand'] : '' }}{{ $product['unit'] ? ', unit: '.$product['unit'] : '' }}{{ $product['size'] ? ', size: '.$product['size'] : '' }}{{ ($product['group'] ?? null) ? ', general name: '.$product['group'] : '' }})
@empty
- No existing products yet — create new canonical products for each line item.
@endforelse

Line items to match:
@foreach($lineItems as $item)
- receipt_item_id={{ $item['receipt_item_id'] }}: "{{ $item['name'] }}" (unit_price: {{ $item['unit_price'] }}, quantity: {{ $item['quantity'] }}{{ $item['category'] ? ', category: '.$item['category'] : '' }})
@endforeach

Return strict JSON:
{
  "matches": [
    {
      "receipt_item_id": 1,
      "line_type": "product",
      "action": "existing",
      "product_id": 5,
      "group": "Milk",
      "group_unit": "l"
    },
    {
      "receipt_item_id": 2,
      "line_type": "product",
      "action": "new",
      "product": {
        "name": "Oat Drink Barista 1L",
        "normalized_name": "oat-drink-barista-1l",
        "brand": "Oatly",
        "unit": "l",
        "size": "1"
      },
      "group": "Oat drink",
      "group_unit": "l"
    },
    {
      "receipt_item_id": 3,
      "line_type": "deposit",
      "action": "skip"
    }
  ]
}
