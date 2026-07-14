<?php

namespace App\Services;

use App\Jobs\ProcessReceipt;
use App\Models\Receipt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $fileSize = $file->getSize() ?? 0;

        $year = now()->year;
        $month = now()->format('m');
        $uuid = Str::uuid();
        $path = "receipts/{$year}/{$month}/";

        if ($this->isPdf($file)) {
            $extension = 'pdf';
            $filename = "{$uuid}.{$extension}";
            $storedPath = $file->storeAs($path, $filename, 'public');
        } else {
            [$storedPath, $mimeType, $fileSize, $extension] = $this->storeImage($file, $path, $uuid, $originalFilename);
        }

        $receipt = Receipt::create([
            'user_id' => $userId,
            'original_filename' => $originalFilename,
            'original_path' => $path,
            'stored_path' => $storedPath,
            'file_type' => $extension,
            'mime' => $mimeType,
            'file_size' => $fileSize,
            'expense_type' => $expenseType,
            'status' => 'pending',
        ]);

        ProcessReceipt::dispatch($receipt);

        return $receipt;
    }

    /**
     * @return array{0: string, 1: string, 2: int, 3: string}
     */
    private function storeImage(UploadedFile $file, string $path, string $uuid, string $originalFilename): array
    {
        $extension = 'png';
        $filename = "{$uuid}.{$extension}";
        $storedPath = $path.$filename;

        try {
            $imageManager = new ImageManager(new Driver);
            $image = $imageManager->read($file->getRealPath());
            $pngData = $image->toPng();
            Storage::disk('public')->put($storedPath, $pngData);

            Log::info('Receipt image converted to PNG', [
                'original_filename' => $originalFilename,
                'original_mime' => $file->getMimeType(),
            ]);

            return [$storedPath, 'image/png', strlen($pngData), $extension];
        } catch (\Exception $e) {
            Log::error('Receipt image conversion failed', [
                'original_filename' => $originalFilename,
                'error' => $e->getMessage(),
            ]);

            $extension = $file->getClientOriginalExtension() ?: 'bin';
            $filename = "{$uuid}.{$extension}";
            $storedPath = $file->storeAs($path, $filename, 'public');

            return [
                $storedPath,
                $file->getMimeType() ?: 'application/octet-stream',
                $file->getSize() ?? 0,
                $extension,
            ];
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
