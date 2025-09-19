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

    public function __construct()
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        $baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');

        $this->client = (new Factory())
            ->withApiKey($apiKey)
            ->withBaseUri($baseUrl)
            ->make();
    }

    /**
     * Parse receipt text and extract structured data
     */
    public function parseReceipt(string $ocrText): array
    {
        try {
            $categories = $this->getExistingCategories();
            $prompt = $this->buildPrompt($ocrText, $categories);

            $response = $this->client->chat()->create([
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You extract structured purchase data (category, subcategory, vendor, currency, total, and line items) from raw OCR text of receipts/invoices. You return strict JSON only.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.1,
                'max_tokens' => 2000
            ]);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('LLM parsing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build the prompt for LLM using Blade template
     */
    private function buildPrompt(string $ocrText, array $categories): string
    {
        return View::make('prompts.receipt-parsing', [
            'ocrText' => $ocrText,
            'categories' => $categories
        ])->render();
    }

    /**
     * Get existing categories for the prompt
     */
    private function getExistingCategories(): array
    {
        $categories = Category::with('subcategories')->whereNull('parent_id')->get();

        return $categories->map(function ($category) {
            return [
                'name' => $category->name,
                'subcategories' => $category->subcategories->pluck('name')->toArray()
            ];
        })->toArray();
    }

    /**
     * Handle LLM API response
     */
    private function handleResponse($response): array
    {
        try {
            $content = $response->choices[0]->message->content ?? '';

            // Try to parse JSON response
            $parsedData = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('LLM returned invalid JSON', [
                    'content' => $content,
                    'json_error' => json_last_error_msg()
                ]);

                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Invalid JSON response from LLM'
                ];
            }

            return [
                'success' => true,
                'data' => $parsedData,
                'raw_response' => $response->toArray()
            ];
        } catch (\Exception $e) {
            Log::error('LLM response handling failed', [
                'error' => $e->getMessage(),
                'response' => $response->toArray() ?? 'Unable to serialize response'
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Failed to handle LLM response: ' . $e->getMessage()
            ];
        }
    }
}
