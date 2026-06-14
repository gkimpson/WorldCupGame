<?php

namespace App\Services\Results\Contracts;

use App\Models\Fixture;
use Illuminate\Support\Collection;

interface WorldCupResultsProviderInterface
{
    /**
     * @param  Collection<int, Fixture>  $fixtures
     * @return array<string, array{home_score: int|null, away_score: int|null, status: string}>
     */
    public function fetchResults(Collection $fixtures): array;

    /** @throws \RuntimeException */
    public function fetchRawResults(?string $specificDate = null): string;
}
