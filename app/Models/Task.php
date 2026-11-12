<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'notes',
        'due_date',
        'priority',
        'amount',
        'currency',
        'repeats',
        'taskable_type',
        'taskable_id',
        'completed_at',
    ];

    protected $attributes = [
        'priority' => 'normal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'priority' => TaskPriority::class,
            'repeats' => BillingCycle::class,
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The receipt, contract, budget or savings entry the task is about.
     */
    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeDone(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }
}
