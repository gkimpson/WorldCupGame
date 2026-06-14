<?php

namespace App\Console\Commands;

use App\Enums\FixtureStatus;
use App\Events\ResultImported;
use App\Models\Fixture;
use App\Services\Results\Contracts\WorldCupResultsProviderInterface;
use App\Services\Results\GeminiResultsService;
use App\Services\Results\OpenAiResultsService;
use Illuminate\Console\Command;
use RuntimeException;

final class SyncResults extends Command
{
    protected $signature = 'world-cup:sync-results
                            {--provider=gemini : Which AI provider to use (gemini|openai)}
                            {--dry-run : Preview changes without persisting them}
                            {--dummy : Show raw provider response and fixture preview without writing to the database}
                            {--data-only : Hit the provider directly and dump raw results, skipping all DB interaction}
                            {--specific-date= : With --data-only, restrict results to matches played on this date (YYYY-MM-DD)}
                            {--all : Fetch every completed World Cup 2026 result to date in simplified text format}';

    protected $description = 'Fetch World Cup 2026 match results from an AI provider and update fixtures';

    public function __construct(
        private readonly GeminiResultsService $geminiService,
        private readonly OpenAiResultsService $openaiService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $provider = $this->provider();

        if ($this->option('all')) {
            try {
                $raw = $provider->fetchRawResults(null, true);
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->line($raw);

            return self::SUCCESS;
        }

        if ($this->option('data-only')) {
            $specificDate = $this->option('specific-date');

            if ($specificDate !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $specificDate)) {
                $this->error('--specific-date must be in YYYY-MM-DD format (e.g. 2026-06-13).');

                return self::FAILURE;
            }

            try {
                $raw = $provider->fetchRawResults($specificDate);
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
            $results = $provider->fetchResults($fixtures);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $dummy = (bool) $this->option('dummy');
        $dryRun = $dummy || (bool) $this->option('dry-run');
        $providerLabel = ucfirst((string) $this->option('provider'));

        if ($dummy) {
            $this->outputDummyReport($fixtures->all(), $results, $providerLabel);
        }

        $synced = 0;
        $skipped = 0;
        $rows = [];

        foreach ($fixtures as $fixture) {
            $result = $results[$fixture->id] ?? null;

            $homeName = ($ht = $fixture->homeTeam) !== null ? $ht->name : ($fixture->home_team_placeholder ?? 'TBD');
            $awayName = ($at = $fixture->awayTeam) !== null ? $at->name : ($fixture->away_team_placeholder ?? 'TBD');

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
            $this->table(['Home', 'Score', 'Away', "{$providerLabel} Status", 'Action'], $rows);
            $this->info("Synced: {$synced}, Skipped: {$skipped}".($dryRun ? ' (dry-run)' : ''));
        }

        return self::SUCCESS;
    }

    private function provider(): WorldCupResultsProviderInterface
    {
        return match ($this->option('provider')) {
            'openai' => $this->openaiService,
            default => $this->geminiService,
        };
    }

    /**
     * @param  Fixture[]  $fixtures
     * @param  array<int, array{home_score: int|null, away_score: int|null, status: string}>  $results
     */
    private function outputDummyReport(array $fixtures, array $results, string $providerLabel): void
    {
        $this->newLine();
        $this->comment("=== RAW DATA RECEIVED FROM {$providerLabel} ===");

        $providerRows = array_map(fn (Fixture $fixture) => [
            $fixture->id,
            ($ht = $fixture->homeTeam) !== null ? $ht->name : ($fixture->home_team_placeholder ?? 'TBD'),
            $results[$fixture->id]['home_score'] ?? 'null',
            $results[$fixture->id]['away_score'] ?? 'null',
            ($at = $fixture->awayTeam) !== null ? $at->name : ($fixture->away_team_placeholder ?? 'TBD'),
            $results[$fixture->id]['status'] ?? 'no result',
        ], $fixtures);

        $this->table(['Fixture ID', 'Home Team', 'Home Score', 'Away Score', 'Away Team', 'Status'], $providerRows);

        $this->newLine();
        $this->comment('=== WOULD BE WRITTEN TO fixtures TABLE ===');

        $writeRows = [];

        foreach ($fixtures as $fixture) {
            $result = $results[$fixture->id] ?? null;
            $homeName = ($ht2 = $fixture->homeTeam) !== null ? $ht2->name : ($fixture->home_team_placeholder ?? 'TBD');
            $awayName = ($at2 = $fixture->awayTeam) !== null ? $at2->name : ($fixture->away_team_placeholder ?? 'TBD');

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
