<?php

namespace App\Livewire\Leaderboard;

use App\Models\Fixture;
use App\Models\UserStat;
use App\Models\UserWeeklyStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Global Leaderboard')]
class GlobalLeaderboard extends Component
{
    #[Url(as: 'week')]
    public ?int $week = null;

    public function mount(): void
    {
        if ($this->week === null) {
            $this->week = $this->availableWeeks()->last();
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

        $availableWeeks = $this->availableWeeks();

        $stats = $this->statsQuery()
            ->with('user')
            ->orderBy('total_points', 'desc')
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get();

        $inTop100 = $userId !== null && $stats->contains('user_id', $userId);
        $topEntries = $stats->map(function (UserStat|UserWeeklyStat $stat, int $index) use ($userId): array {
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
            $userStat = $this->statsQuery()->where('user_id', $userId)->first();
            if ($userStat !== null) {
                $rank = $this->statsQuery()->where('total_points', '>', $userStat->total_points)->count()
                    + $this->statsQuery()
                        ->where('total_points', $userStat->total_points)
                        ->where('id', '<', $userStat->id)
                        ->count()
                    + 1;

                $pinnedEntry = [
                    'rank' => $rank,
                    'name' => $user->name,
                    'total_points' => $userStat->total_points,
                    'predictions_made' => $userStat->predictions_made,
                ];
            }
        }

        return view('livewire.leaderboard.global-leaderboard', [
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
