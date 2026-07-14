<?php

namespace App\Models;

use App\Enums\ExpenseType;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Receipt extends Model implements HasMedia
{
    use CrudTrait, HasFactory, InteractsWithMedia;

    public const RECEIPT_COLLECTION = 'receipt';

    protected $fillable = [
        'user_id',
        'original_filename',
        'original_path',
        'stored_path',
        'file_type',
        'mime',
        'file_size',
        'ocr_text',
        'ocr_data',
        'vendor',
        'receipt_number',
        'currency',
        'expense_type',
        'total_amount',
        'receipt_date',
        'receipt_timezone',
        'status',
        'error_message',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'file_size' => 'integer',
        'ocr_data' => 'array',
        'receipt_date' => 'datetime',
        'expense_type' => ExpenseType::class,
    ];

    protected $attributes = [
        'currency' => 'EUR',
        'expense_type' => 'personal',
    ];

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the receipt items
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReceiptItem::class);
    }

    /**
     * Check if receipt is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if receipt is processed
     */
    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    /**
     * Check if receipt failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * The receipt file is stored on a private disk and served only through the
     * ownership-checked receipts.file route.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::RECEIPT_COLLECTION)
            ->useDisk('local')
            ->singleFile();
    }

    /**
     * The stored receipt file, whether on media-library or the legacy public disk.
     */
    private function legacyPath(): ?string
    {
        return $this->stored_path ?: $this->original_path;
    }

    /**
     * All file URL accessors resolve to the ownership-checked controller route;
     * the underlying file is private and never linked directly.
     */
    public function getFileUrlAttribute(): string
    {
        return route('receipts.file', $this->id);
    }

    public function getPublicFileUrlAttribute(): string
    {
        return route('receipts.file', $this->id);
    }

    public function getDirectFileUrlAttribute(): string
    {
        return route('receipts.file', $this->id);
    }

    /**
     * Check if the underlying file exists (media-library first, legacy fallback).
     */
    public function fileExists(): bool
    {
        $media = $this->getFirstMedia(self::RECEIPT_COLLECTION);

        if ($media !== null) {
            return file_exists($media->getPath());
        }

        $legacy = $this->legacyPath();

        return $legacy !== null && Storage::disk('public')->exists($legacy);
    }

    /**
     * Absolute path to the underlying file (media-library first, legacy fallback).
     */
    public function getFilePathAttribute(): ?string
    {
        $media = $this->getFirstMedia(self::RECEIPT_COLLECTION);

        if ($media !== null) {
            return $media->getPath();
        }

        $legacy = $this->legacyPath();

        return $legacy !== null ? Storage::disk('public')->path($legacy) : null;
    }
}
