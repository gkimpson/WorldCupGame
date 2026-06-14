<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('world-cup:sync-fixtures')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('world-cup:sync-results-gemini')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Run once on the day after the final group stage fixture (2026-06-28) to populate Round of 32 teams.
Schedule::command('world-cup:resolve-knockout-teams --stage=round_of_32')
    ->dailyAt('09:00')
    ->when(fn () => now()->toDateString() === '2026-06-29')
    ->withoutOverlapping()
    ->runInBackground();
