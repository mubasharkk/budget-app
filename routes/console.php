<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('contracts:roll-billing-dates')->dailyAt('01:00');
Schedule::command('digests:generate-monthly')->monthlyOn(1, '06:00');
