<?php

namespace App\Livewire\Leaderboard;

use App\Models\UserStat;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Perfect 104 Leaderboard')]
class PerfectLeaderboard extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        $userId = $user?->is_dummy ? null : $user?->id;

        $stats = UserStat::forRealUsers()
            ->with('user')
            ->orderBy('exact_scores', 'desc')
            ->orderBy('total_points', 'desc')
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get();

        $inTop100 = $userId !== null && $stats->contains('user_id', $userId);
        $topEntries = $stats->map(function (UserStat $stat, int $index) use ($userId): array {
            return [
                'rank' => $index + 1,
                'name' => $stat->user->name,
                'exact_scores' => $stat->exact_scores,
                'total_points' => $stat->total_points,
                'predictions_made' => $stat->predictions_made,
                'is_current_user' => $stat->user_id === $userId,
            ];
        })->all();

        $pinnedEntry = null;
        if ($userId !== null && ! $inTop100) {
            $userStat = UserStat::forRealUsers()->where('user_id', $userId)->first();
            if ($userStat !== null) {
                $rank = UserStat::forRealUsers()->where('exact_scores', '>', $userStat->exact_scores)->count()
                    + UserStat::forRealUsers()
                        ->where('exact_scores', $userStat->exact_scores)
                        ->where('total_points', '>', $userStat->total_points)
                        ->count()
                    + UserStat::forRealUsers()
                        ->where('exact_scores', $userStat->exact_scores)
                        ->where('total_points', $userStat->total_points)
                        ->where('id', '<', $userStat->id)
                        ->count()
                    + 1;

                $pinnedEntry = [
                    'rank' => $rank,
                    'name' => $user->name,
                    'exact_scores' => $userStat->exact_scores,
                    'total_points' => $userStat->total_points,
                    'predictions_made' => $userStat->predictions_made,
                ];
            }
        }

        return view('livewire.leaderboard.perfect-leaderboard', [
            'topEntries' => $topEntries,
            'pinnedEntry' => $pinnedEntry,
        ]);
    }
}
