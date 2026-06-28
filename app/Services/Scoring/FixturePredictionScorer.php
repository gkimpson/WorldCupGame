<?php

namespace App\Services\Scoring;

use App\Enums\FixtureOutcome;
use App\Enums\FixtureStatus;
use App\Enums\KnockoutOutcome;
use App\Models\Fixture;
use InvalidArgumentException;

class FixturePredictionScorer
{
    public const ExactScorePoints = 3;

    public const CorrectOutcomePoints = 1;

    public const IncorrectPoints = 0;

    public function score(
        Fixture $fixture,
        int $predictedHomeScore,
        int $predictedAwayScore,
        ?KnockoutOutcome $predictedKnockoutOutcome = null,
    ): FixturePredictionScore {
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

        if ($fixture->home_score === null || $fixture->away_score === null) {
            return new FixturePredictionScore(
                points: self::IncorrectPoints,
                exactScore: false,
                correctOutcome: false,
                predictedOutcome: $predictedOutcome,
                actualOutcome: null,
                reason: 'missing_result',
            );
        }

        $actualHomeScore = $this->settledHomeScore($fixture);
        $actualAwayScore = $this->settledAwayScore($fixture);

        $actualOutcome = $this->actualOutcome($fixture, $actualHomeScore, $actualAwayScore);

        $exactScore = $predictedHomeScore === $fixture->home_score
            && $predictedAwayScore === $fixture->away_score;

        if ($exactScore) {
            $points = self::ExactScorePoints;
            $knockoutWinnerCorrect = false;
            $knockoutMethodCorrect = false;

            if ($fixture->stage->isKnockout() && $predictedKnockoutOutcome !== null) {
                try {
                    $actualKnockoutOutcome = KnockoutOutcome::fromFixture($fixture);

                    if ($predictedKnockoutOutcome->winner() === $actualKnockoutOutcome->winner()) {
                        $points += 1;
                        $knockoutWinnerCorrect = true;
                    }

                    if ($predictedKnockoutOutcome->method() === $actualKnockoutOutcome->method()) {
                        $points += 1;
                        $knockoutMethodCorrect = true;
                    }
                } catch (\RuntimeException) {
                    $knockoutWinnerCorrect = false;
                    $knockoutMethodCorrect = false;
                }
            }

            return new FixturePredictionScore(
                points: $points,
                exactScore: true,
                correctOutcome: true,
                predictedOutcome: $predictedOutcome,
                actualOutcome: $actualOutcome,
                knockoutMethodCorrect: $knockoutMethodCorrect,
            );
        }

        $correctOutcome = $predictedOutcome === $actualOutcome;
        $points = self::IncorrectPoints;
        $knockoutWinnerCorrect = false;
        $knockoutMethodCorrect = false;

        if ($correctOutcome) {
            $points = self::CorrectOutcomePoints;

            if ($fixture->stage->isKnockout() && $predictedKnockoutOutcome !== null) {
                try {
                    $actualKnockoutOutcome = KnockoutOutcome::fromFixture($fixture);

                    if ($predictedKnockoutOutcome->method() === $actualKnockoutOutcome->method()) {
                        $points += 1;
                        $knockoutMethodCorrect = true;
                    }
                } catch (\RuntimeException) {
                    $knockoutMethodCorrect = false;
                }
            }
        }

        return new FixturePredictionScore(
            points: $points,
            exactScore: false,
            correctOutcome: $correctOutcome,
            predictedOutcome: $predictedOutcome,
            actualOutcome: $actualOutcome,
            knockoutMethodCorrect: $knockoutMethodCorrect,
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
