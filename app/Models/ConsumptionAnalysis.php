<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An AI-written analysis of a user's consumption in a period. `payload` holds
 * the exact figures the AI was given; `findings` are {title, detail} pairs.
 */
class ConsumptionAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'period_start',
        'period_end',
        'summary',
        'findings',
        'tips',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'findings' => 'array',
            'tips' => 'array',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
