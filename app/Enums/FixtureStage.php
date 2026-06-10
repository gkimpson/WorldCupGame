<?php

namespace App\Enums;

enum FixtureStage: string
{
    case GroupStage = 'group_stage';
    case RoundOf32 = 'round_of_32';
    case RoundOf16 = 'round_of_16';
    case QuarterFinal = 'quarter_final';
    case SemiFinal = 'semi_final';
    case ThirdPlace = 'third_place';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::GroupStage => 'Group Stage',
            self::RoundOf32 => 'Round of 32',
            self::RoundOf16 => 'Round of 16',
            self::QuarterFinal => 'Quarter-Final',
            self::SemiFinal => 'Semi-Final',
            self::ThirdPlace => 'Third Place Play-off',
            self::Final => 'Final',
        };
    }

    public function isKnockout(): bool
    {
        return $this !== self::GroupStage;
    }
}
