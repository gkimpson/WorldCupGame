<?php

namespace App\Livewire\Leaderboard;

use App\Models\Fixture;
use App\Models\UserStat;
use App\Models\UserWeeklyStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Accuracy Leaderboard')]
class AccuracyLeaderboard extends Component
{
    #[Url(as: 'week')]
    public ?int $week = null;

    public function mount(): void
    {
        if ($this->week === null) {
            $available = $this->availableWeeks();
            if ($available->isNotEmpty()) {
                $dayOffset = (int) Carbon::parse('2026-06-11')->diffInDays(now()->startOfDay(), false);
                $currentWeek = max(1, (int) floor($dayOffset / 7) + 1);
                $this->week = $available->contains($currentWeek) ? $currentWeek : $available->first();
            }
        }
    }

    public function previousWeek(): void
    {
        $weeks = $this->availableWeeks();
        $index = $weeks->search($this->week);
        if ($index > 0) {
            $this->week = $weeks->get($index - 1);
        }
    }

    public function nextWeek(): void
    {
        $weeks = $this->availableWeeks();
        $index = $weeks->search($this->week);
        if ($index !== false && $index < $weeks->count() - 1) {
            $this->week = $weeks->get($index + 1);
        }
    }

    public function showAllTime(): void
    {
        $this->week = null;
    }

    public function render(): View
    {
        $user = auth()->user();
        $userId = $user?->is_dummy ? null : $user?->id;

        $stats = $this->statsQuery()
            ->with('user')
            ->orderBy('correct_outcomes', 'desc')
            ->orderBy('predictions_made', 'asc')
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get();

        $inTop100 = $userId !== null && $stats->contains('user_id', $userId);
        $topEntries = $stats->map(function (UserStat|UserWeeklyStat $stat, int $index) use ($userId): array {
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
            $userStat = $this->statsQuery()->where('user_id', $userId)->first();
            if ($userStat !== null) {
                $rank = $this->statsQuery()->where('correct_outcomes', '>', $userStat->correct_outcomes)->count()
                    + $this->statsQuery()
                        ->where('correct_outcomes', $userStat->correct_outcomes)
                        ->where('predictions_made', '<', $userStat->predictions_made)
                        ->count()
                    + $this->statsQuery()
                        ->where('correct_outcomes', $userStat->correct_outcomes)
                        ->where('predictions_made', $userStat->predictions_made)
                        ->where('id', '<', $userStat->id)
                        ->count()
                    + 1;

                $accuracyPct = $userStat->predictions_made > 0
                    ? round($userStat->correct_outcomes / $userStat->predictions_made * 100, 1)
                    : 0;

                $pinnedEntry = [
                    'rank' => $rank,
                    'name' => $user->name,
                    'correct_outcomes' => $userStat->correct_outcomes,
                    'predictions_made' => $userStat->predictions_made,
                    'accuracy_pct' => $accuracyPct,
                ];
            }
        }

        $availableWeeks = $this->availableWeeks();

        return view('livewire.leaderboard.accuracy-leaderboard', [
            'topEntries' => $topEntries,
            'pinnedEntry' => $pinnedEntry,
            'availableWeeks' => $availableWeeks,
            'isFirstWeek' => $this->week !== null && $availableWeeks->first() === $this->week,
            'isLastWeek' => $this->week !== null && $availableWeeks->last() === $this->week,
        ]);
    }

    /** @return Builder<UserStat>|Builder<UserWeeklyStat> */
    private function statsQuery(): Builder
    {
        if ($this->week === null) {
            return UserStat::forRealUsers();
        }

        return UserWeeklyStat::forRealUsers()->where('week_number', $this->week);
    }

    /** @return Collection<int, int> */
    private function availableWeeks(): Collection
    {
        return Fixture::query()
            ->whereNotNull('week_number')
            ->whereHas('predictions', fn (Builder $q) => $q->whereNotNull('points'))
            ->distinct()
            ->orderBy('week_number')
            ->pluck('week_number');
    }
}
