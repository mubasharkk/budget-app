<?php

namespace Tests\Feature;

use App\Domain\Shared\Services\LlmService;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Testing\ClientFake;
use RuntimeException;
use Tests\TestCase;

class LlmServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClientFake $client;

    private function service(string|\Throwable ...$replies): LlmService
    {
        $this->client = new ClientFake(array_map(
            fn (string|\Throwable $reply) => is_string($reply)
                ? CreateResponse::fake(['choices' => [['message' => ['content' => $reply]]]])
                : $reply,
            $replies,
        ));

        return new LlmService($this->client);
    }

    private function receiptFile(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'receipt').'.'.$extension;
        file_put_contents($path, 'fake-bytes');

        return $path;
    }

    /**
     * The text prompt and file part of the single sent chat request.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function sentReceiptParts(): array
    {
        $parts = null;
        $this->client->assertSent(Chat::class, function (string $method, array $parameters) use (&$parts): bool {
            $parts = $parameters['messages'][1]['content'];

            return $method === 'create' && $parameters['response_format'] === ['type' => 'json_object'];
        });

        return [$parts[0]['text'], $parts[1]];
    }

    public function test_image_receipt_is_sent_as_data_uri_and_json_is_decoded(): void
    {
        $dairy = Category::factory()->create(['name' => 'Groceries', 'parent_id' => null]);
        Category::factory()->create(['name' => 'Dairy', 'parent_id' => $dairy->id]);

        $result = $this->service('{"is_receipt": true, "vendor": "REWE", "total_amount": 9.5}')
            ->parseReceiptFromFile($this->receiptFile('png'), 'image/png', preferredCurrency: 'USD');

        $this->assertTrue($result['success']);
        $this->assertSame(['is_receipt' => true, 'vendor' => 'REWE', 'total_amount' => 9.5], $result['data']);

        [$prompt, $file] = $this->sentReceiptParts();
        $this->assertSame('image_url', $file['type']);
        $this->assertSame('data:image/png;base64,'.base64_encode('fake-bytes'), $file['image_url']['url']);
        $this->assertStringContainsString('preferred currency is **USD**', $prompt);
        $this->assertStringContainsString('**Groceries**: Dairy', $prompt);
        $this->assertStringNotContainsString('INCOME DOCUMENT', $prompt);
    }

    public function test_pdf_income_receipt_uses_file_part_income_hint_and_fallback_currency(): void
    {
        $path = $this->receiptFile('pdf');

        $this->service('{"is_receipt": true}')->parseReceiptFromFile($path, 'application/pdf', isIncome: true);

        [$prompt, $file] = $this->sentReceiptParts();
        $this->assertSame('file', $file['type']);
        $this->assertSame(basename($path), $file['file']['filename']);
        $this->assertStringStartsWith('data:application/pdf;base64,', $file['file']['file_data']);
        $this->assertStringContainsString('INCOME DOCUMENT', $prompt);
        $this->assertStringContainsString('preferred currency is **EUR**', $prompt);
    }

    public function test_invalid_json_is_reported_as_failure(): void
    {
        $result = $this->service('not json')->parseReceiptFromFile($this->receiptFile('png'), 'image/png');

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid JSON response from LLM', $result['error']);
    }

    public function test_api_errors_are_caught_for_every_call(): void
    {
        $error = new RuntimeException('rate limited');
        $service = $this->service($error, $error, $error, $error, $error);

        $results = [
            $service->parseReceiptFromFile($this->receiptFile('png'), 'image/png'),
            $service->matchLineItemsToProducts([], []),
            $service->summarizeMonthlyDigest($this->digestData()),
            $service->parseSpendingQuestion('How much on food?', ['categories' => [], 'today' => '2026-06-01']),
            $service->formatSpendingAnswer('How much on food?', ['total' => 1]),
        ];

        foreach ($results as $result) {
            $this->assertFalse($result['success']);
            $this->assertSame('rate limited', $result['error']);
        }
    }

    public function test_text_prompts_are_rendered_and_answers_decoded(): void
    {
        $service = $this->service('{"matches": []}', '{"summary": "ok"}', '{"intent": "total"}', '{"answer": "12 $"}');

        $this->assertSame(['matches' => []], $service->matchLineItemsToProducts(
            [['receipt_item_id' => 1, 'name' => 'MILCH', 'unit_price' => 1.0, 'quantity' => 1.0, 'category' => 'Dairy']],
            [['id' => 1, 'name' => 'Milk', 'normalized_name' => 'milk', 'brand' => 'Weihenstephan', 'unit' => 'l', 'size' => null]],
        )['data']);
        $this->assertSame(['summary' => 'ok'], $service->summarizeMonthlyDigest($this->digestData())['data']);
        $this->assertSame(['intent' => 'total'], $service->parseSpendingQuestion('Food?', [
            'categories' => ['Groceries'],
            'today' => '2026-06-01',
            'history' => [['role' => 'user', 'content' => 'Hi']],
        ])['data']);
        $this->assertSame(['answer' => '12 $'], $service->formatSpendingAnswer('Food?', ['total' => 12], '$')['data']);

        $this->client->assertSent(Chat::class, 4);
        $this->client->assertSent(Chat::class, fn (string $method, array $parameters): bool => str_contains(
            $parameters['messages'][1]['content'],
            '"MILCH" (unit_price: 1, quantity: 1, category: Dairy)',
        ));
        $this->client->assertSent(Chat::class, fn (string $method, array $parameters): bool => str_contains($parameters['messages'][1]['content'], 'Food?'));
    }

    /**
     * @return array<string, mixed>
     */
    private function digestData(): array
    {
        return [
            'period' => 'June 2026',
            'currencySymbol' => '€',
            'overview' => ['total' => 120, 'fixed' => 20, 'variable' => 100, 'by_category' => [['category' => 'Groceries', 'total' => 100]]],
            'budget' => ['budgeted' => 200, 'actual' => 120, 'over_count' => 0, 'warning_count' => 1],
            'recommendations' => [['type' => 'saving', 'title' => 'Cook more', 'description' => 'Fewer takeaways']],
            'anomalies' => [['title' => 'Spike', 'description' => 'Groceries up 40%']],
            'renewals' => [['name' => 'Netflix', 'amount' => 12.99, 'billing_cycle' => 'monthly', 'next_billing_date' => '2026-07-01']],
        ];
    }
}
