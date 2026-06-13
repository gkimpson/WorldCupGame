<?php

namespace App\Livewire\Users;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class PredictionHeatmap extends Component
{
    public User $user;

    public bool $compact = false;

    public function render(): View
    {
        $predictions = Prediction::where('user_id', $this->user->id)
            ->whereNotNull('points')
            ->get()
            ->keyBy('fixture_id');

        $outcomeGrid = Fixture::with(['homeTeam', 'awayTeam'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Fixture $fixture) => [
                'fixture' => $fixture,
                'prediction' => $predictions->get($fixture->id),
                'result' => $this->resolveResult($predictions->get($fixture->id)),
            ]);

        $scoreGrid = null;
        if (! $this->compact) {
            $scoreGrid = $this->buildScoreGrid($predictions->values());
        }

        return view('livewire.users.prediction-heatmap', [
            'outcomeGrid' => $outcomeGrid,
            'scoreGrid' => $scoreGrid,
            'compact' => $this->compact,
        ]);
    }

    private function resolveResult(?Prediction $prediction): string
    {
        if ($prediction === null) {
            return 'none';
        }

        if ($prediction->points >= 3) {
            return 'exact';
        }

        if ($prediction->points >= 1) {
            return 'correct';
        }

        return 'wrong';
    }

    /**
     * @param  Collection<int, Prediction>  $scored
     * @return array{maxHome: int, maxAway: int, cells: array<int, array<int, array{count: int, correct: int, exact: int}>>}|null
     */
    private function buildScoreGrid(Collection $scored): ?array
    {
        if ($scored->isEmpty()) {
            return null;
        }

        $maxHome = max(3, $scored->max('home_score') ?? 0);
        $maxAway = max(3, $scored->max('away_score') ?? 0);

        // Accumulate raw counts keyed by score pair
        $cells = [];
        for ($h = 0; $h <= $maxHome; $h++) {
            for ($a = 0; $a <= $maxAway; $a++) {
                $cells[$h][$a] = ['count' => 0, 'correct' => 0, 'exact' => 0];
            }
        }

        foreach ($scored as $prediction) {
            $h = $prediction->home_score;
            $a = $prediction->away_score;
            $cell = $cells[$h][$a] ?? ['count' => 0, 'correct' => 0, 'exact' => 0];

            $cells[$h][$a] = [
                'count' => $cell['count'] + 1,
                'correct' => $cell['correct'] + ($prediction->points > 0 ? 1 : 0),
                'exact' => $cell['exact'] + ($prediction->points >= 3 ? 1 : 0),
            ];
        }

        return [
            'maxHome' => $maxHome,
            'maxAway' => $maxAway,
            'cells' => $cells,
        ];
    }
}
