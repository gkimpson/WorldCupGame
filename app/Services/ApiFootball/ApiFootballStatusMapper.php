<?php

namespace App\Services\ApiFootball;

use App\Enums\FixtureStatus;

final class ApiFootballStatusMapper
{
    private const MAP = [
        'NS' => FixtureStatus::Scheduled,
        'TBD' => FixtureStatus::Scheduled,
        'LIVE' => FixtureStatus::InProgress,
        'HT' => FixtureStatus::InProgress,
        '1H' => FixtureStatus::InProgress,
        '2H' => FixtureStatus::InProgress,
        'ET' => FixtureStatus::InProgress,
        'BT' => FixtureStatus::InProgress,
        'P' => FixtureStatus::InProgress,
        'SUSP' => FixtureStatus::InProgress,
        'FT' => FixtureStatus::Completed,
        'AET' => FixtureStatus::Completed,
        'PEN' => FixtureStatus::Completed,
        'PST' => FixtureStatus::Postponed,
        // CANC (Cancelled) is intentionally mapped to Postponed — our domain has no Cancelled status
        'CANC' => FixtureStatus::Postponed,
        'ABD' => FixtureStatus::Postponed,
    ];

    public function map(string $shortStatus): FixtureStatus
    {
        return self::MAP[$shortStatus] ?? FixtureStatus::Scheduled;
    }
}
