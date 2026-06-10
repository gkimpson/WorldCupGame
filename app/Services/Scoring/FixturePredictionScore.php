<?php

namespace App\Services\Scoring;

use App\Enums\FixtureOutcome;

class FixturePredictionScore
{
    public function __construct(
        public int $points,
        public bool $exactScore,
        public bool $correctOutcome,
        public FixtureOutcome $predictedOutcome,
        public ?FixtureOutcome $actualOutcome,
        public ?string $reason = null,
    ) {}

    public function isScored(): bool
    {
        return $this->reason === null;
    }
}
