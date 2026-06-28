<?php

namespace App\Enums;

use App\Models\Fixture;

enum KnockoutOutcome: string
{
    case HomeWin = 'home_win';
    case AwayWin = 'away_win';
    case HomeWinAet = 'home_win_aet';
    case AwayWinAet = 'away_win_aet';
    case HomeWinPens = 'home_win_pens';
    case AwayWinPens = 'away_win_pens';

    public function winner(): string
    {
        return match ($this) {
            self::HomeWin, self::HomeWinAet, self::HomeWinPens => 'home',
            self::AwayWin, self::AwayWinAet, self::AwayWinPens => 'away',
        };
    }

    public function method(): string
    {
        return match ($this) {
            self::HomeWin, self::AwayWin => 'normal',
            self::HomeWinAet, self::AwayWinAet => 'aet',
            self::HomeWinPens, self::AwayWinPens => 'pens',
        };
    }

    public function isDrawAt90(): bool
    {
        return in_array($this, [
            self::HomeWinAet,
            self::AwayWinAet,
            self::HomeWinPens,
            self::AwayWinPens,
        ], true);
    }

    public static function fromFixture(Fixture $fixture): self
    {
        if ($fixture->home_score === null || $fixture->away_score === null) {
            throw new \RuntimeException('Fixture missing 90-minute scores.');
        }

        $homeScore = $fixture->home_score;
        $awayScore = $fixture->away_score;

        if ($homeScore !== $awayScore) {
            return $homeScore > $awayScore ? self::HomeWin : self::AwayWin;
        }

        if ($fixture->home_score_aet !== null && $fixture->away_score_aet !== null) {
            $homeAet = $fixture->home_score_aet;
            $awayAet = $fixture->away_score_aet;

            if ($homeAet !== $awayAet) {
                return $homeAet > $awayAet ? self::HomeWinAet : self::AwayWinAet;
            }
        }

        if ($fixture->home_score_pens !== null && $fixture->away_score_pens !== null) {
            $homePens = $fixture->home_score_pens;
            $awayPens = $fixture->away_score_pens;

            if ($homePens !== $awayPens) {
                return $homePens > $awayPens ? self::HomeWinPens : self::AwayWinPens;
            }

            throw new \RuntimeException('Fixture has equal penalty scores, which is impossible.');
        }

        throw new \RuntimeException('Fixture result is incomplete: no AET or pens scores after a 90-minute draw.');
    }
}
