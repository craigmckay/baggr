<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs one cron entry running "schedule:run" every minute (deploy/README.md).
Schedule::command('mwis:fetch')
    ->dailyAt(config('mwis.fetch_at'))
    ->timezone(config('mwis.timezone'))
    ->when(fn () => config('mwis.enabled'));
