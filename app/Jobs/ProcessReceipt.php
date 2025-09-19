<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Services\LlmService;
use App\Services\OcrService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessReceipt implements ShouldQueue
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
            Log::info('Processing receipt', ['receipt_id' => $this->receipt->id]);

            // Step 1: OCR Processing
            $this->performOcr();

            // Step 2: LLM Parsing
            $this->performLlmParsing();

            // Step 3: Update receipt status
            $this->receipt->update(['status' => 'processed']);

            Log::info('Receipt processed successfully', ['receipt_id' => $this->receipt->id]);

        } catch (\Exception $e) {
            Log::error('Receipt processing failed', [
                'receipt_id' => $this->receipt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->receipt->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Perform OCR processing
     */
    private function performOcr(): void
    {
        $ocrService = new OcrService();
        // Use stored_path if available, otherwise fall back to original_path
        $path = $this->receipt->stored_path ?: $this->receipt->original_path;
        $filePath = Storage::disk('public')->path($path);

        if (!file_exists($filePath)) {
            throw new \Exception('Receipt file not found: ' . $filePath);
        }

        // Determine if it's PDF or image
        $isPdf = $this->receipt->mime === 'application/pdf';

        $ocrResult = $isPdf
            ? $ocrService->extractFromPdf($filePath)
            : $ocrService->extractFromImage($filePath);

        if (!$ocrResult['success']) {
            throw new \Exception('OCR processing failed: ' . ($ocrResult['error'] ?? 'Unknown error'));
        }

        // Update receipt with OCR data
        $this->receipt->update([
            'ocr_text' => $ocrResult['text'],
            'ocr_data' => $ocrResult['raw_data'] ?? null
        ]);

        Log::info('OCR completed', [
            'receipt_id' => $this->receipt->id,
            'confidence' => $ocrResult['confidence'],
            'text_length' => strlen($ocrResult['text'])
        ]);
    }

    /**
     * Perform LLM parsing
     */
    private function performLlmParsing(): void
    {
        if (empty($this->receipt->ocr_text)) {
            throw new \Exception('No OCR text available for LLM parsing');
        }

        $llmService = new LlmService();
        $llmResult = $llmService->parseReceipt($this->receipt->ocr_text);

        if (!$llmResult['success']) {
            throw new \Exception('LLM parsing failed: ' . ($llmResult['error'] ?? 'Unknown error'));
        }

        $data = $llmResult['data'];

        // Process categories
        $categoryId = $this->findOrCreateCategory($data['category']);
        $subcategoryId = $this->findOrCreateSubcategory($data['subcategory'], $categoryId);

        // Update receipt with parsed data
        $this->receipt->update([
            'category_id' => $categoryId,
            'subcategory_id' => $subcategoryId,
            'vendor' => $data['vendor'],
            'currency' => $data['currency'],
            'total_amount' => $data['total_amount']
        ]);

        // Process items
        $this->processItems($data['items'] ?? []);

        Log::info('LLM parsing completed', [
            'receipt_id' => $this->receipt->id,
            'category' => $data['category'],
            'subcategory' => $data['subcategory'],
            'vendor' => $data['vendor'],
            'items_count' => count($data['items'] ?? [])
        ]);
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
     * Process receipt items
     */
    private function processItems(array $items): void
    {
        // Delete existing items
        $this->receipt->items()->delete();

        foreach ($items as $itemData) {
            ReceiptItem::create([
                'receipt_id' => $this->receipt->id,
                'name' => $itemData['name'] ?? '',
                'quantity' => $itemData['quantity'] ?? 1,
                'unit_price' => $itemData['unit_price'] ?? 0,
                'total' => $itemData['total'] ?? 0
            ]);
        }
    }
}
