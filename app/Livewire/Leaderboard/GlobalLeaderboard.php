<?php

namespace App\Livewire\Leaderboard;

use App\Models\UserStat;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Global Leaderboard')]
class GlobalLeaderboard extends Component
{
    public function render(): View
    {
        $userId = auth()->id();

        $stats = UserStat::with('user')
            ->orderBy('total_points', 'desc')
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get();

        $inTop100 = $userId !== null && $stats->contains('user_id', $userId);
        $topEntries = $stats->map(function (UserStat $stat, int $index) use ($userId): array {
            return [
                'rank' => $index + 1,
                'name' => $stat->user->name,
                'total_points' => $stat->total_points,
                'predictions_made' => $stat->predictions_made,
                'is_current_user' => $stat->user_id === $userId,
            ];
        })->all();

        $pinnedEntry = null;
        if ($userId !== null && ! $inTop100) {
            $userStat = UserStat::where('user_id', $userId)->first();
            if ($userStat !== null) {
                $rank = UserStat::where('total_points', '>', $userStat->total_points)->count()
                    + UserStat::where('total_points', $userStat->total_points)
                        ->where('id', '<', $userStat->id)
                        ->count()
                    + 1;

                $pinnedEntry = [
                    'rank' => $rank,
                    'name' => auth()->user()->name,
                    'total_points' => $userStat->total_points,
                    'predictions_made' => $userStat->predictions_made,
                ];
            }
        }

        return view('livewire.leaderboard.global-leaderboard', [
            'topEntries' => $topEntries,
            'pinnedEntry' => $pinnedEntry,
        ]);
    }
}
