<?php

namespace App\Services\ApiFootball\Contracts;

use App\Data\ApiFootball\FixtureData;
use Illuminate\Support\Collection;

interface FootballDataProviderInterface
{
    /** @return Collection<int, FixtureData> */
    public function fetchFixtures(int $league, int $season): Collection;
}
