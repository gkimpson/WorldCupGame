<?php

namespace App\Console\Commands;

use App\Enums\FixtureStatus;
use App\Events\ResultImported;
use App\Models\Fixture;
use App\Services\Results\Contracts\WorldCupResultsProviderInterface;
use Illuminate\Console\Command;
use RuntimeException;

final class SyncResultsFromGemini extends Command
{
    protected $signature = 'world-cup:sync-results-gemini
                            {--dry-run : Preview changes without persisting them}
                            {--dummy : Show raw Gemini response and fixture preview without writing to the database}
                            {--data-only : Hit Gemini directly and dump raw results, skipping all DB interaction}
                            {--specific-date= : With --data-only, restrict results to matches played on this date (YYYY-MM-DD)}';

    protected $description = 'Fetch World Cup 2026 match results from Gemini AI and update fixtures';

    public function __construct(private readonly WorldCupResultsProviderInterface $gemini)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('data-only')) {
            $specificDate = $this->option('specific-date');

            if ($specificDate !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $specificDate)) {
                $this->error('--specific-date must be in YYYY-MM-DD format (e.g. 2026-06-13).');

                return self::FAILURE;
            }

            try {
                $raw = $this->gemini->fetchRawResults($specificDate);
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->line($raw);

            return self::SUCCESS;
        }

        $fixtures = Fixture::query()
            ->with(['homeTeam', 'awayTeam'])
            ->where('scheduled_at', '<=', now())
            ->where('status', '!=', FixtureStatus::Completed->value)
            ->get();

        if ($fixtures->isEmpty()) {
            $this->info('No fixtures to sync.');

            return self::SUCCESS;
        }

        try {
            $results = $this->gemini->fetchResults($fixtures);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $dummy = (bool) $this->option('dummy');
        $dryRun = $dummy || (bool) $this->option('dry-run');

        if ($dummy) {
            $this->outputDummyReport($fixtures->all(), $results);
        }

        $synced = 0;
        $skipped = 0;
        $rows = [];

        foreach ($fixtures as $fixture) {
            $result = $results[$fixture->id] ?? null;

            $homeName = $fixture->homeTeam?->name ?? $fixture->home_team_placeholder ?? 'TBD';
            $awayName = $fixture->awayTeam?->name ?? $fixture->away_team_placeholder ?? 'TBD';

            if (
                $result === null
                || $result['status'] !== 'completed'
                || $result['home_score'] === null
                || $result['away_score'] === null
            ) {
                $skipped++;
                if (! $dummy) {
                    $rows[] = [$homeName, 'v', $awayName, $result['status'] ?? 'no result', 'skipped'];
                }

                continue;
            }

            if (! $dummy) {
                $rows[] = [
                    $homeName,
                    "{$result['home_score']}-{$result['away_score']}",
                    $awayName,
                    'completed',
                    $dryRun ? 'dry-run' : 'updated',
                ];
            }

            if ($dryRun) {
                $synced++;

                continue;
            }

            $fixture->update([
                'home_score' => $result['home_score'],
                'away_score' => $result['away_score'],
                'status' => FixtureStatus::Completed,
            ]);

            ResultImported::dispatch($fixture->fresh());
            $synced++;
        }

        if (! $dummy) {
            $this->table(['Home', 'Score', 'Away', 'Gemini Status', 'Action'], $rows);
            $this->info("Synced: {$synced}, Skipped: {$skipped}".($dryRun ? ' (dry-run)' : ''));
        }

        return self::SUCCESS;
    }

    /**
     * @param  Fixture[]  $fixtures
     * @param  array<int, array{home_score: int|null, away_score: int|null, status: string}>  $results
     */
    private function outputDummyReport(array $fixtures, array $results): void
    {
        $this->newLine();
        $this->comment('=== RAW DATA RECEIVED FROM GEMINI ===');

        $geminiRows = array_map(fn (Fixture $fixture) => [
            $fixture->id,
            $fixture->homeTeam?->name ?? $fixture->home_team_placeholder ?? 'TBD',
            $results[$fixture->id]['home_score'] ?? 'null',
            $results[$fixture->id]['away_score'] ?? 'null',
            $fixture->awayTeam?->name ?? $fixture->away_team_placeholder ?? 'TBD',
            $results[$fixture->id]['status'] ?? 'no result',
        ], $fixtures);

        $this->table(['Fixture ID', 'Home Team', 'Home Score', 'Away Score', 'Away Team', 'Status'], $geminiRows);

        $this->newLine();
        $this->comment('=== WOULD BE WRITTEN TO fixtures TABLE ===');

        $writeRows = [];

        foreach ($fixtures as $fixture) {
            $result = $results[$fixture->id] ?? null;
            $homeName = $fixture->homeTeam?->name ?? $fixture->home_team_placeholder ?? 'TBD';
            $awayName = $fixture->awayTeam?->name ?? $fixture->away_team_placeholder ?? 'TBD';

            if (
                $result === null
                || $result['status'] !== 'completed'
                || $result['home_score'] === null
                || $result['away_score'] === null
            ) {
                $writeRows[] = [$fixture->id, $homeName, $awayName, '-', '-', 'skipped — '.($result['status'] ?? 'no result')];

                continue;
            }

            $writeRows[] = [
                $fixture->id,
                $homeName,
                $awayName,
                $result['home_score'],
                $result['away_score'],
                'would update',
            ];
        }

        $this->table(['Fixture ID', 'Home', 'Away', 'home_score', 'away_score', 'Action'], $writeRows);
        $this->newLine();
    }
}
