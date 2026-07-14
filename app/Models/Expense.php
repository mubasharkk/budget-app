<?php

namespace App\Models;

use App\Enums\ExpenseType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Expense extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const DOCUMENT_COLLECTION = 'document';

    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'spent_on',
        'description',
        'expense_type',
        'notes',
    ];

    protected $attributes = [
        'currency' => 'EUR',
        'expense_type' => 'personal',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'document',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_on' => 'date',
            'expense_type' => ExpenseType::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::DOCUMENT_COLLECTION)
            ->useDisk('public')
            ->singleFile();
    }

    /**
     * The attached invoice/document, serialized for the frontend.
     *
     * @return array{name: string, size: int, url: string}|null
     */
    public function getDocumentAttribute(): ?array
    {
        $media = $this->getFirstMedia(self::DOCUMENT_COLLECTION);

        if ($media === null) {
            return null;
        }

        return [
            'name' => $media->file_name,
            'size' => $media->size,
            'url' => route('expenses.document', $this->id),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
