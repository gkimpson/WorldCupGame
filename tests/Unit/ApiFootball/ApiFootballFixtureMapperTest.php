<?php

use App\Data\ApiFootball\FixtureData;
use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use App\Services\ApiFootball\ApiFootballFixtureMapper;
use App\Services\ApiFootball\ApiFootballStageMapper;
use App\Services\ApiFootball\ApiFootballStatusMapper;

function makeMapper(): ApiFootballFixtureMapper
{
    return new ApiFootballFixtureMapper(new ApiFootballStatusMapper, new ApiFootballStageMapper);
}

function sampleItem(array $overrides = []): array
{
    return array_replace_recursive([
        'fixture' => [
            'id' => 1234,
            'date' => '2026-06-11T19:00:00+00:00',
            'status' => ['short' => 'FT'],
            'venue' => ['name' => 'Estadio Azteca', 'city' => 'Mexico City'],
        ],
        'league' => ['round' => 'Group Stage - 1'],
        'teams' => [
            'home' => ['id' => 10, 'name' => 'Mexico'],
            'away' => ['id' => 11, 'name' => 'South Africa'],
        ],
        'goals' => ['home' => 2, 'away' => 1],
        'score' => [
            'fulltime' => ['home' => 2, 'away' => 1],
            'extratime' => ['home' => null, 'away' => null],
            'penalty' => ['home' => null, 'away' => null],
        ],
    ], $overrides);
}

it('maps a completed fixture to FixtureData', function (): void {
    $dto = makeMapper()->map(sampleItem());

    expect($dto)->toBeInstanceOf(FixtureData::class)
        ->and($dto->providerFixtureId)->toBe(1234)
        ->and($dto->homeTeamName)->toBe('Mexico')
        ->and($dto->homeTeamExternalId)->toBe(10)
        ->and($dto->awayTeamName)->toBe('South Africa')
        ->and($dto->awayTeamExternalId)->toBe(11)
        ->and($dto->status)->toBe(FixtureStatus::Completed)
        ->and($dto->stage)->toBe(FixtureStage::GroupStage)
        ->and($dto->homeScore)->toBe(2)
        ->and($dto->awayScore)->toBe(1)
        ->and($dto->venue)->toBe('Estadio Azteca')
        ->and($dto->city)->toBe('Mexico City');
});

it('maps AET scores', function (): void {
    $dto = makeMapper()->map(sampleItem([
        'fixture' => ['status' => ['short' => 'AET']],
        'score' => [
            'fulltime' => ['home' => 1, 'away' => 1],
            'extratime' => ['home' => 2, 'away' => 1],
            'penalty' => ['home' => null, 'away' => null],
        ],
    ]));

    expect($dto->homeScore)->toBe(1)
        ->and($dto->homeScoreAet)->toBe(2)
        ->and($dto->awayScoreAet)->toBe(1);
});

it('maps penalty scores', function (): void {
    $dto = makeMapper()->map(sampleItem([
        'fixture' => ['status' => ['short' => 'PEN']],
        'score' => [
            'fulltime' => ['home' => 1, 'away' => 1],
            'extratime' => ['home' => 1, 'away' => 1],
            'penalty' => ['home' => 4, 'away' => 3],
        ],
    ]));

    expect($dto->homeScorePens)->toBe(4)
        ->and($dto->awayScorePens)->toBe(3);
});

it('maps null scores for a scheduled fixture', function (): void {
    $dto = makeMapper()->map(sampleItem([
        'fixture' => ['status' => ['short' => 'NS']],
        'goals' => ['home' => null, 'away' => null],
        'score' => [
            'fulltime' => ['home' => null, 'away' => null],
            'extratime' => ['home' => null, 'away' => null],
            'penalty' => ['home' => null, 'away' => null],
        ],
    ]));

    expect($dto->status)->toBe(FixtureStatus::Scheduled)
        ->and($dto->homeScore)->toBeNull()
        ->and($dto->awayScore)->toBeNull();
});
