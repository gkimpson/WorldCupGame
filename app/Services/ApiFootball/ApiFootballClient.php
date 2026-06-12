<?php

namespace App\Services\ApiFootball;

use App\Data\ApiFootball\FixtureData;
use App\Services\ApiFootball\Contracts\FootballDataProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

final class ApiFootballClient implements FootballDataProviderInterface
{
    public function __construct(
        private readonly ApiFootballFixtureMapper $mapper,
    ) {}

    /** @return Collection<int, FixtureData> */
    public function fetchFixtures(int $league, int $season): Collection
    {
        $response = $this->request()->get('/fixtures', [
            'league' => $league,
            'season' => $season,
        ]);

        $response->throw();

        return collect((array) $response->json('response', []))
            ->map(fn (array $item): FixtureData => $this->mapper->map($item));
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.api_football.base_url'))
            ->withHeader('x-apisports-key', config('services.api_football.key'))
            ->acceptJson();
    }
}
