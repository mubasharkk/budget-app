<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Services\LlmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300; // 5 minutes

    public function __construct(public Receipt $receipt) {}

    /**
     * Read the uploaded receipt with the vision LLM and persist the structured result.
     */
    public function handle(LlmService $llmService): void
    {
        try {
            Log::info('Starting receipt processing', ['receipt_id' => $this->receipt->id]);

            if (! $this->receipt->fileExists()) {
                throw new \Exception('Receipt file not found: '.($this->receipt->stored_path ?: $this->receipt->original_path));
            }

            $path = $this->receipt->stored_path ?: $this->receipt->original_path;
            $filePath = Storage::disk('public')->path($path);

            $result = $llmService->parseReceiptFromFile($filePath, $this->receipt->mime);

            if (! $result['success']) {
                throw new \Exception('Receipt parsing failed: '.($result['error'] ?? 'Unknown error'));
            }

            $data = $result['data'];
            $isReceipt = $data['is_receipt'] ?? true;

            if (! $isReceipt) {
                $this->receipt->update([
                    'ocr_data' => $data,
                    'vendor' => null,
                    'currency' => 'EUR',
                    'total_amount' => 0,
                    'receipt_date' => null,
                    'receipt_timezone' => null,
                    'status' => 'processed',
                ]);

                $this->receipt->items()->delete();

                Log::info('Document identified as non-receipt, marked as processed with zero values', [
                    'receipt_id' => $this->receipt->id,
                    'notes' => $data['notes'] ?? 'Document is not a receipt or invoice',
                ]);

                return;
            }

            $this->receipt->update([
                'ocr_data' => $data,
                'vendor' => $data['vendor'] ?? null,
                'currency' => $data['currency'] ?? 'EUR',
                'total_amount' => $data['total_amount'] ?? null,
                'receipt_date' => $this->parseReceiptDateTime($data['receipt_date'] ?? null, $data['receipt_time'] ?? null),
                'receipt_timezone' => 'Europe/Berlin',
            ]);

            $this->processItems($data['items'] ?? []);

            $this->receipt->update(['status' => 'processed']);

            MatchReceiptItems::dispatch($this->receipt);

            Log::info('Receipt processing completed successfully', [
                'receipt_id' => $this->receipt->id,
                'vendor' => $data['vendor'] ?? 'Not provided',
                'items_count' => count($data['items'] ?? []),
            ]);
        } catch (\Exception $e) {
            Log::error('Receipt processing failed', [
                'receipt_id' => $this->receipt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->receipt->update([
                'status' => 'failed',
                'error_message' => 'Receipt processing failed: '.$e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Find or create a top-level category by name.
     */
    private function findOrCreateCategory(string $categoryName): ?int
    {
        if (empty($categoryName)) {
            return null;
        }

        $category = Category::where('name', $categoryName)
            ->orWhere('slug', Str::slug($categoryName))
            ->whereNull('parent_id')
            ->first();

        if (! $category) {
            $category = Category::create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
                'parent_id' => null,
            ]);
        }

        return $category->id;
    }

    /**
     * Find or create a subcategory under the given parent category.
     */
    private function findOrCreateSubcategory(?string $subcategoryName, ?int $categoryId): ?int
    {
        if (empty($subcategoryName) || ! $categoryId) {
            return null;
        }

        $subcategory = Category::where('name', $subcategoryName)
            ->orWhere('slug', Str::slug($subcategoryName))
            ->where('parent_id', $categoryId)
            ->first();

        if (! $subcategory) {
            $subcategory = Category::create([
                'name' => $subcategoryName,
                'slug' => Str::slug($subcategoryName),
                'parent_id' => $categoryId,
            ]);
        }

        return $subcategory->id;
    }

    /**
     * Replace the receipt's items, finding or creating a category/subcategory per item.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function processItems(array $items): void
    {
        $this->receipt->items()->delete();

        foreach ($items as $itemData) {
            $itemCategory = $itemData['category'] ?? null;
            $itemSubcategory = $itemData['subcategory'] ?? null;

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
     * Combine the parsed date and time into a single datetime.
     */
    private function parseReceiptDateTime(?string $date, ?string $time): ?\DateTime
    {
        if (! $date) {
            return null;
        }

        try {
            $dateTime = \DateTime::createFromFormat('Y-m-d', $date);

            if (! $dateTime) {
                Log::warning('Invalid date format received from LLM', ['date' => $date]);

                return null;
            }

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
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
