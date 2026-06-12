<?php

use App\Data\ApiFootball\FixtureData;
use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use App\Events\ResultImported;
use App\Models\Fixture;
use App\Models\Team;
use App\Services\ApiFootball\Contracts\FootballDataProviderInterface;
use App\Services\ApiFootball\FixtureSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;

function mockProvider(array $fixtures): FootballDataProviderInterface
{
    $collection = collect($fixtures);

    return new class($collection) implements FootballDataProviderInterface
    {
        public function __construct(private readonly Collection $fixtures) {}

        public function fetchFixtures(int $league, int $season): Collection
        {
            return $this->fixtures;
        }
    };
}

function scheduledFixtureData(array $overrides = []): FixtureData
{
    return new FixtureData(
        providerFixtureId: $overrides['providerFixtureId'] ?? 1001,
        homeTeamName: $overrides['homeTeamName'] ?? 'Mexico',
        homeTeamExternalId: 10,
        awayTeamName: $overrides['awayTeamName'] ?? 'South Africa',
        awayTeamExternalId: 11,
        scheduledAt: CarbonImmutable::parse('2026-06-11T19:00:00Z'),
        status: $overrides['status'] ?? FixtureStatus::Scheduled,
        stage: FixtureStage::GroupStage,
        group: null,
        venue: $overrides['venue'] ?? 'Estadio Azteca',
        city: 'Mexico City',
        homeScore: $overrides['homeScore'] ?? null,
        awayScore: $overrides['awayScore'] ?? null,
        homeScoreAet: null,
        awayScoreAet: null,
        homeScorePens: null,
        awayScorePens: null,
    );
}

it('creates a fixture for two known teams', function (): void {
    Team::factory()->create(['name' => 'Mexico']);
    Team::factory()->create(['name' => 'South Africa']);

    $service = new FixtureSyncService(mockProvider([scheduledFixtureData()]));
    $result = $service->syncFromProvider();

    expect($result['synced'])->toBe(1)
        ->and($result['skipped'])->toBe(0)
        ->and(Fixture::where('provider_fixture_id', 1001)->exists())->toBeTrue();
});

it('skips a fixture when a team is not found in the database', function (): void {
    Team::factory()->create(['name' => 'Mexico']);
    // South Africa not seeded

    $service = new FixtureSyncService(mockProvider([scheduledFixtureData()]));
    $result = $service->syncFromProvider();

    expect($result['skipped'])->toBe(1)
        ->and(Fixture::where('provider_fixture_id', 1001)->exists())->toBeFalse();
});

it('upserts an existing fixture without duplicating', function (): void {
    Team::factory()->create(['name' => 'Mexico']);
    Team::factory()->create(['name' => 'South Africa']);

    $service = new FixtureSyncService(mockProvider([scheduledFixtureData()]));
    $service->syncFromProvider();

    $service2 = new FixtureSyncService(mockProvider([scheduledFixtureData(['venue' => 'New Stadium'])]));
    $service2->syncFromProvider();

    expect(Fixture::where('provider_fixture_id', 1001)->count())->toBe(1)
        ->and(Fixture::where('provider_fixture_id', 1001)->value('venue'))->toBe('New Stadium');
});

it('fires ResultImported when a fixture transitions to completed', function (): void {
    Event::fake([ResultImported::class]);

    Team::factory()->create(['name' => 'Mexico']);
    Team::factory()->create(['name' => 'South Africa']);

    $service = new FixtureSyncService(mockProvider([scheduledFixtureData()]));
    $service->syncFromProvider();
    Event::assertNotDispatched(ResultImported::class);

    $service2 = new FixtureSyncService(mockProvider([
        scheduledFixtureData(['status' => FixtureStatus::Completed, 'homeScore' => 2, 'awayScore' => 1]),
    ]));
    $service2->syncFromProvider();

    Event::assertDispatched(ResultImported::class);
});

it('does not fire ResultImported again when fixture is already completed', function (): void {
    Event::fake([ResultImported::class]);

    Team::factory()->create(['name' => 'Mexico']);
    Team::factory()->create(['name' => 'South Africa']);

    $completedData = scheduledFixtureData(['status' => FixtureStatus::Completed, 'homeScore' => 2, 'awayScore' => 1]);

    (new FixtureSyncService(mockProvider([$completedData])))->syncFromProvider();
    Event::assertDispatchedTimes(ResultImported::class, 1);

    Event::fake([ResultImported::class]);

    (new FixtureSyncService(mockProvider([$completedData])))->syncFromProvider();
    Event::assertNotDispatched(ResultImported::class);
});

it('does not persist in dry-run mode', function (): void {
    Team::factory()->create(['name' => 'Mexico']);
    Team::factory()->create(['name' => 'South Africa']);

    $service = new FixtureSyncService(mockProvider([scheduledFixtureData()]));
    $result = $service->syncFromProvider(dryRun: true);

    expect($result['synced'])->toBe(1)
        ->and(Fixture::where('provider_fixture_id', 1001)->exists())->toBeFalse();
});
