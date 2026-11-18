<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A general name for what a user consumes ("Milk", "Coffee beans"), grouping
 * products of any brand or size. `unit` is the base unit amounts are totalled
 * in: l, kg or pcs.
 */
class ConsumableGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'unit',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
