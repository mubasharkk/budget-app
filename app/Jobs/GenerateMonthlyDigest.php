<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\DigestService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyDigest implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public User $user,
        public ?string $month = null,
    ) {}

    public function handle(DigestService $digestService): void
    {
        try {
            $month = $this->month
                ? CarbonImmutable::parse($this->month.'-01')
                : null;

            $digestService->generateForUser($this->user, $month);

            Log::info('Monthly digest generated', ['user_id' => $this->user->id]);
        } catch (\Exception $e) {
            Log::error('Monthly digest generation failed', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
