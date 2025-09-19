{{-- LLM Prompt for Receipt Parsing --}}
OCR text (verbatim):
{{ $ocrText }}

Locale hint: "Country: DE (EUR default), language may vary."

Rules:
- Classify into: `category` and `subcategory`. Prefer existing categories list (provided below). If none fits, propose a **new** category and/or subcategory.
- **Category Limit**: Only suggest new categories if absolutely necessary. Try to fit items into existing categories first.
- **Subcategory Limit**: Only suggest new subcategories if they are clearly different from existing ones in the same category.
- Extract: `vendor`, `currency` (ISO 4217), `total_amount`.
- Extract line items: `name`, `quantity` (default 1 if missing), `unit_price`, `total` (unit_price × quantity).
- Numbers in dot decimal; no currency symbols.
- If something is unknown, set `null`.

Known categories to bias (send list):
@forelse($categories as $category)
- **{{ $category['name'] }}**: {{ implode(', ', $category['subcategories']) }}
@empty
- No existing categories found
@endforelse

Category Creation Guidelines:
- **Use existing categories** whenever possible, even if not a perfect match
- **Only create new categories** for items that clearly don't fit any existing category
- **Only create new subcategories** for items that are distinctly different from existing subcategories
- **Examples of when to create new**: "Pet Supplies" if no pet-related category exists, "Office Supplies" if no office category exists
- **Examples of when NOT to create**: "Organic Groceries" (use "Groceries"), "Premium Electronics" (use "Electronics")

Return strict JSON only:
{
  "category": "Groceries",
  "subcategory": "Dairy",
  "vendor": "REWE",
  "currency": "EUR",
  "total_amount": 23.45,
  "items": [
    {"name": "Milk 1L", "quantity": 2, "unit_price": 1.19, "total": 2.38},
    {"name": "Butter 250g", "quantity": 1, "unit_price": 2.29, "total": 2.29}
  ],
  "notes": null
}