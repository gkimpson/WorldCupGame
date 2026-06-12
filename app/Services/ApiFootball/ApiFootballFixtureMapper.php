<?php

namespace App\Services\ApiFootball;

use App\Data\ApiFootball\FixtureData;
use Carbon\CarbonImmutable;

final class ApiFootballFixtureMapper
{
    public function __construct(
        private readonly ApiFootballStatusMapper $statusMapper,
        private readonly ApiFootballStageMapper $stageMapper,
    ) {}

    /** @param array<string, mixed> $item */
    public function map(array $item): FixtureData
    {
        $fixture = $item['fixture'];
        $teams = $item['teams'];
        $score = $item['score'];
        $goals = $item['goals'];

        $status = $this->statusMapper->map($fixture['status']['short']);
        $stage = $this->stageMapper->map($item['league']['round']);

        // For completed fixtures, fulltime score is definitive.
        // For in-progress fixtures, fulltime is null so goals holds the live score.
        $homeScore = $score['fulltime']['home'] ?? $goals['home'];
        $awayScore = $score['fulltime']['away'] ?? $goals['away'];

        return new FixtureData(
            providerFixtureId: (int) $fixture['id'],
            homeTeamName: $teams['home']['name'],
            homeTeamExternalId: isset($teams['home']['id']) ? (int) $teams['home']['id'] : null,
            awayTeamName: $teams['away']['name'],
            awayTeamExternalId: isset($teams['away']['id']) ? (int) $teams['away']['id'] : null,
            scheduledAt: CarbonImmutable::parse($fixture['date']),
            status: $status,
            stage: $stage,
            group: null,
            venue: $fixture['venue']['name'] ?? null,
            city: $fixture['venue']['city'] ?? null,
            homeScore: $homeScore !== null ? (int) $homeScore : null,
            awayScore: $awayScore !== null ? (int) $awayScore : null,
            homeScoreAet: isset($score['extratime']['home']) ? (int) $score['extratime']['home'] : null,
            awayScoreAet: isset($score['extratime']['away']) ? (int) $score['extratime']['away'] : null,
            homeScorePens: isset($score['penalty']['home']) ? (int) $score['penalty']['home'] : null,
            awayScorePens: isset($score['penalty']['away']) ? (int) $score['penalty']['away'] : null,
        );
    }
}
