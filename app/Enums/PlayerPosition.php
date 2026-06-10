<?php

namespace App\Enums;

use ValueError;

enum PlayerPosition: string
{
    case Goalkeeper = 'GK';
    case Defender = 'DEF';
    case Midfielder = 'MID';
    case Forward = 'FWD';

    /**
     * Human-readable label for the position.
     */
    public function label(): string
    {
        return match ($this) {
            self::Goalkeeper => 'Goalkeeper',
            self::Defender => 'Defender',
            self::Midfielder => 'Midfielder',
            self::Forward => 'Forward',
        };
    }

    /**
     * Map a raw BBC position label (e.g. "Goalkeeper", "Striker", "Wing-back")
     * onto one of the four broad position categories.
     *
     * @throws ValueError when the label cannot be mapped.
     */
    public static function fromBbc(string $raw): self
    {
        $value = strtolower(trim($raw));

        return match (true) {
            $value === 'gk', str_contains($value, 'keeper') => self::Goalkeeper,
            str_contains($value, 'defen'), str_contains($value, 'back') => self::Defender,
            str_contains($value, 'midfield') => self::Midfielder,
            str_contains($value, 'forward'),
            str_contains($value, 'strik'),
            str_contains($value, 'wing'),
            str_contains($value, 'attack') => self::Forward,
            default => throw new ValueError("Unknown BBC position label: {$raw}"),
        };
    }
}
