<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
    ];

    /**
     * Get the parent category
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the subcategories
     */
    public function subcategories(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get receipts for this category
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'category_id');
    }

    /**
     * Get receipts for this subcategory
     */
    public function subcategoryReceipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'subcategory_id');
    }

    /**
     * Check if this is a parent category (no parent_id)
     */
    public function isParent(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if this is a subcategory (has parent_id)
     */
    public function isSubcategory(): bool
    {
        return !is_null($this->parent_id);
    }
}
