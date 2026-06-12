<?php

use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Support\Facades\Http;

function fakeApiResponse(int $fixtureId = 1234): array
{
    return [
        'response' => [
            [
                'fixture' => [
                    'id' => $fixtureId,
                    'date' => '2026-06-11T19:00:00+00:00',
                    'status' => ['short' => 'NS'],
                    'venue' => ['name' => 'Estadio Azteca', 'city' => 'Mexico City'],
                ],
                'league' => ['round' => 'Group Stage - 1'],
                'teams' => [
                    'home' => ['id' => 10, 'name' => 'Mexico'],
                    'away' => ['id' => 11, 'name' => 'South Africa'],
                ],
                'goals' => ['home' => null, 'away' => null],
                'score' => [
                    'fulltime' => ['home' => null, 'away' => null],
                    'extratime' => ['home' => null, 'away' => null],
                    'penalty' => ['home' => null, 'away' => null],
                ],
            ],
        ],
    ];
}

it('syncs fixtures successfully', function (): void {
    Team::factory()->create(['name' => 'Mexico']);
    Team::factory()->create(['name' => 'South Africa']);

    Http::fake([
        'v3.football.api-sports.io/fixtures*' => Http::response(fakeApiResponse(), 200),
    ]);

    $this->artisan('world-cup:sync-fixtures')
        ->assertSuccessful()
        ->expectsOutputToContain('Synced');

    expect(Fixture::where('provider_fixture_id', 1234)->exists())->toBeTrue();
});

it('does not write to db in dry-run mode', function (): void {
    Team::factory()->create(['name' => 'Mexico']);
    Team::factory()->create(['name' => 'South Africa']);

    Http::fake([
        'v3.football.api-sports.io/fixtures*' => Http::response(fakeApiResponse(5555), 200),
    ]);

    $this->artisan('world-cup:sync-fixtures --dry-run')
        ->assertSuccessful()
        ->expectsOutputToContain('Dry-run');

    expect(Fixture::where('provider_fixture_id', 5555)->exists())->toBeFalse();
});

it('returns failure when the API call fails', function (): void {
    Http::fake([
        'v3.football.api-sports.io/fixtures*' => Http::response([], 401),
    ]);

    $this->artisan('world-cup:sync-fixtures')
        ->assertFailed()
        ->expectsOutputToContain('Sync failed');
});
