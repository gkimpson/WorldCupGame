<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function previewFixturesResponse(): array
{
    return [
        'response' => [
            [
                'fixture' => [
                    'id' => 9001,
                    'date' => '2024-07-14T19:00:00+00:00',
                    'status' => ['short' => 'FT'],
                ],
                'league' => ['round' => 'Final'],
                'teams' => [
                    'home' => ['name' => 'Argentina'],
                    'away' => ['name' => 'France'],
                ],
                'goals' => ['home' => 2, 'away' => 1],
                'score' => [
                    'fulltime' => ['home' => 2, 'away' => 1],
                ],
            ],
        ],
    ];
}

function worldCup2026FixturesResponse(): array
{
    return [
        'response' => [
            [
                'fixture' => [
                    'id' => 1001,
                    'date' => '2026-06-11T19:00:00+00:00',
                    'status' => ['short' => 'FT'],
                ],
                'league' => ['round' => 'Group Stage - 1'],
                'teams' => [
                    'home' => ['name' => 'Mexico'],
                    'away' => ['name' => 'South Africa'],
                ],
                'goals' => ['home' => 2, 'away' => 1],
                'score' => [
                    'fulltime' => ['home' => 2, 'away' => 1],
                ],
            ],
            [
                'fixture' => [
                    'id' => 1002,
                    'date' => '2026-07-04T20:00:00+00:00',
                    'status' => ['short' => 'AET'],
                ],
                'league' => ['round' => 'Round of 16'],
                'teams' => [
                    'home' => ['name' => 'Brazil'],
                    'away' => ['name' => 'Germany'],
                ],
                'goals' => ['home' => 1, 'away' => 1],
                'score' => [
                    'fulltime' => ['home' => 1, 'away' => 1],
                    'extratime' => ['home' => 2, 'away' => 1],
                ],
            ],
            [
                'fixture' => [
                    'id' => 1003,
                    'date' => '2026-07-05T20:00:00+00:00',
                    'status' => ['short' => 'NS'],
                ],
                'league' => ['round' => 'Round of 16'],
                'teams' => [
                    'home' => ['name' => 'France'],
                    'away' => ['name' => 'Spain'],
                ],
                'goals' => ['home' => null, 'away' => null],
                'score' => [
                    'fulltime' => ['home' => null, 'away' => null],
                ],
            ],
        ],
    ];
}

beforeEach(function (): void {
    config()->set('services.api_football.key', 'test-api-football-key');
    config()->set('services.api_football.base_url', 'https://api-football.test');
});

it('previews completed fixture results from one API-Football request', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'api-football.test/fixtures*' => Http::response(previewFixturesResponse(), 200),
    ]);

    $this->artisan('api-football:preview-fixtures --league=1 --season=2024 --limit=1')
        ->expectsOutput('Requesting API-Football fixtures for league 1, season 2024, status FT...')
        ->expectsTable(
            ['Date', 'Stage', 'Home', 'Score', 'Away', 'Status'],
            [['2024-07-14 19:00', 'Final', 'Argentina', '2-1', 'France', 'FT']],
        )
        ->expectsOutput('Displayed 1 of 1 fixtures from one API request. Nothing was written to the database.')
        ->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        $query = [];
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_starts_with($request->url(), 'https://api-football.test/fixtures')
            && $request->hasHeader('x-apisports-key', 'test-api-football-key')
            && $query === [
                'league' => '1',
                'season' => '2024',
                'status' => 'FT',
            ];
    });
    Http::assertSentCount(1);
});

it('does not send a request when the API key is missing', function (): void {
    config()->set('services.api_football.key', null);
    Http::fake();

    $this->artisan('api-football:preview-fixtures --season=2024')
        ->expectsOutput('API_FOOTBALL_KEY is not configured.')
        ->assertFailed();

    Http::assertNothingSent();
});

it('previews World Cup 2026 completed results from one season request', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'api-football.test/fixtures*' => Http::response(worldCup2026FixturesResponse(), 200),
    ]);

    $this->artisan('api-football:preview-fixtures --league=1 --season=2026 --date=2026-06-11 --completed --limit=10')
        ->expectsOutput('Requesting API-Football fixtures for league 1, season 2026, date 2026-06-11, then filtering completed results...')
        ->expectsTable(
            ['Date', 'Stage', 'Home', 'Score', 'Away', 'Status'],
            [
                ['2026-06-11 19:00', 'Group Stage - 1', 'Mexico', '2-1', 'South Africa', 'FT'],
                ['2026-07-04 20:00', 'Round of 16', 'Brazil', '2-1 AET', 'Germany', 'AET'],
            ],
        )
        ->expectsOutput('Displayed 2 of 2 fixtures from one API request. Nothing was written to the database.')
        ->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        $query = [];
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_starts_with($request->url(), 'https://api-football.test/fixtures')
            && $request->hasHeader('x-apisports-key', 'test-api-football-key')
            && $query === [
                'league' => '1',
                'season' => '2026',
                'date' => '2026-06-11',
            ];
    });
    Http::assertSentCount(1);
});

it('falls back to verified World Cup 2026 opening day results when API-Football has no fixtures', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'api-football.test/fixtures*' => Http::response(['response' => []], 200),
    ]);

    $this->artisan('api-football:preview-fixtures --league=1 --season=2026 --date=2026-06-11 --completed --limit=10')
        ->expectsOutput('Requesting API-Football fixtures for league 1, season 2026, date 2026-06-11, then filtering completed results...')
        ->expectsOutput('API-Football returned no fixtures; displaying verified fallback World Cup results.')
        ->expectsTable(
            ['Date', 'Stage', 'Home', 'Score', 'Away', 'Status'],
            [
                ['2026-06-11 19:00', 'Group Stage - 1', 'Mexico', '2-0', 'South Africa', 'FT'],
                ['2026-06-12 02:00', 'Group Stage - 1', 'South Korea', '2-1', 'Czechia', 'FT'],
            ],
        )
        ->expectsOutput('Displayed 2 of 2 fixtures from one API request. Nothing was written to the database.')
        ->assertSuccessful();

    Http::assertSentCount(1);
});
