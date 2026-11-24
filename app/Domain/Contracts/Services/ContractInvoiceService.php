<?php

namespace App\Domain\Contracts\Services;

use App\Models\Contract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Invoice files kept on a contract. They are stored as-is: never read by the
 * AI and never counted as spending, since the contract's cost already is.
 */
class ContractInvoiceService
{
    public const MAX_INVOICES = 50;

    public const ALLOWED_MIMES = 'jpg,jpeg,png,heic,heif,webp,pdf';

    public const MIMES_MESSAGE = 'Invoices must be a PDF or a photo.';

    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, Media>
     */
    public function attach(Contract $contract, array $files): Collection
    {
        return collect($files)->map(function (UploadedFile $file) use ($contract): Media {
            $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin'));

            return $contract->addMedia($file)
                ->usingFileName(Str::uuid().'.'.$extension)
                ->usingName($file->getClientOriginalName() ?: 'invoice')
                ->toMediaCollection(Contract::INVOICES_COLLECTION);
        });
    }

    public function remainingSlots(Contract $contract): int
    {
        return max(0, self::MAX_INVOICES - $contract->invoices()->count());
    }

    /**
     * Newest first, for the contract page.
     *
     * @return Collection<int, array{id: int, name: string, mime_type: ?string, size: int, url: string, created_at: mixed}>
     */
    public function forDisplay(Contract $contract): Collection
    {
        return $contract->invoices()->reorder()->latest()->latest('id')->get()->map(fn (Media $media): array => [
            'id' => $media->id,
            'name' => $media->name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => route('contracts.invoices.show', [$contract, $media]),
            'created_at' => $media->created_at,
        ]);
    }

    /**
     * The invoice, or null when its file is missing on disk.
     */
    public function storedFile(Media $invoice): ?Media
    {
        return file_exists($invoice->getPath()) ? $invoice : null;
    }

    public function delete(Media $invoice): void
    {
        $invoice->delete();
    }
}
