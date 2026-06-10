<?php

use App\Enums\FixtureOutcome;
use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Services\Scoring\FixturePredictionScorer;

function scoringFixture(array $attributes = []): Fixture
{
    $fixture = new Fixture;

    $fixture->forceFill([
        'stage' => FixtureStage::GroupStage,
        'status' => FixtureStatus::Completed,
        'home_score' => 2,
        'away_score' => 1,
        'home_score_aet' => null,
        'away_score_aet' => null,
        'home_score_pens' => null,
        'away_score_pens' => null,
        ...$attributes,
    ]);

    return $fixture;
}

it('awards three points for an exact settled score', function () {
    $score = (new FixturePredictionScorer)->score(scoringFixture(), 2, 1);

    expect($score->points)->toBe(3)
        ->and($score->exactScore)->toBeTrue()
        ->and($score->correctOutcome)->toBeTrue()
        ->and($score->predictedOutcome)->toBe(FixtureOutcome::HomeWin)
        ->and($score->actualOutcome)->toBe(FixtureOutcome::HomeWin)
        ->and($score->isScored())->toBeTrue();
});

it('awards one point for the correct outcome without the exact score', function () {
    $score = (new FixturePredictionScorer)->score(scoringFixture(), 1, 0);

    expect($score->points)->toBe(1)
        ->and($score->exactScore)->toBeFalse()
        ->and($score->correctOutcome)->toBeTrue();
});

it('awards no points for an incorrect outcome', function () {
    $score = (new FixturePredictionScorer)->score(scoringFixture(), 0, 1);

    expect($score->points)->toBe(0)
        ->and($score->exactScore)->toBeFalse()
        ->and($score->correctOutcome)->toBeFalse()
        ->and($score->predictedOutcome)->toBe(FixtureOutcome::AwayWin)
        ->and($score->actualOutcome)->toBe(FixtureOutcome::HomeWin);
});

it('scores draws as an outcome', function () {
    $score = (new FixturePredictionScorer)->score(scoringFixture([
        'home_score' => 1,
        'away_score' => 1,
    ]), 2, 2);

    expect($score->points)->toBe(1)
        ->and($score->correctOutcome)->toBeTrue()
        ->and($score->actualOutcome)->toBe(FixtureOutcome::Draw);
});

it('uses extra-time scores as the settled score', function () {
    $score = (new FixturePredictionScorer)->score(scoringFixture([
        'stage' => FixtureStage::RoundOf16,
        'home_score' => 1,
        'away_score' => 1,
        'home_score_aet' => 2,
        'away_score_aet' => 1,
    ]), 2, 1);

    expect($score->points)->toBe(3)
        ->and($score->exactScore)->toBeTrue()
        ->and($score->actualOutcome)->toBe(FixtureOutcome::HomeWin);
});

it('uses penalties to determine the winner when the settled score is tied', function () {
    $score = (new FixturePredictionScorer)->score(scoringFixture([
        'stage' => FixtureStage::Final,
        'home_score' => 1,
        'away_score' => 1,
        'home_score_aet' => 1,
        'away_score_aet' => 1,
        'home_score_pens' => 5,
        'away_score_pens' => 4,
    ]), 2, 1);

    expect($score->points)->toBe(1)
        ->and($score->exactScore)->toBeFalse()
        ->and($score->correctOutcome)->toBeTrue()
        ->and($score->actualOutcome)->toBe(FixtureOutcome::HomeWin);
});

it('does not score fixtures that are not completed', function () {
    $score = (new FixturePredictionScorer)->score(scoringFixture([
        'status' => FixtureStatus::Scheduled,
    ]), 2, 1);

    expect($score->points)->toBe(0)
        ->and($score->isScored())->toBeFalse()
        ->and($score->reason)->toBe('fixture_not_completed')
        ->and($score->actualOutcome)->toBeNull();
});

it('does not score completed fixtures with missing result data', function () {
    $score = (new FixturePredictionScorer)->score(scoringFixture([
        'home_score' => null,
        'away_score' => null,
    ]), 2, 1);

    expect($score->points)->toBe(0)
        ->and($score->isScored())->toBeFalse()
        ->and($score->reason)->toBe('missing_result');
});

it('rejects negative predicted scores', function () {
    (new FixturePredictionScorer)->score(scoringFixture(), -1, 0);
})->throws(InvalidArgumentException::class, 'Predicted scores must be zero or greater.');
