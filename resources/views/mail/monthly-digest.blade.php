<x-mail::message>
# Your {{ $digest->period_start->format('F Y') }} digest

{{ $digest->summary }}

@if (count($recommendations) > 0)
## Top recommendations
@foreach (array_slice($recommendations, 0, 3) as $rec)
- **{{ $rec['title'] }}** — {{ $rec['description'] }}
@endforeach
@endif

@if (count($renewals) > 0)
## Upcoming billing
@foreach (array_slice($renewals, 0, 3) as $renewal)
- {{ $renewal['name'] }} — €{{ number_format($renewal['amount'], 2) }} due {{ $renewal['next_billing_date'] }}
@endforeach
@endif

<x-mail::button :url="url('/agent')">
View in app
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
