<?php

use App\Data\ApiFootball\FixtureData;
use App\Services\ApiFootball\ApiFootballClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function fakeFixtureResponse(): array
{
    return [
        'response' => [
            [
                'fixture' => [
                    'id' => 1234,
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

it('fetches fixtures and returns a collection of FixtureData', function (): void {
    Http::fake([
        'v3.football.api-sports.io/fixtures*' => Http::response(fakeFixtureResponse(), 200),
    ]);

    $result = app(ApiFootballClient::class)->fetchFixtures(1, 2026);

    expect($result)->toHaveCount(1)
        ->and($result->first())->toBeInstanceOf(FixtureData::class)
        ->and($result->first()->providerFixtureId)->toBe(1234);
});

it('throws on HTTP 401 response', function (): void {
    Http::fake([
        'v3.football.api-sports.io/fixtures*' => Http::response([], 401),
    ]);

    expect(fn () => app(ApiFootballClient::class)->fetchFixtures(1, 2026))
        ->toThrow(RequestException::class);
});
