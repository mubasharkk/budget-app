<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    use CrudTrait, HasFactory;

    protected $fillable = [
        'user_id',
        'provider_id',
        'category_id',
        'name',
        'description',
        'amount',
        'currency',
        'billing_cycle',
        'billing_day',
        'start_date',
        'end_date',
        'next_billing_date',
        'status',
        'auto_renew',
        'notes',
    ];

    protected $attributes = [
        'currency' => 'EUR',
        'billing_cycle' => 'monthly',
        'status' => 'active',
        'auto_renew' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'billing_cycle' => BillingCycle::class,
            'status' => ContractStatus::class,
            'billing_day' => 'integer',
            'auto_renew' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_billing_date' => 'date',
        ];
    }

    protected $appends = [
        'projected_monthly_amount',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The contract's cost normalized to a monthly equivalent.
     */
    public function projectedMonthlyAmount(): float
    {
        return $this->billing_cycle->toMonthlyAmount((float) $this->amount);
    }

    public function getProjectedMonthlyAmountAttribute(): float
    {
        return $this->projectedMonthlyAmount();
    }
}
