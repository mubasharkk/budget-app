{{-- LLM Prompt for monthly spending digest --}}
You are a personal budgeting assistant. Summarize the user's month in clear, encouraging prose.

Period: {{ $period }}

Spending overview:
- Total: €{{ number_format($overview['total'], 2) }} (fixed €{{ number_format($overview['fixed'], 2) }}, variable €{{ number_format($overview['variable'], 2) }})
@foreach($overview['by_category'] ?? [] as $row)
- {{ $row['category'] }}: €{{ number_format($row['total'], 2) }}
@endforeach

Budget status:
- Budgeted: €{{ number_format($budget['budgeted'], 2) }}
- Actual: €{{ number_format($budget['actual'], 2) }}
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
- {{ is_array($renewal) ? $renewal['name'] : $renewal->name }} (€{{ number_format(is_array($renewal) ? $renewal['amount'] : $renewal->amount, 2) }}/{{ is_array($renewal) ? $renewal['billing_cycle'] : $renewal->billing_cycle }}) — due {{ is_array($renewal) ? $renewal['next_billing_date'] : $renewal->next_billing_date }}
@empty
- None in the next 30 days
@endforelse

Return strict JSON:
{
  "summary": "2-4 sentence narrative summary of the month",
  "highlights": ["bullet 1", "bullet 2", "bullet 3"]
}
