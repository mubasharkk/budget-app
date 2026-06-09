{{-- LLM Prompt for parsing natural-language spending questions --}}
Today is {{ $today }}.

Known categories (use exact names when matching):
@forelse($categories as $category)
- {{ $category }}
@empty
- (none yet)
@endforelse

Parse the user's question into a safe, structured query. Only use these intents:
- `total_spend` — total spending in a date range (fixed + variable)
- `category_spend` — spending in one category (requires `category`)
- `vendor_spend` — spending at one vendor (requires `vendor`)
- `budget_status` — current monthly budget progress (no date range needed)
- `top_items` — most purchased items (`metric`: "spend" or "quantity")

Date rules:
- Resolve relative phrases ("last month", "this week", "in June") to concrete `start_date` and `end_date` (YYYY-MM-DD).
- For `budget_status`, set both dates to null.

User question: "{{ $question }}"

Return strict JSON:
{
  "intent": "category_spend",
  "category": "Groceries",
  "vendor": null,
  "start_date": "2026-05-01",
  "end_date": "2026-05-31",
  "metric": null
}
