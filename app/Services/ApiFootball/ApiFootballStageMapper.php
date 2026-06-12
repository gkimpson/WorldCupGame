<?php

namespace App\Services\ApiFootball;

use App\Enums\FixtureStage;

class ApiFootballStageMapper
{
    public function map(string $round): FixtureStage
    {
        return match (true) {
            str_starts_with($round, 'Group Stage') => FixtureStage::GroupStage,
            str_contains($round, 'Round of 32') => FixtureStage::RoundOf32,
            str_contains($round, 'Round of 16') => FixtureStage::RoundOf16,
            str_contains($round, 'Quarter') => FixtureStage::QuarterFinal,
            str_contains($round, 'Semi') => FixtureStage::SemiFinal,
            str_contains($round, '3rd') => FixtureStage::ThirdPlace,
            str_contains($round, 'Final') => FixtureStage::Final,
            default => FixtureStage::GroupStage,
        };
    }
}
