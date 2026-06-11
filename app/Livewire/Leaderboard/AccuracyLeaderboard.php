<?php

namespace App\Livewire\Leaderboard;

use App\Models\UserStat;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Accuracy Leaderboard')]
class AccuracyLeaderboard extends Component
{
    public function render(): View
    {
        $userId = auth()->id();

        $stats = UserStat::with('user')
            ->orderBy('correct_outcomes', 'desc')
            ->orderBy('predictions_made', 'asc')
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get();

        $inTop100 = $userId !== null && $stats->contains('user_id', $userId);
        $topEntries = $stats->map(function (UserStat $stat, int $index) use ($userId): array {
            $accuracyPct = $stat->predictions_made > 0
                ? round($stat->correct_outcomes / $stat->predictions_made * 100, 1)
                : 0;

            return [
                'rank' => $index + 1,
                'name' => $stat->user->name,
                'correct_outcomes' => $stat->correct_outcomes,
                'predictions_made' => $stat->predictions_made,
                'accuracy_pct' => $accuracyPct,
                'is_current_user' => $stat->user_id === $userId,
            ];
        })->all();

        $pinnedEntry = null;
        if ($userId !== null && ! $inTop100) {
            $userStat = UserStat::where('user_id', $userId)->first();
            if ($userStat !== null) {
                $rank = UserStat::where('correct_outcomes', '>', $userStat->correct_outcomes)->count()
                    + UserStat::where('correct_outcomes', $userStat->correct_outcomes)
                        ->where('predictions_made', '<', $userStat->predictions_made)
                        ->count()
                    + UserStat::where('correct_outcomes', $userStat->correct_outcomes)
                        ->where('predictions_made', $userStat->predictions_made)
                        ->where('id', '<', $userStat->id)
                        ->count()
                    + 1;

                $accuracyPct = $userStat->predictions_made > 0
                    ? round($userStat->correct_outcomes / $userStat->predictions_made * 100, 1)
                    : 0;

                $pinnedEntry = [
                    'rank' => $rank,
                    'name' => auth()->user()->name,
                    'correct_outcomes' => $userStat->correct_outcomes,
                    'predictions_made' => $userStat->predictions_made,
                    'accuracy_pct' => $accuracyPct,
                ];
            }
        }

        return view('livewire.leaderboard.accuracy-leaderboard', [
            'topEntries' => $topEntries,
            'pinnedEntry' => $pinnedEntry,
        ]);
    }
}
