<?php

namespace App\Listeners;

use App\Events\ResultImported;
use App\Models\Prediction;
use App\Services\Scoring\FixturePredictionScorer;

class RecalculateFixturePredictions
{
    public function handle(ResultImported $event): void
    {
        $fixture = $event->fixture;
        $scorer = new FixturePredictionScorer;

        Prediction::where('fixture_id', $fixture->id)
            ->each(function (Prediction $prediction) use ($fixture, $scorer): void {
                $result = $scorer->score($fixture, $prediction->home_score, $prediction->away_score);
                $prediction->points = $result->isScored() ? $result->points : null;
                $prediction->save();
            });
    }
}
