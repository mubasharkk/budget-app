<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Services\LlmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessLlm implements ShouldQueue
{
    use Queueable;

    public Receipt $receipt;
    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(Receipt $receipt)
    {
        $this->receipt = $receipt;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting LLM processing', ['receipt_id' => $this->receipt->id]);

            if (empty($this->receipt->ocr_text)) {
                throw new \Exception('No OCR text available for LLM parsing');
            }

            // Perform LLM parsing
            $llmService = new LlmService();
            $llmResult = $llmService->parseReceipt($this->receipt->ocr_text);

            if (!$llmResult['success']) {
                throw new \Exception('LLM parsing failed: ' . ($llmResult['error'] ?? 'Unknown error'));
            }

            $data = $llmResult['data'];

            // Update receipt with parsed data (no categories on receipt)
            $this->receipt->update([
                'vendor' => $data['vendor'] ?? null,
                'currency' => $data['currency'] ?? null,
                'total_amount' => $data['total_amount'] ?? null,
                'receipt_date' => $this->parseReceiptDateTime($data['receipt_date'] ?? null, $data['receipt_time'] ?? null),
                'receipt_timezone' => 'Europe/Berlin' // Default to German timezone
            ]);

            // Process items with individual categories
            $this->processItems($data['items'] ?? []);

            // Update receipt status to processed
            $this->receipt->update(['status' => 'processed']);

            Log::info('LLM processing completed successfully', [
                'receipt_id' => $this->receipt->id,
                'vendor' => $data['vendor'] ?? 'Not provided',
                'items_count' => count($data['items'] ?? []),
                'items_with_categories' => count(array_filter($data['items'] ?? [], function($item) {
                    return !empty($item['category']);
                }))
            ]);

        } catch (\Exception $e) {
            Log::error('LLM processing failed', [
                'receipt_id' => $this->receipt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->receipt->update([
                'status' => 'failed',
                'error_message' => 'LLM processing failed: ' . $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Find or create category
     */
    private function findOrCreateCategory(string $categoryName): ?int
    {
        if (empty($categoryName)) {
            return null;
        }

        $category = Category::where('name', $categoryName)
            ->whereNull('parent_id')
            ->first();

        if (!$category) {
            $category = Category::create([
                'name' => $categoryName,
                'parent_id' => null
            ]);
        }

        return $category->id;
    }

    /**
     * Find or create subcategory
     */
    private function findOrCreateSubcategory(?string $subcategoryName, ?int $categoryId): ?int
    {
        if (empty($subcategoryName) || !$categoryId) {
            return null;
        }

        $subcategory = Category::where('name', $subcategoryName)
            ->where('parent_id', $categoryId)
            ->first();

        if (!$subcategory) {
            $subcategory = Category::create([
                'name' => $subcategoryName,
                'parent_id' => $categoryId
            ]);
        }

        return $subcategory->id;
    }

    /**
     * Process receipt items with individual categories
     */
    private function processItems(array $items): void
    {
        // Delete existing items
        $this->receipt->items()->delete();

        foreach ($items as $itemData) {
            // Get category and subcategory for this specific item
            $itemCategory = $itemData['category'] ?? null;
            $itemSubcategory = $itemData['subcategory'] ?? null;

            // Find or create categories for this item
            $categoryId = $itemCategory ? $this->findOrCreateCategory($itemCategory) : null;
            $subcategoryId = $itemSubcategory && $categoryId ? $this->findOrCreateSubcategory($itemSubcategory, $categoryId) : null;

            ReceiptItem::create([
                'receipt_id' => $this->receipt->id,
                'name' => $itemData['name'] ?? '',
                'quantity' => $itemData['quantity'] ?? 1,
                'unit_price' => $itemData['unit_price'] ?? 0,
                'total' => $itemData['total'] ?? 0,
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
            ]);
        }
    }

    /**
     * Parse receipt date and time into a single datetime
     */
    private function parseReceiptDateTime(?string $date, ?string $time): ?\DateTime
    {
        if (!$date) {
            return null;
        }

        try {
            // Parse date (expected format: YYYY-MM-DD)
            $dateTime = \DateTime::createFromFormat('Y-m-d', $date);
            
            if (!$dateTime) {
                Log::warning('Invalid date format received from LLM', ['date' => $date]);
                return null;
            }

            // Add time if provided (expected format: HH:MM:SS or HH:MM)
            if ($time) {
                $timeParts = explode(':', $time);
                if (count($timeParts) >= 2) {
                    $hour = (int) $timeParts[0];
                    $minute = (int) $timeParts[1];
                    $second = isset($timeParts[2]) ? (int) $timeParts[2] : 0;
                    
                    $dateTime->setTime($hour, $minute, $second);
                }
            }

            return $dateTime;
        } catch (\Exception $e) {
            Log::error('Failed to parse receipt datetime', [
                'date' => $date,
                'time' => $time,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
