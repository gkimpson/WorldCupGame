<?php

namespace App\Listeners;

use App\Events\ResultImported;
use App\Models\Prediction;
use App\Services\Scoring\FixturePredictionScorer;
use Illuminate\Database\Eloquent\Collection;

class RecalculateFixturePredictions
{
    public function __construct(private readonly FixturePredictionScorer $scorer) {}

    public function handle(ResultImported $event): void
    {
        $fixture = $event->fixture;

        Prediction::where('fixture_id', $fixture->id)
            ->chunkById(200, function (Collection $predictions) use ($fixture): void {
                foreach ($predictions as $prediction) {
                    $result = $this->scorer->score($fixture, $prediction->home_score, $prediction->away_score);
                    $prediction->points = $result->isScored() ? $result->points : null;
                    $prediction->save();
                }
            });
    }
}
