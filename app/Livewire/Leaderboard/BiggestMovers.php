<?php

namespace App\Livewire\Leaderboard;

use App\Models\UserWeeklyStat;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Biggest Movers')]
class BiggestMovers extends Component
{
    public function render(): View
    {
        return view('livewire.leaderboard.biggest-movers', [
            'movers' => $this->getMovers(),
        ]);
    }

    /** @return array<int, object> */
    private function getMovers(): array
    {
        $currentWeek = UserWeeklyStat::forRealUsers()->max('week_number');

        if ($currentWeek === null || $currentWeek < 2) {
            return [];
        }

        $previousWeek = $currentWeek - 1;

        return DB::select(<<<'SQL'
            WITH current_ranks AS (
                SELECT uws.user_id,
                       ROW_NUMBER() OVER (ORDER BY uws.total_points DESC, uws.id ASC) AS `rank`
                FROM user_weekly_stats uws
                INNER JOIN users u ON u.id = uws.user_id
                WHERE uws.week_number = ?
                  AND u.is_dummy = 0
            ),
            prev_ranks AS (
                SELECT uws.user_id,
                       ROW_NUMBER() OVER (ORDER BY uws.total_points DESC, uws.id ASC) AS `rank`
                FROM user_weekly_stats uws
                INNER JOIN users u ON u.id = uws.user_id
                WHERE uws.week_number = ?
                  AND u.is_dummy = 0
            )
            SELECT u.name,
                   u.id AS user_id,
                   cr.`rank` AS current_rank,
                   pr.`rank` AS prev_rank,
                   (CAST(pr.`rank` AS SIGNED) - CAST(cr.`rank` AS SIGNED)) AS rank_change,
                   uws.total_points
            FROM current_ranks cr
            INNER JOIN prev_ranks pr ON pr.user_id = cr.user_id
            INNER JOIN users u ON u.id = cr.user_id
            INNER JOIN user_weekly_stats uws ON uws.user_id = cr.user_id AND uws.week_number = ?
            ORDER BY ABS(CAST(pr.`rank` AS SIGNED) - CAST(cr.`rank` AS SIGNED)) DESC, (CAST(pr.`rank` AS SIGNED) - CAST(cr.`rank` AS SIGNED)) DESC
            LIMIT 10
        SQL, [$currentWeek, $previousWeek, $currentWeek]);
    }
}
