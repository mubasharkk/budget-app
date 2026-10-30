{{-- LLM Prompt for formatting spending query results as a natural answer --}}
@use('App\Domain\Shared\Support\SupportedCurrencies')
The user asked: "{{ $question }}"

Query results (JSON):
@json($data)

Write a concise, friendly answer in 1-3 sentences. Amounts are in {{ $currency }}; write them in that currency's local pattern, e.g. {{ SupportedCurrencies::format(1234.5, $currency) }}. Be factual — only use numbers from the results.

Notes:
- If an `items` or `categories` list is empty, say you couldn't find anything matching and suggest rephrasing. Do not invent results.
- Report item quantities in the units as stored (e.g. "3 packs of eggs"); never convert packs into individual units.

Return strict JSON:
{
  "answer": "Your answer here."
}
