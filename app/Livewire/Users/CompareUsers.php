<?php

namespace App\Livewire\Users;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use App\Models\UserStat;
use App\Models\UserWeeklyStat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Compare Players')]
class CompareUsers extends Component
{
    public ?User $userA = null;

    public ?User $userB = null;

    #[Url]
    public string $searchA = '';

    #[Url]
    public string $searchB = '';

    public function mount(?User $userA = null, ?User $userB = null): void
    {
        $this->userA = $userA;
        $this->userB = $userB;
    }

    public function selectUserA(int $userId): void
    {
        $this->searchA = '';
        $bId = $this->userB?->id;

        if ($bId !== null) {
            $this->redirect(route('users.compare.show', [$userId, $bId]));
        } else {
            $this->userA = User::find($userId);
        }
    }

    public function selectUserB(int $userId): void
    {
        $this->searchB = '';
        $aId = $this->userA?->id;

        if ($aId !== null) {
            $this->redirect(route('users.compare.show', [$aId, $userId]));
        } else {
            $this->userB = User::find($userId);
        }
    }

    public function render(): View
    {
        $statsA = $this->userA ? UserStat::where('user_id', $this->userA->id)->first() : null;
        $statsB = $this->userB ? UserStat::where('user_id', $this->userB->id)->first() : null;

        $weeklyA = $this->userA
            ? UserWeeklyStat::where('user_id', $this->userA->id)->orderBy('week_number')->get()
            : collect();

        $weeklyB = $this->userB
            ? UserWeeklyStat::where('user_id', $this->userB->id)->orderBy('week_number')->get()
            : collect();

        $allWeeks = $weeklyA->pluck('week_number')
            ->merge($weeklyB->pluck('week_number'))
            ->unique()
            ->sort()
            ->values();

        $accuracyA = $statsA && $statsA->predictions_made > 0
            ? round($statsA->correct_outcomes / $statsA->predictions_made * 100, 1)
            : 0.0;

        $accuracyB = $statsB && $statsB->predictions_made > 0
            ? round($statsB->correct_outcomes / $statsB->predictions_made * 100, 1)
            : 0.0;

        $rankA = $statsA ? UserStat::where('total_points', '>', $statsA->total_points)->count() + 1 : 0;
        $rankB = $statsB ? UserStat::where('total_points', '>', $statsB->total_points)->count() + 1 : 0;

        $matches = $this->buildMatchList();

        $searchResultsA = $this->searchA !== ''
            ? User::where('name', 'like', '%'.$this->searchA.'%')
                ->where('is_dummy', false)
                ->limit(10)
                ->get()
            : collect();

        $searchResultsB = $this->searchB !== ''
            ? User::where('name', 'like', '%'.$this->searchB.'%')
                ->where('is_dummy', false)
                ->limit(10)
                ->get()
            : collect();

        return view('livewire.users.compare-users', [
            'statsA' => $statsA,
            'statsB' => $statsB,
            'weeklyA' => $weeklyA,
            'weeklyB' => $weeklyB,
            'allWeeks' => $allWeeks,
            'matches' => $matches,
            'accuracyA' => $accuracyA,
            'accuracyB' => $accuracyB,
            'rankA' => $rankA,
            'rankB' => $rankB,
            'searchResultsA' => $searchResultsA,
            'searchResultsB' => $searchResultsB,
        ]);
    }

    /** @return array<int, array{fixture: Fixture, predA: Prediction|null, predB: Prediction|null}> */
    private function buildMatchList(): array
    {
        if (! $this->userA || ! $this->userB) {
            return [];
        }

        $userIds = [$this->userA->id, $this->userB->id];

        /** @var Collection<int, Prediction> $allPredictions */
        $allPredictions = Prediction::whereIn('user_id', $userIds)
            ->whereNotNull('points')
            ->get();

        $predictions = $allPredictions->groupBy('fixture_id');
        $fixtureIds = $predictions->keys();

        $userAId = $this->userA->id;
        $userBId = $this->userB->id;

        return Fixture::where('status', FixtureStatus::Completed)
            ->whereIn('id', $fixtureIds)
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(function (Fixture $fixture) use ($predictions, $userAId, $userBId): array {
                /** @var Collection<int, Prediction> $preds */
                $preds = $predictions->get($fixture->id, new Collection);

                return [
                    'fixture' => $fixture,
                    'predA' => $preds->first(fn (Prediction $p): bool => $p->user_id === $userAId),
                    'predB' => $preds->first(fn (Prediction $p): bool => $p->user_id === $userBId),
                ];
            })
            ->all();
    }
}
