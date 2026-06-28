<?php

namespace App\Console\Commands;

use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use App\Enums\KnockoutOutcome;
use App\Events\ResultImported;
use App\Listeners\RecalculateFixturePredictions;
use App\Listeners\RecalculateUserStats;
use App\Models\Fixture;
use App\Models\Prediction;
use Illuminate\Console\Command;

class DevSeedKnockoutResults extends Command
{
    private const OUTCOME_POOL = [
        KnockoutOutcome::HomeWin,
        KnockoutOutcome::HomeWin,
        KnockoutOutcome::HomeWin,
        KnockoutOutcome::HomeWin,
        KnockoutOutcome::AwayWin,
        KnockoutOutcome::AwayWin,
        KnockoutOutcome::AwayWin,
        KnockoutOutcome::AwayWin,
        KnockoutOutcome::HomeWinAet,
        KnockoutOutcome::HomeWinAet,
        KnockoutOutcome::AwayWinAet,
        KnockoutOutcome::AwayWinAet,
        KnockoutOutcome::HomeWinPens,
        KnockoutOutcome::HomeWinPens,
        KnockoutOutcome::AwayWinPens,
        KnockoutOutcome::AwayWinPens,
    ];

    protected $signature = 'dev:seed-knockout-results
                            {--stage=round_of_32 : Stage to process (round_of_32|round_of_16|quarter_final|semi_final|third_place|final|all)}
                            {--reset : Clear results instead of seeding them}
                            {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Seed or reset knockout match results with realistic outcome distribution (dev only)';

    public function handle(
        RecalculateFixturePredictions $scorePredictions,
        RecalculateUserStats $recalculateStats,
    ): int {
        $stageInput = (string) $this->option('stage');
        $reset = (bool) $this->option('reset');
        $dryRun = (bool) $this->option('dry-run');

        if ($reset) {
            return $this->handleReset($stageInput, $dryRun);
        }

        return $this->handleSeed($stageInput, $dryRun, $scorePredictions, $recalculateStats);
    }

    private function handleSeed(
        string $stageInput,
        bool $dryRun,
        RecalculateFixturePredictions $scorePredictions,
        RecalculateUserStats $recalculateStats,
    ): int {
        $stages = $this->parseStages($stageInput);

        if ($stages === null) {
            $this->error("Invalid stage \"{$stageInput}\". Use one of: round_of_32, round_of_16, quarter_final, semi_final, third_place, final, all");

            return self::FAILURE;
        }

        foreach ($stages as $stage) {
            $this->seedStage($stage, $dryRun, $scorePredictions, $recalculateStats);
        }

        return self::SUCCESS;
    }

    private function handleReset(string $stageInput, bool $dryRun): int
    {
        $stages = $this->parseStages($stageInput);

        if ($stages === null) {
            $this->error("Invalid stage \"{$stageInput}\". Use one of: round_of_32, round_of_16, quarter_final, semi_final, third_place, final, all");

            return self::FAILURE;
        }

        // Process in reverse order so downstream clears before upstream
        $stages = array_reverse($stages);

        foreach ($stages as $stage) {
            $this->resetStage($stage, $dryRun);
        }

        return self::SUCCESS;
    }

    /** @return FixtureStage[]|null */
    private function parseStages(string $stageInput): ?array
    {
        if ($stageInput === 'all') {
            return [
                FixtureStage::RoundOf32,
                FixtureStage::RoundOf16,
                FixtureStage::QuarterFinal,
                FixtureStage::SemiFinal,
                FixtureStage::ThirdPlace,
                FixtureStage::Final,
            ];
        }

        $stage = FixtureStage::tryFrom($stageInput);

        if ($stage === null || ! $stage->isKnockout()) {
            return null;
        }

        return [$stage];
    }

    private function seedStage(
        FixtureStage $stage,
        bool $dryRun,
        RecalculateFixturePredictions $scorePredictions,
        RecalculateUserStats $recalculateStats,
    ): void {
        $fixtures = Fixture::where('stage', $stage->value)
            ->orderBy('match_number')
            ->get();

        if ($fixtures->isEmpty()) {
            $this->warn("No {$stage->label()} fixtures found.");

            return;
        }

        $this->info("Seeding {$fixtures->count()} {$stage->label()} fixture(s)...");

        $pool = self::OUTCOME_POOL;
        shuffle($pool);
        $poolIndex = 0;

        $rows = [];
        $bar = $this->output->createProgressBar($fixtures->count());
        $bar->start();

        foreach ($fixtures as $fixture) {
            $outcome = $pool[$poolIndex % count($pool)];
            $poolIndex++;

            $scores = $this->generateScores($outcome);

            if (! $dryRun) {
                $fixture->update([
                    'home_score' => $scores['home_score'],
                    'away_score' => $scores['away_score'],
                    'home_score_aet' => $scores['home_score_aet'],
                    'away_score_aet' => $scores['away_score_aet'],
                    'home_score_pens' => $scores['home_score_pens'],
                    'away_score_pens' => $scores['away_score_pens'],
                    'status' => FixtureStatus::Completed,
                ]);

                $fixture->refresh();

                $event = new ResultImported($fixture);
                $scorePredictions->handle($event);
                $recalculateStats->handle($event);

                $this->advanceWinner($fixture, $outcome);
            }

            $rows[] = [
                $fixture->match_number,
                $this->formatTeamName($fixture->homeTeam?->name ?? $fixture->home_team_placeholder),
                $this->formatScore($scores),
                $this->formatTeamName($fixture->awayTeam?->name ?? $fixture->away_team_placeholder),
                $outcome->name,
                $dryRun ? 'dry-run' : 'updated',
            ];

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->table(['Match', 'Home', 'Score', 'Away', 'Outcome', 'Action'], $rows);
    }

    private function resetStage(FixtureStage $stage, bool $dryRun): void
    {
        $fixtures = Fixture::where('stage', $stage->value)
            ->orderBy('match_number')
            ->get();

        if ($fixtures->isEmpty()) {
            $this->warn("No {$stage->label()} fixtures found.");

            return;
        }

        $this->info("Resetting {$fixtures->count()} {$stage->label()} fixture(s)...");

        $matchNumbers = $fixtures->pluck('match_number')->toArray();

        if (! $dryRun) {
            // Clear scores on the stage being reset
            Fixture::whereIn('match_number', $matchNumbers)
                ->update([
                    'home_score' => null,
                    'away_score' => null,
                    'home_score_aet' => null,
                    'away_score_aet' => null,
                    'home_score_pens' => null,
                    'away_score_pens' => null,
                    'status' => FixtureStatus::Scheduled,
                ]);

            // Null out points on predictions for these fixtures
            Prediction::whereIn('fixture_id', $fixtures->pluck('id'))
                ->update(['points' => null]);

            // Clear downstream fixture team assignments
            $this->clearDownstream($matchNumbers);
        }

        $rows = [];

        foreach ($fixtures as $fixture) {
            $rows[] = [
                $fixture->match_number,
                $this->formatTeamName($fixture->homeTeam?->name ?? $fixture->home_team_placeholder),
                '— : —',
                $this->formatTeamName($fixture->awayTeam?->name ?? $fixture->away_team_placeholder),
                'reset',
                $dryRun ? 'dry-run' : 'updated',
            ];
        }

        $this->table(['Match', 'Home', 'Score', 'Away', 'Status', 'Action'], $rows);
    }

    /**
     * @param  array<int>  $matchNumbers
     */
    private function clearDownstream(array $matchNumbers): void
    {
        foreach ($matchNumbers as $matchNum) {
            Fixture::where('home_team_placeholder', "Winner Match {$matchNum}")
                ->update(['home_team_id' => null]);

            Fixture::where('away_team_placeholder', "Winner Match {$matchNum}")
                ->update(['away_team_id' => null]);

            Fixture::where('home_team_placeholder', "Loser Match {$matchNum}")
                ->update(['home_team_id' => null]);

            Fixture::where('away_team_placeholder', "Loser Match {$matchNum}")
                ->update(['away_team_id' => null]);
        }
    }

    private function advanceWinner(Fixture $fixture, KnockoutOutcome $outcome): void
    {
        try {
            $actualOutcome = KnockoutOutcome::fromFixture($fixture);
        } catch (\RuntimeException) {
            return;
        }

        $winner = $actualOutcome->winner() === 'home' ? $fixture->home_team_id : $fixture->away_team_id;
        $loser = $actualOutcome->winner() === 'home' ? $fixture->away_team_id : $fixture->home_team_id;

        if ($winner === null) {
            return;
        }

        $matchNum = $fixture->match_number;

        Fixture::where('home_team_placeholder', "Winner Match {$matchNum}")
            ->update(['home_team_id' => $winner]);

        Fixture::where('away_team_placeholder', "Winner Match {$matchNum}")
            ->update(['away_team_id' => $winner]);

        // Third place slot (only semi-final losers fill this)
        if ($loser !== null) {
            Fixture::where('home_team_placeholder', "Loser Match {$matchNum}")
                ->update(['home_team_id' => $loser]);

            Fixture::where('away_team_placeholder', "Loser Match {$matchNum}")
                ->update(['away_team_id' => $loser]);
        }
    }

    /**
     * @return array{home_score: int|null, away_score: int|null, home_score_aet: int|null, away_score_aet: int|null, home_score_pens: int|null, away_score_pens: int|null}
     */
    private function generateScores(KnockoutOutcome $outcome): array
    {
        $base = [
            'home_score_aet' => null,
            'away_score_aet' => null,
            'home_score_pens' => null,
            'away_score_pens' => null,
        ];

        $nonDrawScores = [[1, 0], [2, 0], [2, 1], [3, 0], [3, 1], [3, 2]];
        $drawScores = [[0, 0], [1, 1], [2, 2]];
        $penScores = [[4, 2], [4, 3], [5, 3], [5, 4]];

        $nd = $nonDrawScores[array_rand($nonDrawScores)];
        $d = $drawScores[array_rand($drawScores)];
        $p = $penScores[array_rand($penScores)];

        return match ($outcome) {
            KnockoutOutcome::HomeWin => [
                ...$base,
                'home_score' => $nd[0],
                'away_score' => $nd[1],
            ],
            KnockoutOutcome::AwayWin => [
                ...$base,
                'home_score' => $nd[1],
                'away_score' => $nd[0],
            ],
            KnockoutOutcome::HomeWinAet => [
                'home_score' => $d[0],
                'away_score' => $d[1],
                'home_score_aet' => $nonDrawScores[array_rand($nonDrawScores)][0],
                'away_score_aet' => $nonDrawScores[array_rand($nonDrawScores)][1],
                'home_score_pens' => null,
                'away_score_pens' => null,
            ],
            KnockoutOutcome::AwayWinAet => [
                'home_score' => $d[0],
                'away_score' => $d[1],
                'home_score_aet' => $nonDrawScores[array_rand($nonDrawScores)][1],
                'away_score_aet' => $nonDrawScores[array_rand($nonDrawScores)][0],
                'home_score_pens' => null,
                'away_score_pens' => null,
            ],
            KnockoutOutcome::HomeWinPens => [
                'home_score' => $d[0],
                'away_score' => $d[1],
                'home_score_aet' => $d[0],
                'away_score_aet' => $d[1],
                'home_score_pens' => $p[0],
                'away_score_pens' => $p[1],
            ],
            KnockoutOutcome::AwayWinPens => [
                'home_score' => $d[0],
                'away_score' => $d[1],
                'home_score_aet' => $d[0],
                'away_score_aet' => $d[1],
                'home_score_pens' => $p[1],
                'away_score_pens' => $p[0],
            ],
        };
    }

    private function formatScore(array $scores): string
    {
        $result = $scores['home_score'].' - '.$scores['away_score'];

        if ($scores['home_score_aet'] !== null) {
            $result .= ' (AET: '.$scores['home_score_aet'].' - '.$scores['away_score_aet'].')';
        }

        if ($scores['home_score_pens'] !== null) {
            $result .= ' (Pens: '.$scores['home_score_pens'].' - '.$scores['away_score_pens'].')';
        }

        return $result;
    }

    private function formatTeamName(?string $name): string
    {
        if ($name === null) {
            return 'TBD';
        }

        if (strlen($name) > 20) {
            return substr($name, 0, 17).'...';
        }

        return $name;
    }
}
