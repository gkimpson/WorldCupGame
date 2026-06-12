<?php

use App\Data\ApiFootball\FixtureData;
use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use Carbon\CarbonImmutable;

it('holds all fixture fields as immutable value object', function (): void {
    $dto = new FixtureData(
        providerFixtureId: 1234,
        homeTeamName: 'Mexico',
        awayTeamName: 'South Africa',
        scheduledAt: CarbonImmutable::parse('2026-06-11T19:00:00Z'),
        status: FixtureStatus::Scheduled,
        stage: FixtureStage::GroupStage,
        group: null,
        venue: 'Estadio Azteca',
        city: 'Mexico City',
        homeScore: null,
        awayScore: null,
        homeScoreAet: null,
        awayScoreAet: null,
        homeScorePens: null,
        awayScorePens: null,
    );

    expect($dto->providerFixtureId)->toBe(1234)
        ->and($dto->homeTeamName)->toBe('Mexico')
        ->and($dto->status)->toBe(FixtureStatus::Scheduled)
        ->and($dto->stage)->toBe(FixtureStage::GroupStage)
        ->and($dto->homeScore)->toBeNull();
});
