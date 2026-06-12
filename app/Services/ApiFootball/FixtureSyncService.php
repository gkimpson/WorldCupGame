<?php

namespace App\Services\ApiFootball;

use App\Data\ApiFootball\FixtureData;
use App\Enums\FixtureStatus;
use App\Events\ResultImported;
use App\Models\Fixture;
use App\Models\Team;
use App\Services\ApiFootball\Contracts\FootballDataProviderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class FixtureSyncService
{
    /** @var Collection<string, int>|null */
    private ?Collection $teamNameIndex = null;

    public function __construct(
        private readonly FootballDataProviderInterface $provider,
    ) {}

    /**
     * @return array{synced: int, skipped: int, completed: int}
     */
    public function syncFromProvider(bool $dryRun = false): array
    {
        $league = (int) config('services.api_football.league');
        $season = (int) config('services.api_football.season');
        $fixtures = $this->provider->fetchFixtures($league, $season);

        return $this->sync($fixtures, $dryRun);
    }

    /**
     * @param  Collection<int, FixtureData>  $fixtures
     * @return array{synced: int, skipped: int, completed: int}
     */
    public function sync(Collection $fixtures, bool $dryRun = false): array
    {
        $synced = 0;
        $skipped = 0;
        $completed = 0;

        foreach ($fixtures as $data) {
            $homeTeamId = $this->resolveTeamId($data->homeTeamName);
            $awayTeamId = $this->resolveTeamId($data->awayTeamName);

            if ($homeTeamId === null || $awayTeamId === null) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $synced++;

                continue;
            }

            $existing = Fixture::where('provider_fixture_id', $data->providerFixtureId)->first();
            $wasCompleted = $existing?->status === FixtureStatus::Completed;

            $fixture = Fixture::updateOrCreate(
                ['provider_fixture_id' => $data->providerFixtureId],
                [
                    'home_team_id' => $homeTeamId,
                    'away_team_id' => $awayTeamId,
                    'scheduled_at' => $data->scheduledAt,
                    'status' => $data->status,
                    'stage' => $data->stage,
                    'group' => $data->group,
                    'venue' => $data->venue,
                    'city' => $data->city,
                    'home_score' => $data->homeScore,
                    'away_score' => $data->awayScore,
                    'home_score_aet' => $data->homeScoreAet,
                    'away_score_aet' => $data->awayScoreAet,
                    'home_score_pens' => $data->homeScorePens,
                    'away_score_pens' => $data->awayScorePens,
                ],
            );

            $synced++;

            if (! $wasCompleted && $fixture->status === FixtureStatus::Completed) {
                ResultImported::dispatch($fixture->fresh());
                $completed++;
            }
        }

        return compact('synced', 'skipped', 'completed');
    }

    private function resolveTeamId(string $name): ?int
    {
        $id = $this->getTeamNameIndex()->get($name);

        if ($id === null) {
            Log::warning('ApiFootball: team not found in local database', ['name' => $name]);

            return null;
        }

        return (int) $id;
    }

    /** @return Collection<string, int> */
    private function getTeamNameIndex(): Collection
    {
        $this->teamNameIndex ??= Team::query()->pluck('id', 'name');

        return $this->teamNameIndex;
    }
}
