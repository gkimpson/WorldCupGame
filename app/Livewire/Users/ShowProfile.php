<?php

namespace App\Livewire\Users;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Player Profile')]
class ShowProfile extends Component
{
    public User $user;

    public int $globalRank = 0;

    public int $totalPoints = 0;

    public int $predictionsMade = 0;

    public int $correctOutcomes = 0;

    public int $exactScores = 0;

    public float $accuracyPct = 0.0;

    public Collection $recentResults;

    public function mount(User $user): void
    {
        $this->user = $user;

        $stat = UserStat::where('user_id', $user->id)->first();

        if ($stat !== null) {
            $this->totalPoints = $stat->total_points;
            $this->predictionsMade = $stat->predictions_made;
            $this->correctOutcomes = $stat->correct_outcomes;
            $this->exactScores = $stat->exact_scores;
            $this->accuracyPct = $stat->predictions_made > 0
                ? round($stat->correct_outcomes / $stat->predictions_made * 100, 1)
                : 0.0;
            $this->globalRank = UserStat::where('total_points', '>', $stat->total_points)->count() + 1;
        }

        $this->recentResults = Prediction::where('predictions.user_id', $user->id)
            ->whereNotNull('predictions.points')
            ->whereHas('fixture', fn ($q) => $q->where('status', FixtureStatus::Completed))
            ->join('fixtures', 'fixtures.id', '=', 'predictions.fixture_id')
            ->orderBy('fixtures.scheduled_at', 'desc')
            ->limit(10)
            ->select('predictions.*')
            ->with(['fixture.homeTeam', 'fixture.awayTeam'])
            ->get();
    }

    public function render(): View
    {
        return view('livewire.users.show-profile', [
            'totalMatches' => Fixture::TOTAL_WORLD_CUP_MATCHES,
        ]);
    }
}
