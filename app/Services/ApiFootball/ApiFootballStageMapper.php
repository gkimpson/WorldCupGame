<?php

namespace App\Services\ApiFootball;

use App\Enums\FixtureStage;
use Illuminate\Support\Facades\Log;

final class ApiFootballStageMapper
{
    public function map(string $round): FixtureStage
    {
        $stage = match (true) {
            str_starts_with($round, 'Group Stage') => FixtureStage::GroupStage,
            str_contains($round, 'Round of 32') => FixtureStage::RoundOf32,
            str_contains($round, 'Round of 16') => FixtureStage::RoundOf16,
            str_contains($round, 'Quarter') => FixtureStage::QuarterFinal,
            str_contains($round, 'Semi') => FixtureStage::SemiFinal,
            str_starts_with($round, '3rd Place') => FixtureStage::ThirdPlace,
            str_contains($round, 'Final') => FixtureStage::Final,
            default => null,
        };

        if ($stage === null) {
            Log::warning('ApiFootball: unmapped stage round, defaulting to GroupStage', ['round' => $round]);

            return FixtureStage::GroupStage;
        }

        return $stage;
    }
}
