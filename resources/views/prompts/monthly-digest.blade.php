{{-- LLM Prompt for monthly spending digest --}}
@use('App\Domain\Shared\Support\SupportedCurrencies')
You are a personal budgeting assistant. Summarize the user's month in clear, encouraging prose.

Period: {{ $period }}

Spending overview:
- Total: {{ SupportedCurrencies::format($overview['total'], $currency) }} (fixed {{ SupportedCurrencies::format($overview['fixed'], $currency) }}, variable {{ SupportedCurrencies::format($overview['variable'], $currency) }})
@foreach($overview['by_category'] ?? [] as $row)
- {{ $row['category'] }}: {{ SupportedCurrencies::format($row['total'], $currency) }}
@endforeach

Budget status:
- Budgeted: {{ SupportedCurrencies::format($budget['budgeted'], $currency) }}
- Actual: {{ SupportedCurrencies::format($budget['actual'], $currency) }}
- Over budget categories: {{ $budget['over_count'] }}
- Near limit: {{ $budget['warning_count'] }}

Top recommendations:
@forelse($recommendations as $rec)
- [{{ $rec['type'] }}] {{ $rec['title'] }}: {{ $rec['description'] }}
@empty
- None
@endforelse

Anomalies:
@forelse($anomalies as $anomaly)
- {{ $anomaly['title'] }}: {{ $anomaly['description'] }}
@empty
- None detected
@endforelse

Upcoming renewals / billing:
@forelse($renewals as $renewal)
- {{ is_array($renewal) ? $renewal['name'] : $renewal->name }} ({{ SupportedCurrencies::format(is_array($renewal) ? $renewal['amount'] : $renewal->amount, $currency) }}/{{ is_array($renewal) ? $renewal['billing_cycle'] : $renewal->billing_cycle }}) — due {{ is_array($renewal) ? $renewal['next_billing_date'] : $renewal->next_billing_date }}
@empty
- None in the next 30 days
@endforelse

Return strict JSON:
{
  "summary": "2-4 sentence narrative summary of the month",
  "highlights": ["bullet 1", "bullet 2", "bullet 3"]
}
