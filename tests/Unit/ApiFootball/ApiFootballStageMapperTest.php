<?php

use App\Enums\FixtureStage;
use App\Services\ApiFootball\ApiFootballStageMapper;

it('maps group stage rounds', function (): void {
    expect((new ApiFootballStageMapper)->map('Group Stage - 1'))->toBe(FixtureStage::GroupStage);
});

it('maps knockout rounds', function (string $round, FixtureStage $expected): void {
    expect((new ApiFootballStageMapper)->map($round))->toBe($expected);
})->with([
    ['Round of 32', FixtureStage::RoundOf32],
    ['Round of 16', FixtureStage::RoundOf16],
    ['Quarter-finals', FixtureStage::QuarterFinal],
    ['Semi-finals', FixtureStage::SemiFinal],
    ['3rd Place Final', FixtureStage::ThirdPlace],
    ['Final', FixtureStage::Final],
]);
