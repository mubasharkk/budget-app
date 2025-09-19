<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        $this->baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');
    }

    /**
     * Parse receipt text and extract structured data
     */
    public function parseReceipt(string $ocrText): array
    {
        try {
            $categories = $this->getExistingCategories();
            
            $prompt = $this->buildPrompt($ocrText, $categories);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
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
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build the prompt for LLM
     */
    private function buildPrompt(string $ocrText, array $categories): string
    {
        $categoriesList = '';
        foreach ($categories as $category) {
            $categoriesList .= "- **{$category['name']}**: " . implode(', ', $category['subcategories']) . "\n";
        }

        return "OCR text (verbatim):\n{$ocrText}\n\nLocale hint: \"Country: DE (EUR default), language may vary.\"\n\nRules:\n- Classify into: `category` and `subcategory`. Prefer existing categories list (provided below). If none fits, propose a **new** category and/or subcategory.\n- Extract: `vendor`, `currency` (ISO 4217), `total_amount`.\n- Extract line items: `name`, `quantity` (default 1 if missing), `unit_price`, `total` (unit_price × quantity).\n- Numbers in dot decimal; no currency symbols.\n- If something is unknown, set `null`.\n\nKnown categories to bias (send list):\n{$categoriesList}\n\nReturn strict JSON only:\n{\n  \"category\": \"Groceries\",\n  \"subcategory\": \"Dairy\",\n  \"vendor\": \"REWE\",\n  \"currency\": \"EUR\",\n  \"total_amount\": 23.45,\n  \"items\": [\n    {\"name\": \"Milk 1L\", \"quantity\": 2, \"unit_price\": 1.19, \"total\": 2.38},\n    {\"name\": \"Butter 250g\", \"quantity\": 1, \"unit_price\": 2.29, \"total\": 2.29}\n  ],\n  \"notes\": null\n}";
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
    private function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';
            
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
                'raw_response' => $data
            ];
        }

        Log::error('LLM API request failed', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return [
            'success' => false,
            'data' => null,
            'error' => 'LLM API request failed with status: ' . $response->status()
        ];
    }
}