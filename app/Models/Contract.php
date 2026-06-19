<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
        'last_paid_at',
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
            'last_paid_at' => 'date',
        ];
    }

    protected $appends = [
        'projected_monthly_amount',
        'is_paid_this_month',
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

    public function isDueInCalendarMonth(?CarbonInterface $anchor = null): bool
    {
        if ($this->next_billing_date === null) {
            return false;
        }

        $anchor = CarbonImmutable::instance($anchor ?? CarbonImmutable::today());
        $due = CarbonImmutable::instance($this->next_billing_date);

        return $due->betweenIncluded($anchor->startOfMonth(), $anchor->endOfMonth());
    }

    public function isPaidThisMonth(?CarbonInterface $anchor = null): bool
    {
        if ($this->last_paid_at === null) {
            return false;
        }

        $anchor = CarbonImmutable::instance($anchor ?? CarbonImmutable::today());

        return CarbonImmutable::instance($this->last_paid_at)
            ->betweenIncluded($anchor->startOfMonth(), $anchor->endOfMonth());
    }

    public function getIsPaidThisMonthAttribute(): bool
    {
        return $this->isPaidThisMonth();
    }

    public function isUnpaidForCurrentDue(): bool
    {
        if ($this->last_paid_at === null || $this->next_billing_date === null) {
            return true;
        }

        return CarbonImmutable::instance($this->last_paid_at)
            ->lt(CarbonImmutable::instance($this->next_billing_date));
    }

    public function isPayableThisMonth(?CarbonInterface $anchor = null): bool
    {
        return $this->status->isBillable()
            && $this->isDueInCalendarMonth($anchor)
            && $this->isUnpaidForCurrentDue();
    }
}
