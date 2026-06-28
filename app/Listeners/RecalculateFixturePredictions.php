<?php

namespace App\Listeners;

use App\Actions\Predictions\EnsureDefaultPredictions;
use App\Events\ResultImported;
use App\Models\Prediction;
use App\Services\Scoring\FixturePredictionScorer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;

class RecalculateFixturePredictions implements ShouldQueue
{
    public function __construct(
        private readonly FixturePredictionScorer $scorer,
        private readonly EnsureDefaultPredictions $ensureDefaultPredictions,
    ) {}

    public function handle(ResultImported $event): void
    {
        $fixture = $event->fixture;

        $this->ensureDefaultPredictions->forFixture($fixture);

        Prediction::where('fixture_id', $fixture->id)
            ->chunkById(200, function (Collection $predictions) use ($fixture): void {
                foreach ($predictions as $prediction) {
                    $result = $this->scorer->score(
                        $fixture,
                        $prediction->home_score,
                        $prediction->away_score,
                        $prediction->knockout_outcome,
                    );
                    $prediction->points = $result->isScored() ? $result->points : null;
                    $prediction->save();
                }
            });
    }
}
