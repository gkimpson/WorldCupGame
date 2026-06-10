<?php

namespace App\Enums;

enum FixtureOutcome: string
{
    case HomeWin = 'home_win';
    case Draw = 'draw';
    case AwayWin = 'away_win';

    public static function fromScores(int $homeScore, int $awayScore): self
    {
        if ($homeScore > $awayScore) {
            return self::HomeWin;
        }

        if ($awayScore > $homeScore) {
            return self::AwayWin;
        }

        return self::Draw;
    }
}
