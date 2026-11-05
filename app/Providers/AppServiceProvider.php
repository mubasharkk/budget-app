<?php

namespace App\Providers;

use App\Domain\Deals\Services\AldiNordOfferFeed;
use App\Domain\Deals\Services\DealSource;
use App\Domain\Deals\Services\DealSyncService;
use App\Domain\Deals\Services\MarktguruDealSource;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DealSource::class, MarktguruDealSource::class);
        $this->app->tag([AldiNordOfferFeed::class], 'deals.offer-feeds');
        $this->app->when(DealSyncService::class)->needs('$offerFeeds')->giveTagged('deals.offer-feeds');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
