<?php

use App\Enums\PredictionOutcome;

it('returns Exact when predicted scores match actual scores exactly', function () {
    expect(PredictionOutcome::fromScores(2, 1, 2, 1))->toBe(PredictionOutcome::Exact);
});

it('returns Exact for a nil-nil draw predicted exactly', function () {
    expect(PredictionOutcome::fromScores(0, 0, 0, 0))->toBe(PredictionOutcome::Exact);
});

it('returns Correct for a home win predicted with wrong scores', function () {
    expect(PredictionOutcome::fromScores(2, 0, 3, 1))->toBe(PredictionOutcome::Correct);
});

it('returns Correct for an away win predicted with wrong scores', function () {
    expect(PredictionOutcome::fromScores(0, 1, 0, 2))->toBe(PredictionOutcome::Correct);
});

it('returns Correct for a draw predicted with wrong scores', function () {
    expect(PredictionOutcome::fromScores(1, 1, 2, 2))->toBe(PredictionOutcome::Correct);
});

it('returns Incorrect when predicted home win but actual away win', function () {
    expect(PredictionOutcome::fromScores(2, 1, 0, 1))->toBe(PredictionOutcome::Incorrect);
});

it('returns Incorrect when predicted draw but actual home win', function () {
    expect(PredictionOutcome::fromScores(1, 1, 2, 0))->toBe(PredictionOutcome::Incorrect);
});

it('returns Incorrect when predicted away win but actual draw', function () {
    expect(PredictionOutcome::fromScores(0, 2, 1, 1))->toBe(PredictionOutcome::Incorrect);
});
