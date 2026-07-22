<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use OpenAI\Client;
use OpenAI\Factory;

class LlmService
{
    private Client $client;

    private string $model;

    public function __construct()
    {
        $apiKey = config('services.openai.api_key');
        $baseUrl = config('services.openai.base_url');
        $this->model = config('services.openai.model');

        $this->client = (new Factory)
            ->withApiKey($apiKey)
            ->withBaseUri($baseUrl)
            ->make();
    }

    /**
     * Read a receipt file (image or PDF) with a vision model and extract structured data.
     *
     * @return array{success: bool, data: ?array, error?: string, raw_response?: array}
     */
    public function parseReceiptFromFile(string $filePath, string $mime): array
    {
        try {
            $prompt = $this->buildPrompt($this->getExistingCategories());

            $response = $this->client->chat()->create([
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You read receipt/invoice images and PDFs to determine if the document is a receipt/invoice and extract structured purchase data (vendor, currency, total, date, and line items with category & subcategory). Return strict JSON only, always including the is_receipt field.',
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            $this->fileContentPart($filePath, $mime),
                        ],
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens' => 2000,
            ]);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('LLM parsing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build the multimodal content part for the receipt file.
     *
     * Images are sent as image_url data URIs; PDFs use the file/file_data part.
     *
     * @return array<string, mixed>
     */
    private function fileContentPart(string $filePath, string $mime): array
    {
        $base64 = base64_encode(file_get_contents($filePath));
        $dataUri = "data:{$mime};base64,{$base64}";

        if (str_contains($mime, 'pdf')) {
            return [
                'type' => 'file',
                'file' => [
                    'filename' => basename($filePath),
                    'file_data' => $dataUri,
                ],
            ];
        }

        return [
            'type' => 'image_url',
            'image_url' => ['url' => $dataUri],
        ];
    }

    /**
     * Match receipt line items to canonical products using existing catalog context.
     *
     * @param  array<int, array{receipt_item_id: int, name: string, unit_price: float, quantity: float, category: ?string}>  $lineItems
     * @param  array<int, array{id: int, name: string, normalized_name: string, brand: ?string, unit: ?string, size: ?string}>  $existingProducts
     * @return array{success: bool, data: ?array, error?: string, raw_response?: array}
     */
    public function matchLineItemsToProducts(array $lineItems, array $existingProducts): array
    {
        try {
            $prompt = View::make('prompts.product-matching', [
                'lineItems' => $lineItems,
                'products' => $existingProducts,
            ])->render();

            $response = $this->client->chat()->create([
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You match messy receipt line items to a canonical product catalog. Return strict JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens' => 2000,
            ]);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('LLM product matching failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate a narrative monthly digest summary from structured data.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, data: ?array, error?: string, raw_response?: array}
     */
    public function summarizeMonthlyDigest(array $data): array
    {
        try {
            $prompt = View::make('prompts.monthly-digest', $data)->render();

            $response = $this->client->chat()->create([
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You write concise personal finance digests. Return strict JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000,
            ]);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('LLM digest summarization failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Parse a natural-language spending question into a structured query.
     *
     * @param  array{categories: array<int, string>, today: string, history?: array<int, array{role: string, content: string}>}  $context
     * @return array{success: bool, data: ?array, error?: string, raw_response?: array}
     */
    public function parseSpendingQuestion(string $question, array $context): array
    {
        try {
            $prompt = View::make('prompts.spending-question', [
                'question' => $question,
                'categories' => $context['categories'],
                'today' => $context['today'],
                'history' => $context['history'] ?? [],
            ])->render();

            $response = $this->client->chat()->create([
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You parse spending questions into safe structured queries. Return strict JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens' => 500,
            ]);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('LLM spending question parse failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Format structured query results as a natural-language answer.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, data: ?array, error?: string, raw_response?: array}
     */
    public function formatSpendingAnswer(string $question, array $data): array
    {
        try {
            $prompt = View::make('prompts.spending-answer', [
                'question' => $question,
                'data' => $data,
            ])->render();

            $response = $this->client->chat()->create([
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You answer personal finance questions clearly and concisely. Return strict JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.2,
                'max_tokens' => 300,
            ]);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('LLM spending answer format failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the prompt for the LLM using the Blade template.
     *
     * @param  array<int, array{name: string, slug: string, subcategories: array<int, string>}>  $categories
     */
    private function buildPrompt(array $categories): string
    {
        return View::make('prompts.receipt-parsing', [
            'categories' => $categories,
        ])->render();
    }

    /**
     * Get existing categories for the prompt.
     *
     * @return array<int, array{name: string, slug: string, subcategories: array<int, string>}>
     */
    private function getExistingCategories(): array
    {
        $categories = Category::with('subcategories')->whereNull('parent_id')->get();

        return $categories->map(function (Category $category): array {
            return [
                'name' => $category->name,
                'slug' => $category->slug,
                'subcategories' => $category->subcategories->pluck('name')->toArray(),
            ];
        })->toArray();
    }

    /**
     * Handle the LLM API response and decode the JSON payload.
     *
     * @return array{success: bool, data: ?array, error?: string, raw_response?: array}
     */
    private function handleResponse($response): array
    {
        try {
            $content = $response->choices[0]->message->content ?? '';

            $parsedData = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('LLM returned invalid JSON', [
                    'content' => $content,
                    'json_error' => json_last_error_msg(),
                ]);

                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Invalid JSON response from LLM',
                ];
            }

            return [
                'success' => true,
                'data' => $parsedData,
                'raw_response' => $response->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('LLM response handling failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to handle LLM response: '.$e->getMessage(),
            ];
        }
    }
}
