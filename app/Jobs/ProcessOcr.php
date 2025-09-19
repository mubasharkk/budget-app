<?php

namespace App\Jobs;

use App\Models\Receipt;
use App\Services\OcrService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessOcr implements ShouldQueue
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
            Log::info('Starting OCR processing', ['receipt_id' => $this->receipt->id]);

            // Check if file exists
            if (!$this->receipt->fileExists()) {
                throw new \Exception('Receipt file not found: ' . ($this->receipt->stored_path ?: $this->receipt->original_path));
            }

            // Use stored_path if available, otherwise fall back to original_path
            $path = $this->receipt->stored_path ?: $this->receipt->original_path;
            $filePath = Storage::disk('public')->path($path);

            if (!file_exists($filePath)) {
                throw new \Exception('Receipt file not found: ' . $filePath);
            }

            // Determine if it's PDF or image
            $isPdf = $this->receipt->mime === 'application/pdf';

            // Perform OCR
            $ocrService = new OcrService();
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

            Log::info('OCR processing completed successfully', [
                'receipt_id' => $this->receipt->id,
                'confidence' => $ocrResult['confidence'],
                'text_length' => strlen($ocrResult['text'])
            ]);

            // Dispatch LLM processing job
            ProcessLlm::dispatch($this->receipt);

        } catch (\Exception $e) {
            Log::error('OCR processing failed', [
                'receipt_id' => $this->receipt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->receipt->update([
                'status' => 'failed',
                'error_message' => 'OCR processing failed: ' . $e->getMessage()
            ]);

            throw $e;
        }
    }
}