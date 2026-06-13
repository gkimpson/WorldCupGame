<?php

namespace App\Console\Commands;

use App\Models\Fixture;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('world-cup:assign-fixture-weeks')]
#[Description('Assign week_number to fixtures based on scheduled_at relative to the tournament start date.')]
class AssignFixtureWeeks extends Command
{
    private const TOURNAMENT_START = '2026-06-11';

    public function handle(): int
    {
        $start = Carbon::parse(self::TOURNAMENT_START)->startOfDay();

        $updated = Fixture::whereNotNull('scheduled_at')->get()->each(function (Fixture $fixture) use ($start): void {
            $dayOffset = (int) $start->diffInDays($fixture->scheduled_at->startOfDay(), false);
            $week = (int) floor($dayOffset / 7) + 1;
            $fixture->update(['week_number' => $week]);
        })->count();

        $this->info("Assigned week numbers to {$updated} fixtures.");

        return self::SUCCESS;
    }
}
