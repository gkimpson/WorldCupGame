<?php

namespace App\Services\Scoring;

use App\Enums\FixtureOutcome;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use InvalidArgumentException;

class FixturePredictionScorer
{
    public const ExactScorePoints = 3;

    public const CorrectOutcomePoints = 1;

    public const IncorrectPoints = 0;

    public function score(Fixture $fixture, int $predictedHomeScore, int $predictedAwayScore): FixturePredictionScore
    {
        if ($predictedHomeScore < 0 || $predictedAwayScore < 0) {
            throw new InvalidArgumentException('Predicted scores must be zero or greater.');
        }

        $predictedOutcome = FixtureOutcome::fromScores($predictedHomeScore, $predictedAwayScore);

        if ($fixture->status !== FixtureStatus::Completed) {
            return new FixturePredictionScore(
                points: self::IncorrectPoints,
                exactScore: false,
                correctOutcome: false,
                predictedOutcome: $predictedOutcome,
                actualOutcome: null,
                reason: 'fixture_not_completed',
            );
        }

        $actualHomeScore = $this->settledHomeScore($fixture);
        $actualAwayScore = $this->settledAwayScore($fixture);

        if ($actualHomeScore === null || $actualAwayScore === null) {
            return new FixturePredictionScore(
                points: self::IncorrectPoints,
                exactScore: false,
                correctOutcome: false,
                predictedOutcome: $predictedOutcome,
                actualOutcome: null,
                reason: 'missing_result',
            );
        }

        $actualOutcome = $this->actualOutcome($fixture, $actualHomeScore, $actualAwayScore);
        $exactScore = $predictedHomeScore === $actualHomeScore
            && $predictedAwayScore === $actualAwayScore;

        if ($exactScore) {
            return new FixturePredictionScore(
                points: self::ExactScorePoints,
                exactScore: true,
                correctOutcome: true,
                predictedOutcome: $predictedOutcome,
                actualOutcome: $actualOutcome,
            );
        }

        $correctOutcome = $predictedOutcome === $actualOutcome;

        return new FixturePredictionScore(
            points: $correctOutcome ? self::CorrectOutcomePoints : self::IncorrectPoints,
            exactScore: false,
            correctOutcome: $correctOutcome,
            predictedOutcome: $predictedOutcome,
            actualOutcome: $actualOutcome,
        );
    }

    private function settledHomeScore(Fixture $fixture): ?int
    {
        return $fixture->home_score_aet ?? $fixture->home_score;
    }

    private function settledAwayScore(Fixture $fixture): ?int
    {
        return $fixture->away_score_aet ?? $fixture->away_score;
    }

    private function actualOutcome(Fixture $fixture, int $homeScore, int $awayScore): FixtureOutcome
    {
        if ($homeScore !== $awayScore) {
            return FixtureOutcome::fromScores($homeScore, $awayScore);
        }

        if ($fixture->home_score_pens !== null && $fixture->away_score_pens !== null) {
            return FixtureOutcome::fromScores($fixture->home_score_pens, $fixture->away_score_pens);
        }

        return FixtureOutcome::Draw;
    }
}
