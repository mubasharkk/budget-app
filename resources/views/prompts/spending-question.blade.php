{{-- LLM Prompt for parsing natural-language spending questions --}}
Today is {{ $today }}.

Known categories (use exact names when matching):
@forelse($categories as $category)
- {{ $category }}
@empty
- (none yet)
@endforelse

@if(!empty($history))
Earlier in this conversation (oldest first) — use it to resolve follow-ups like "and eggs?" or "what about last year?":
@foreach($history as $turn)
- {{ $turn['role'] }}: {{ $turn['content'] }}
@endforeach
When the new question is a follow-up, reuse the previous date range and intent unless the user gives new ones.
@endif

Parse the user's question into a safe, structured query. Only use these intents:
- `total_spend` — total spending in a date range (fixed + variable)
- `category_spend` — spending in one known category above (requires exact `category`)
- `vendor_spend` — spending at one vendor (requires `vendor`)
- `budget_status` — current monthly budget progress (no date range needed)
- `top_items` — most purchased items overall (`metric`: "spend" or "quantity")
- `item_search` — how much or how many of a specific product was bought (requires `item`; `metric`: "spend" or "quantity"). Use for "how many eggs did I buy", "how much did I spend on coffee", "how often did I buy bread".
- `category_search` — find spending categories matching a keyword when the user is exploring rather than naming an exact category (requires `category` as the search keyword).

Guidance:
- Prefer `item_search` when the user names a specific product (eggs, milk, coffee). Set `metric` to "quantity" for "how many/how much of X", "spend" for money questions.
- Prefer `category_spend` when the user names an exact category from the list above; use `category_search` only for fuzzy keyword exploration.

Date rules:
- Resolve relative phrases ("last month", "this week", "in June") to concrete `start_date` and `end_date` (YYYY-MM-DD).
- For `budget_status`, set both dates to null.

User question: "{{ $question }}"

Return strict JSON:
{
  "intent": "item_search",
  "category": null,
  "vendor": null,
  "item": "eggs",
  "start_date": "2026-06-01",
  "end_date": "2026-06-30",
  "metric": "quantity"
}
