<?php

namespace App\Jobs;

use App\Domain\Deals\Services\DealSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncDealsForPostalCode implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    public function __construct(public string $postalCode) {}

    public function uniqueId(): string
    {
        return $this->postalCode;
    }

    public function handle(DealSyncService $dealSyncService): void
    {
        $count = $dealSyncService->syncPostalCode($this->postalCode);

        Log::info('Deals synced', ['postal_code' => $this->postalCode, 'offers' => $count]);
    }
}
