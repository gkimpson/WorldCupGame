<?php

namespace App\Listeners;

use App\Events\ResultImported;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\UserStat;
use App\Models\UserWeeklyStat;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecalculateUserStats implements ShouldQueue
{
    public function handle(ResultImported $event): void
    {
        $fixture = $event->fixture;

        $userIds = Prediction::where('fixture_id', $fixture->id)
            ->pluck('user_id')
            ->unique();

        foreach ($userIds as $userId) {
            $this->updateOverallStats($userId);
            $this->updateWeeklyStats($userId, $fixture);
        }
    }

    private function updateOverallStats(int $userId): void
    {
        $stats = Prediction::where('user_id', $userId)
            ->whereNotNull('points')
            ->selectRaw('
                SUM(points) as total_points,
                COUNT(*) as predictions_made,
                SUM(CASE WHEN points >= 1 THEN 1 ELSE 0 END) as correct_outcomes,
                SUM(CASE WHEN points = 3 THEN 1 ELSE 0 END) as exact_scores
            ')
            ->first();

        UserStat::updateOrCreate(
            ['user_id' => $userId],
            [
                'total_points' => (int) ($stats->total_points ?? 0),
                'predictions_made' => (int) ($stats->predictions_made ?? 0),
                'correct_outcomes' => (int) ($stats->correct_outcomes ?? 0),
                'exact_scores' => (int) ($stats->exact_scores ?? 0),
            ]
        );
    }

    private function updateWeeklyStats(int $userId, Fixture $fixture): void
    {
        if ($fixture->week_number === null) {
            return;
        }

        $weekNumber = $fixture->week_number;

        $fixtureIds = Fixture::where('week_number', $weekNumber)->pluck('id');

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

        UserWeeklyStat::updateOrCreate(
            ['user_id' => $userId, 'week_number' => $weekNumber],
            [
                'total_points' => (int) ($stats->total_points ?? 0),
                'predictions_made' => (int) ($stats->predictions_made ?? 0),
                'correct_outcomes' => (int) ($stats->correct_outcomes ?? 0),
                'exact_scores' => (int) ($stats->exact_scores ?? 0),
            ]
        );
    }
}
