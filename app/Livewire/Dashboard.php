<?php

namespace App\Livewire;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\Prediction;
use App\Models\UserStat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public int $globalRank = 0;

    public int $totalPoints = 0;

    public int $predictionsMade = 0;

    /** @var Collection<int, Fixture> */
    public Collection $upcomingFixtures;

    /** @var Collection<int, Prediction> */
    public Collection $recentResults;

    public ?League $topLeague = null;

    public ?int $topLeagueRank = null;

    public bool $hasAnyPredictions = false;

    public function mount(): void
    {
        $user = Auth::user();

        $userStat = UserStat::where('user_id', $user->id)->first();

        if ($userStat !== null) {
            $this->totalPoints = $userStat->total_points;
            $this->predictionsMade = $userStat->predictions_made;
            $this->globalRank = UserStat::where('total_points', '>', $this->totalPoints)->count() + 1;
        }

        $this->hasAnyPredictions = $this->predictionsMade > 0;

        $this->upcomingFixtures = Fixture::where('scheduled_at', '>', now())
            ->where('status', '!=', FixtureStatus::Completed)
            ->orderBy('scheduled_at')
            ->limit(5)
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        $this->recentResults = Prediction::where('predictions.user_id', $user->id)
            ->whereNotNull('predictions.points')
            ->whereHas('fixture', fn ($q) => $q->where('status', FixtureStatus::Completed))
            ->join('fixtures', 'fixtures.id', '=', 'predictions.fixture_id')
            ->orderBy('fixtures.scheduled_at', 'desc')
            ->limit(5)
            ->select('predictions.*')
            ->with(['fixture.homeTeam', 'fixture.awayTeam'])
            ->get();

        $this->resolveTopLeague($user->id);
    }

    private function resolveTopLeague(int $userId): void
    {
        $memberships = LeagueMember::where('user_id', $userId)
            ->with('league')
            ->get();

        if ($memberships->isEmpty()) {
            return;
        }

        $bestRank = null;
        $bestLeague = null;

        foreach ($memberships as $membership) {
            $rank = $this->computeLeagueRank($membership->league_id, $userId);

            if ($bestRank === null || $rank < $bestRank) {
                $bestRank = $rank;
                $bestLeague = $membership->league;
            }
        }

        $this->topLeague = $bestLeague;
        $this->topLeagueRank = $bestRank;
    }

    private function computeLeagueRank(string $leagueId, int $userId): int
    {
        $myPoints = UserStat::where('user_id', $userId)->value('total_points') ?? 0;

        $usersAhead = LeagueMember::where('league_id', $leagueId)
            ->where('user_id', '!=', $userId)
            ->whereHas('user.stat', fn ($q) => $q->where('total_points', '>', $myPoints))
            ->count();

        return $usersAhead + 1;
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
