<?php

namespace App\Console\Commands;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use App\Models\UserWeeklyStat;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leaderboard:recalculate-weekly-stats')]
#[Description('Backfill user_weekly_stats for all users across all scored fixture weeks.')]
class RecalculateWeeklyStats extends Command
{
    public function handle(): int
    {
        $weeks = Fixture::whereNotNull('week_number')->distinct()->orderBy('week_number')->pluck('week_number');

        if ($weeks->isEmpty()) {
            $this->info('No fixture weeks assigned yet. Run world-cup:assign-fixture-weeks first.');

            return self::SUCCESS;
        }

        $userIds = User::where('is_dummy', false)->pluck('id');
        $total = 0;

        foreach ($weeks as $weekNumber) {
            $fixtureIds = Fixture::where('week_number', $weekNumber)->pluck('id');

            foreach ($userIds as $userId) {
                $stats = Prediction::where('user_id', $userId)
                    ->whereNotNull('points')
                    ->whereIn('fixture_id', $fixtureIds)
                    ->selectRaw('
                        SUM(points) as total_points,
                        COUNT(*) as predictions_made,
                        SUM(CASE WHEN points >= 1 THEN 1 ELSE 0 END) as correct_outcomes,
                        SUM(CASE WHEN points = 3 THEN 1 ELSE 0 END) as exact_scores
                    ')
                    ->first();

                if (! $stats || (int) $stats->predictions_made === 0) {
                    continue;
                }

                UserWeeklyStat::updateOrCreate(
                    ['user_id' => $userId, 'week_number' => $weekNumber],
                    [
                        'total_points' => (int) ($stats->total_points ?? 0),
                        'predictions_made' => (int) ($stats->predictions_made ?? 0),
                        'correct_outcomes' => (int) ($stats->correct_outcomes ?? 0),
                        'exact_scores' => (int) ($stats->exact_scores ?? 0),
                    ]
                );

                $total++;
            }

            $this->line("  Week {$weekNumber}: processed.");
        }

        $this->info("Done. {$total} user weekly stat rows created/updated.");

        return self::SUCCESS;
    }
}
