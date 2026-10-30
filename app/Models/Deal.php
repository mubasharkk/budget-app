<?php

namespace App\Models;

use App\Enums\Retailer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'external_id',
        'retailer',
        'category_id',
        'advertiser_name',
        'title',
        'brand',
        'description',
        'external_url',
        'price',
        'old_price',
        'discount_percent',
        'unit',
        'unit_price',
        'unit_price_unit',
        'valid_from',
        'valid_to',
        'raw',
        'fetched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retailer' => Retailer::class,
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'discount_percent' => 'integer',
            'unit_price' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'raw' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function postalCodes(): HasMany
    {
        return $this->hasMany(DealPostalCode::class);
    }

    /**
     * Offers listed for the given postal code.
     *
     * @param  Builder<Deal>  $query
     */
    public function scopeAvailableIn(Builder $query, string $postalCode): void
    {
        $query->whereHas('postalCodes', fn (Builder $postalCodes) => $postalCodes->where('postal_code', $postalCode));
    }

    /**
     * Offers whose validity window includes today (open-ended dates count as valid).
     *
     * @param  Builder<Deal>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $today = now()->toDateString();

        $query->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $today));
    }

    /**
     * Offers announced for a later start date (e.g. next week's leaflet).
     *
     * @param  Builder<Deal>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereDate('valid_from', '>', now()->toDateString());
    }
}
