<?php

use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use App\Enums\PredictionOutcome;
use App\Models\Fixture;
use App\Models\Prediction;

it('returns Pending when the fixture has no result', function () {
    $fixture = new Fixture;
    $fixture->forceFill([
        'stage' => FixtureStage::GroupStage,
        'status' => FixtureStatus::Scheduled,
        'home_score' => null,
        'away_score' => null,
    ]);

    $prediction = new Prediction;
    $prediction->forceFill(['home_score' => 1, 'away_score' => 0]);

    expect($prediction->outcome($fixture))->toBe(PredictionOutcome::Pending);
});
