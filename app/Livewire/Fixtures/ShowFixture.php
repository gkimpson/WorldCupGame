<?php

namespace App\Livewire\Fixtures;

use App\Models\Fixture;
use App\Models\Prediction;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Fixture')]
class ShowFixture extends Component
{
    public Fixture $fixture;

    public function mount(Fixture $fixture): void
    {
        $this->fixture = $fixture->load(['homeTeam', 'awayTeam']);
    }

    public function render(): View
    {
        $userPrediction = null;
        $userId = auth()->id();

        if ($userId !== null) {
            $userPrediction = Prediction::where('fixture_id', $this->fixture->id)
                ->where('user_id', $userId)
                ->first();
        }

        $predictionSummary = [
            'total' => Prediction::where('fixture_id', $this->fixture->id)->count(),
            'home_wins' => Prediction::where('fixture_id', $this->fixture->id)
                ->whereColumn('home_score', '>', 'away_score')
                ->count(),
            'draws' => Prediction::where('fixture_id', $this->fixture->id)
                ->whereColumn('home_score', 'away_score')
                ->count(),
            'away_wins' => Prediction::where('fixture_id', $this->fixture->id)
                ->whereColumn('away_score', '>', 'home_score')
                ->count(),
        ];

        return view('livewire.fixtures.show-fixture', [
            'userPrediction' => $userPrediction,
            'predictionSummary' => $predictionSummary,
        ]);
    }
}
