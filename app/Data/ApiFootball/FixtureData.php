<?php

namespace App\Data\ApiFootball;

use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use Carbon\CarbonImmutable;

final readonly class FixtureData
{
    public function __construct(
        public int $providerFixtureId,
        public string $homeTeamName,
        public string $awayTeamName,
        public CarbonImmutable $scheduledAt,
        public FixtureStatus $status,
        public FixtureStage $stage,
        public ?string $group,
        public ?string $venue,
        public ?string $city,
        public ?int $homeScore,
        public ?int $awayScore,
        public ?int $homeScoreAet,
        public ?int $awayScoreAet,
        public ?int $homeScorePens,
        public ?int $awayScorePens,
    ) {}
}
