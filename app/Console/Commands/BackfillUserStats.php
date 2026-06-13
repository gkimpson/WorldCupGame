<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leaderboard:backfill-user-stats')]
#[Description('Backfill user_stats for all real users from their scored predictions.')]
class BackfillUserStats extends Command
{
    public function handle(): int
    {
        $userIds = User::where('is_dummy', false)->pluck('id');

        if ($userIds->isEmpty()) {
            $this->info('No real users found.');

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($userIds as $userId) {
            $stats = Prediction::where('user_id', $userId)
                ->whereNotNull('points')
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

            UserStat::updateOrCreate(
                ['user_id' => $userId],
                [
                    'total_points' => (int) ($stats->total_points ?? 0),
                    'predictions_made' => (int) ($stats->predictions_made ?? 0),
                    'correct_outcomes' => (int) ($stats->correct_outcomes ?? 0),
                    'exact_scores' => (int) ($stats->exact_scores ?? 0),
                ]
            );

            $total++;
        }

        $this->info("Done. {$total} user stat rows created/updated.");

        return self::SUCCESS;
    }
}
