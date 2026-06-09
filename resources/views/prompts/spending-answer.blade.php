{{-- LLM Prompt for formatting spending query results as a natural answer --}}
The user asked: "{{ $question }}"

Query results (JSON):
@json($data)

Write a concise, friendly answer in 1-3 sentences. Use € for amounts. Be factual — only use numbers from the results.

Return strict JSON:
{
  "answer": "Your answer here."
}
