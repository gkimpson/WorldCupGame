<?php

namespace App\Console\Commands;

use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Team;
use App\Services\Results\ResultsResponseParser;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class ResolveKnockoutTeams extends Command
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    /** Maps AI/FIFA alternate names to canonical DB names in the teams table. */
    private const TEAM_ALIASES = [
        'Türkiye' => 'Turkey',
        'Holland' => 'Netherlands',
        "Côte d'Ivoire" => 'Ivory Coast',
        "Cote d'Ivoire" => 'Ivory Coast',
        'IR Iran' => 'Iran',
        'Korea Republic' => 'South Korea',
        'Cabo Verde' => 'Cape Verde',
        'Czechia' => 'Czech Republic',
        'USA' => 'United States',
        'US' => 'United States',
        'Democratic Republic of Congo' => 'DR Congo',
        'Congo DR' => 'DR Congo',
        'Bosnia & Herzegovina' => 'Bosnia and Herzegovina',
        'BiH' => 'Bosnia and Herzegovina',
    ];

    protected $signature = 'world-cup:resolve-knockout-teams
                            {--stage=round_of_32 : The knockout stage to resolve (round_of_32, round_of_16, quarter_final, semi_final, third_place, final)}
                            {--dry-run : Print the resolved matchups without writing to the database}
                            {--data-only : Hit Gemini directly and dump the raw response without any DB interaction}';

    protected $description = 'Use Gemini AI to resolve which teams play in each knockout round fixture once the preceding stage is complete';

    public function __construct(private readonly ResultsResponseParser $parser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $stageValue = (string) $this->option('stage');
        $stage = FixtureStage::tryFrom($stageValue);

        if ($stage === null || ! $stage->isKnockout()) {
            $valid = implode(', ', array_map(
                fn (FixtureStage $s) => $s->value,
                array_filter(FixtureStage::cases(), fn (FixtureStage $s) => $s->isKnockout()),
            ));
            $this->error("Invalid stage \"{$stageValue}\". Must be one of: {$valid}");

            return self::FAILURE;
        }

        if ($this->option('data-only')) {
            return $this->handleDataOnly($stage);
        }

        if (! $this->prerequisiteStageComplete($stage)) {
            return self::FAILURE;
        }

        $fixtures = Fixture::query()
            ->where('stage', $stage->value)
            ->whereNull('home_team_id')
            ->get();

        if ($fixtures->isEmpty()) {
            $this->info("All {$stage->label()} fixtures already have teams assigned.");

            return self::SUCCESS;
        }

        $this->info("Asking Gemini to resolve {$fixtures->count()} {$stage->label()} fixture(s)...");

        try {
            $assignments = $this->fetchAssignments($fixtures, $stage);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (empty($assignments)) {
            $this->warn('Gemini returned no assignments. Try again or assign manually.');

            return self::FAILURE;
        }

        $teamIndex = Team::query()->pluck('id', 'name');
        $dryRun = (bool) $this->option('dry-run');
        $resolved = 0;
        $unresolved = 0;
        $rows = [];

        foreach ($fixtures as $fixture) {
            $assignment = $assignments[$fixture->id] ?? null;

            if ($assignment === null) {
                $unresolved++;
                $rows[] = [$fixture->id, '—', '—', 'no assignment from AI'];

                continue;
            }

            $homeId = $this->resolveTeamId((string) $assignment['home_team'], $teamIndex);
            $awayId = $this->resolveTeamId((string) $assignment['away_team'], $teamIndex);

            if ($homeId === null || $awayId === null) {
                $unresolved++;
                $rows[] = [
                    $fixture->id,
                    $assignment['home_team'],
                    $assignment['away_team'],
                    'team name not found in DB',
                ];

                continue;
            }

            $rows[] = [
                $fixture->id,
                $assignment['home_team'],
                $assignment['away_team'],
                $dryRun ? 'dry-run' : 'updated',
            ];

            if (! $dryRun) {
                $fixture->update([
                    'home_team_id' => $homeId,
                    'away_team_id' => $awayId,
                ]);
            }

            $resolved++;
        }

        $this->table(['Fixture ID', 'Home Team', 'Away Team', 'Action'], $rows);
        $this->info("Resolved: {$resolved}, Unresolved: {$unresolved}".($dryRun ? ' (dry-run)' : ''));

        return $unresolved === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function handleDataOnly(FixtureStage $stage): int
    {
        $fixtures = Fixture::query()
            ->where('stage', $stage->value)
            ->whereNull('home_team_id')
            ->get();

        if ($fixtures->isEmpty()) {
            $this->info("All {$stage->label()} fixtures already have teams assigned.");

            return self::SUCCESS;
        }

        try {
            $raw = $this->callGemini($this->buildPrompt($fixtures, $stage));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($raw);

        return self::SUCCESS;
    }

    private function prerequisiteStageComplete(FixtureStage $stage): bool
    {
        $prerequisite = match ($stage) {
            FixtureStage::RoundOf32 => FixtureStage::GroupStage,
            FixtureStage::RoundOf16 => FixtureStage::RoundOf32,
            FixtureStage::QuarterFinal => FixtureStage::RoundOf16,
            FixtureStage::SemiFinal => FixtureStage::QuarterFinal,
            FixtureStage::ThirdPlace, FixtureStage::Final => FixtureStage::SemiFinal,
            default => null,
        };

        if ($prerequisite === null) {
            return true;
        }

        $incomplete = Fixture::query()
            ->where('stage', $prerequisite->value)
            ->where('status', '!=', FixtureStatus::Completed->value)
            ->count();

        if ($incomplete > 0) {
            $this->error(
                "Cannot resolve {$stage->label()} yet: {$incomplete} {$prerequisite->label()} fixture(s) are not completed."
            );

            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, Fixture>  $fixtures
     * @return array<int, array{home_team: string, away_team: string}>
     */
    private function fetchAssignments(Collection $fixtures, FixtureStage $stage): array
    {
        $text = $this->callGemini($this->buildPrompt($fixtures, $stage));
        $decoded = json_decode($this->parser->extractJson($text), true);

        if (! is_array($decoded)) {
            Log::warning('ResolveKnockoutTeams: could not parse JSON from Gemini', ['response' => $text]);

            return [];
        }

        $assignments = [];

        foreach ($decoded as $item) {
            if (! isset($item['fixture_id'], $item['home_team'], $item['away_team'])) {
                continue;
            }

            $assignments[(int) $item['fixture_id']] = [
                'home_team' => (string) $item['home_team'],
                'away_team' => (string) $item['away_team'],
            ];
        }

        return $assignments;
    }

    /** @param Collection<int, Fixture> $fixtures */
    private function buildPrompt(Collection $fixtures, FixtureStage $stage): string
    {
        $today = now()->format('l, F j, Y');
        $stageLabel = $stage->label();
        $aliasNote = $this->parser->buildAliasNote();

        $fixtureList = $fixtures->map(fn (Fixture $f) => [
            'fixture_id' => $f->id,
            'match_number' => $f->match_number,
            'home_placeholder' => $f->home_team_placeholder ?? 'TBD',
            'away_placeholder' => $f->away_team_placeholder ?? 'TBD',
            'scheduled_at' => $f->scheduled_at?->format('Y-m-d H:i') ?? 'TBD',
        ])->values()->all();

        $json = json_encode($fixtureList, JSON_PRETTY_PRINT);

        return <<<PROMPT
        Today is {$today}. The FIFA World Cup 2026 is underway and the preceding stage is now complete.

        I need to assign teams to the following {$stageLabel} fixtures. Use Google Search to find:
        1. The official FIFA World Cup 2026 {$stageLabel} bracket and seeding rules — specifically which group winners, runners-up, or match winners play each other in which slot.
        2. The final standings from the completed preceding stage to confirm exactly which team occupies each position.

        Fixtures needing teams assigned:
        {$json}
        {$aliasNote}

        Return ONLY a JSON array. For each fixture where you can confirm BOTH teams with certainty, include one entry.
        If you cannot confirm a team assignment with certainty for a fixture, omit it entirely — do not guess.

        Example format:
        [
          {"fixture_id": 123, "home_team": "Mexico", "away_team": "France"}
        ]

        Rules:
        - fixture_id must exactly match one of the IDs provided above.
        - home_team and away_team must be real team names (not placeholders like "TBD" or "Winner Group A").
        - Only include a fixture if you are certain of both team assignments based on confirmed official results.
        - Do not include any other text — only the JSON array.
        PROMPT;
    }

    private function callGemini(string $prompt): string
    {
        $apiKey = (string) config('services.gemini.key');
        $model = (string) config('services.gemini.model', 'gemini-2.5-flash');

        $response = Http::post(
            self::API_BASE."/{$model}:generateContent?key={$apiKey}",
            [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $prompt]]],
                ],
                'tools' => [['google_search' => (object) []]],
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException("Gemini API request failed with status {$response->status()}: {$response->body()}");
        }

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }

    /** @param Collection<string, int> $teamIndex */
    private function resolveTeamId(string $name, Collection $teamIndex): ?int
    {
        $canonical = self::TEAM_ALIASES[$name] ?? $name;
        $id = $teamIndex->get($canonical);

        if ($id === null) {
            Log::warning('ResolveKnockoutTeams: team not found in DB', ['name' => $name, 'canonical' => $canonical]);
        }

        return $id !== null ? (int) $id : null;
    }
}
