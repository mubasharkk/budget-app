{{-- LLM Prompt for Receipt Parsing --}}
OCR text (verbatim):
{{ $ocrText }}

Locale hint: "Country: DE (EUR default), language may vary."

Rules:
- **ITEM-LEVEL CATEGORIZATION**: Each item in the list must have its own category and subcategory.
- **CATEGORY REQUIRED**: Every item must have a category (required field).
- **SUBCATEGORY PREFERRED**: Each item should have a subcategory when possible (optional but recommended).
- **INDIVIDUAL ANALYSIS**: Analyze each item separately and assign the most accurate category/subcategory.
- **AVOID GENERIC CATEGORIES**: Do not default to "Snacks" or generic categories. Be specific.
- **CATEGORY ACCURACY**: If an item doesn't fit existing categories, suggest a precise new category.
- **SUBCATEGORY PRECISION**: Choose the most specific subcategory that matches the item.
- Extract: `vendor`, `currency` (ISO 4217), `total_amount`.
- Extract line items: `name`, `quantity` (default 1 if missing), `unit_price`, `total` (unit_price × quantity), `category`, `subcategory`.
- **German Price Format**: Process prices in German format (comma as decimal separator, dot as thousands separator).
- **Number Conversion**: Convert German format to float (e.g., "1.234,56" → 1234.56, "15,00" → 15.00).
- **Quantity**: Extract as integer (whole numbers only).
- **Prices**: Extract as float (decimal numbers).
- **Total Amount**: Extract as float in German format and convert to standard float.
- Numbers in dot decimal format in JSON; no currency symbols.
- If something is unknown, set `null`.

Known categories to bias (send list):
@forelse($categories as $category)
- **{{ $category['name'] }}**: {{ implode(', ', $category['subcategories']) }}
@empty
- No existing categories found
@endforelse

Precise Categorization Guidelines:
- **ANALYZE EACH ITEM INDIVIDUALLY**: Look at the item name and determine what it actually is
- **BE SPECIFIC**: Choose the most accurate category and subcategory for each item
- **AVOID DEFAULTING**: Do not use "Snacks" unless the item is actually a snack
- **EXAMPLES OF ACCURATE CATEGORIZATION**:
  * "KELLERBIER" → Category: "Beverages", Subcategory: "Alcoholic Beverages"
  * "MILCH 1L" → Category: "Groceries", Subcategory: "Dairy"
  * "BROT" → Category: "Groceries", Subcategory: "Bakery"
  * "OBST" → Category: "Groceries", Subcategory: "Fruits"
  * "GEMÜSE" → Category: "Groceries", Subcategory: "Vegetables"
  * "FLEISCH" → Category: "Groceries", Subcategory: "Meat"
  * "REINIGUNGSMITTEL" → Category: "Household", Subcategory: "Cleaning Supplies"
  * "BENZIN" → Category: "Transportation", Subcategory: "Fuel"
  * "MEDIKAMENTE" → Category: "Healthcare", Subcategory: "Pharmacy"
- **CREATE NEW CATEGORIES WHEN NEEDED**: If an item doesn't fit existing categories, suggest a precise new category
- **EXAMPLES OF NEW CATEGORIES**: "Automotive", "Electronics", "Clothing", "Books", "Tools", "Garden"

German Number Format Examples:
- **German Format**: "1.234,56 EUR" → **Convert to**: 1234.56
- **German Format**: "15,00" → **Convert to**: 15.00
- **German Format**: "2.500,75" → **Convert to**: 2500.75
- **German Format**: "0,50" → **Convert to**: 0.50
- **Quantity**: "2 Stück" → **Convert to**: 2 (integer)
- **Quantity**: "1,5 kg" → **Convert to**: 1 (integer, round down)
- **Total**: "Gesamtbetrag 12,09" → **Convert to**: 12.09

Return strict JSON only:
{
  "vendor": "REWE",
  "currency": "EUR",
  "total_amount": 14.39,
  "items": [
    {"name": "KELLERBIER", "quantity": 1, "unit_price": 4.50, "total": 4.50, "category": "Beverages", "subcategory": "Alcoholic Beverages"},
    {"name": "PFAND", "quantity": 1, "unit_price": 4.50, "total": 4.50, "category": "Deposits", "subcategory": "Bottle Deposit"}
  ],
  "notes": null
}
