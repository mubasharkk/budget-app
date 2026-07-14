<?php

namespace App\Services;

use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ReceiptUploadService
{
    private const MAX_FILES = 5;

    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, Receipt>
     */
    public function storeMany(int $userId, array $files, string $expenseType = 'personal'): Collection
    {
        $files = array_slice($files, 0, self::MAX_FILES);

        return collect($files)->map(fn (UploadedFile $file): Receipt => $this->storeOne($userId, $file, $expenseType));
    }

    public function storeOne(int $userId, UploadedFile $file, string $expenseType = 'personal'): Receipt
    {
        $originalFilename = $file->getClientOriginalName() ?: $this->defaultFilename($file);
        $uuid = Str::uuid();

        $receipt = Receipt::create([
            'user_id' => $userId,
            'original_filename' => $originalFilename,
            'expense_type' => $expenseType,
            'status' => 'pending',
        ]);

        if ($this->isPdf($file)) {
            $receipt->addMedia($file)
                ->usingFileName("{$uuid}.pdf")
                ->usingName($originalFilename)
                ->toMediaCollection(Receipt::RECEIPT_COLLECTION);
        } else {
            $this->addImageMedia($receipt, $file, $uuid, $originalFilename);
        }

        $media = $receipt->getFirstMedia(Receipt::RECEIPT_COLLECTION);

        $receipt->update([
            'file_type' => pathinfo((string) $media->file_name, PATHINFO_EXTENSION),
            'mime' => $media->mime_type,
            'file_size' => $media->size,
        ]);

        ProcessReceipt::dispatch($receipt);

        return $receipt;
    }

    /**
     * Convert the uploaded image to PNG and attach it; fall back to the original on failure.
     */
    private function addImageMedia(Receipt $receipt, UploadedFile $file, string $uuid, string $originalFilename): void
    {
        try {
            $imageManager = new ImageManager(new Driver);
            $image = $imageManager->read($file->getRealPath());
            $pngData = (string) $image->toPng();

            $receipt->addMediaFromString($pngData)
                ->usingFileName("{$uuid}.png")
                ->usingName($originalFilename)
                ->toMediaCollection(Receipt::RECEIPT_COLLECTION);

            Log::info('Receipt image converted to PNG', [
                'original_filename' => $originalFilename,
                'original_mime' => $file->getMimeType(),
            ]);
        } catch (\Exception $e) {
            Log::error('Receipt image conversion failed', [
                'original_filename' => $originalFilename,
                'error' => $e->getMessage(),
            ]);

            $extension = $file->getClientOriginalExtension() ?: 'bin';

            $receipt->addMedia($file)
                ->usingFileName("{$uuid}.{$extension}")
                ->usingName($originalFilename)
                ->toMediaCollection(Receipt::RECEIPT_COLLECTION);
        }
    }

    private function isPdf(UploadedFile $file): bool
    {
        $mime = $file->getMimeType();

        return $mime === 'application/pdf'
            || $file->getClientOriginalExtension() === 'pdf';
    }

    private function defaultFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension()
            ?: ($this->isPdf($file) ? 'pdf' : 'jpg');

        return 'receipt-'.now()->format('Y-m-d-His').".{$extension}";
    }
}
