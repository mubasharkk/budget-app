<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    use CrudTrait;
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
        'currency',
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
    ];

    protected $attributes = [
        'currency' => 'EUR',
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
     * Get the file URL
     */
    public function getFileUrlAttribute(): string
    {
        // Use stored_path if available (for public access), otherwise fall back to original_path
        $path = $this->stored_path ?: $this->original_path;
        return asset('storage/' . $path);
    }

    /**
     * Get the public file URL (for direct access)
     */
    public function getPublicFileUrlAttribute(): string
    {
        // Use stored_path if available (for public access), otherwise fall back to original_path
        $path = $this->stored_path ?: $this->original_path;
        return url('storage/' . $path);
    }

    /**
     * Get the direct file access URL (through controller)
     */
    public function getDirectFileUrlAttribute(): string
    {
        return route('receipts.file', $this->id);
    }

    /**
     * Check if the physical file exists
     */
    public function fileExists(): bool
    {
        $path = $this->stored_path ?: $this->original_path;
        return $path && \Storage::disk('public')->exists($path);
    }

    /**
     * Get the full file path
     */
    public function getFilePathAttribute(): string
    {
        $path = $this->stored_path ?: $this->original_path;
        return storage_path('app/public/' . $path);
    }
}
