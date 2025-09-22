<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use CrudTrait;
    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'is_active',
        'sort_order',
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
     * Get receipt items for this category
     */
    public function receiptItems(): HasMany
    {
        return $this->hasMany(ReceiptItem::class, 'category_id');
    }

    /**
     * Get receipt items for this subcategory
     */
    public function subcategoryReceiptItems(): HasMany
    {
        return $this->hasMany(ReceiptItem::class, 'subcategory_id');
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
