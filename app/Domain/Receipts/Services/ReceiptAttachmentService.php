<?php

namespace App\Domain\Receipts\Services;

use App\Models\Receipt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Supporting documents stored alongside a receipt. They are kept as-is: never
 * sent to the LLM and never part of any spending total.
 */
class ReceiptAttachmentService
{
    public const MAX_ATTACHMENTS = 10;

    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, Media>
     */
    public function attach(Receipt $receipt, array $files): Collection
    {
        return collect($files)->map(function (UploadedFile $file) use ($receipt): Media {
            $originalFilename = $file->getClientOriginalName() ?: 'attachment';
            $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin'));

            return $receipt->addMedia($file)
                ->usingFileName(Str::uuid().'.'.$extension)
                ->usingName($originalFilename)
                ->toMediaCollection(Receipt::ATTACHMENTS_COLLECTION);
        });
    }

    /**
     * How many more documents the receipt can take.
     */
    public function remainingSlots(Receipt $receipt): int
    {
        return max(0, self::MAX_ATTACHMENTS - $receipt->attachments()->count());
    }

    /**
     * The attachment list shown on the receipt page.
     *
     * @return Collection<int, array{id: int, name: string, mime_type: ?string, size: int, url: string, created_at: mixed}>
     */
    public function forDisplay(Receipt $receipt): Collection
    {
        return $receipt->attachments()->get()->map(fn (Media $media): array => [
            'id' => $media->id,
            'name' => $media->name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => route('receipts.attachments.show', [$receipt, $media]),
            'created_at' => $media->created_at,
        ]);
    }

    /**
     * The attachment, or null when its file is missing on disk.
     */
    public function storedFile(Media $attachment): ?Media
    {
        return file_exists($attachment->getPath()) ? $attachment : null;
    }

    public function delete(Media $attachment): void
    {
        $attachment->delete();
    }
}
