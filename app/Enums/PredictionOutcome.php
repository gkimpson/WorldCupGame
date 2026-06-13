<?php

namespace App\Enums;

enum PredictionOutcome: string
{
    case Exact = 'exact';
    case Correct = 'correct';
    case Incorrect = 'incorrect';
    case Pending = 'pending';

    public static function fromScores(int $predHome, int $predAway, int $actualHome, int $actualAway): self
    {
        if ($predHome === $actualHome && $predAway === $actualAway) {
            return self::Exact;
        }

        $predictedOutcome = FixtureOutcome::fromScores($predHome, $predAway);
        $actualOutcome = FixtureOutcome::fromScores($actualHome, $actualAway);

        return $predictedOutcome === $actualOutcome ? self::Correct : self::Incorrect;
    }
}
