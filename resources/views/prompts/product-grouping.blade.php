{{-- LLM Prompt for giving existing products a general name (consumable group) --}}
Give each product below its general name, so the user can see how much of each thing they consume regardless of brand or pack size.

General name rules:
- The general name of the item without brand, size, fat level or packaging: "Milk", "Bananas", "Coffee beans", "Toilet paper".
- Always in English, whatever the product language. Use the form people say: "Bread", "Eggs", "Bananas".
- Keep genuinely different things apart: "Oat drink" is not "Milk"; "Ground coffee" is not "Coffee beans".
- **Reuse an existing general name** whenever one fits — never create a near-duplicate like "Fresh milk" when "Milk" exists. Reuse the names you give within this list too.
- `group_unit` is the unit amounts of this general name are counted in: "l", "kg" or "pcs".
- **Every product must appear exactly once**, keyed by `product_id`.
- Return strict JSON only.

Existing general names:
@forelse($groups as $group)
- "{{ $group['name'] }}"{{ $group['unit'] ? ' ('.$group['unit'].')' : '' }}
@empty
- None yet.
@endforelse

Products:
@foreach($products as $product)
- product_id={{ $product['id'] }}: "{{ $product['name'] }}"{{ $product['brand'] ? ', brand: '.$product['brand'] : '' }}{{ $product['unit'] ? ', unit: '.$product['unit'] : '' }}{{ $product['size'] ? ', size: '.$product['size'] : '' }}{{ $product['category'] ? ', category: '.$product['category'] : '' }}
@endforeach

Return strict JSON:
{
  "groups": [
    {"product_id": 1, "group": "Milk", "group_unit": "l"},
    {"product_id": 2, "group": "Bananas", "group_unit": "kg"}
  ]
}
