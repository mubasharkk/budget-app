<?php

namespace App\Models;

use App\Enums\BudgetPeriod;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use CrudTrait, HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'period',
        'amount',
        'currency',
        'starts_on',
    ];

    protected $attributes = [
        'currency' => 'EUR',
        'period' => 'monthly',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => BudgetPeriod::class,
            'amount' => 'decimal:2',
            'starts_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
