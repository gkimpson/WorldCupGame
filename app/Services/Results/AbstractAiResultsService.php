<?php

namespace App\Services\Results;

use App\Models\Fixture;
use App\Services\Results\Contracts\WorldCupResultsProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

abstract class AbstractAiResultsService implements WorldCupResultsProviderInterface
{
    public function __construct(protected readonly ResultsResponseParser $parser) {}

    /**
     * @param  Collection<int, Fixture>  $fixtures
     * @return array<int, array{home_score: int|null, away_score: int|null, status: string}>
     */
    public function fetchResults(Collection $fixtures): array
    {
        $fixtureList = $fixtures->map(fn (Fixture $fixture) => [
            'id' => $fixture->id,
            'home' => ($ht = $fixture->homeTeam) !== null ? $ht->name : ($fixture->home_team_placeholder ?? 'TBD'),
            'away' => ($at = $fixture->awayTeam) !== null ? $at->name : ($fixture->away_team_placeholder ?? 'TBD'),
            'date' => $fixture->scheduled_at?->format('Y-m-d') ?? '',
        ])->values()->all();

        $text = $this->call($this->buildPrompt($fixtureList));
        $decoded = json_decode($this->parser->extractJson($text), true);

        if (! is_array($decoded)) {
            Log::warning(static::class.': could not parse JSON response', ['response' => $text]);

            return [];
        }

        return $this->parser->normalise($decoded);
    }

    /** @throws RuntimeException */
    public function fetchRawResults(?string $specificDate = null, bool $allResults = false): string
    {
        $prompt = $allResults
            ? $this->buildAllRawPrompt()
            : $this->buildRawPrompt($specificDate);

        return $this->call($prompt);
    }

    /** @throws RuntimeException */
    abstract protected function call(string $prompt): string;

    /** @param array<int, array{id: int, home: string, away: string, date: string}> $fixtures */
    private function buildPrompt(array $fixtures): string
    {
        $json = json_encode($fixtures, JSON_PRETTY_PRINT);
        $aliasNote = $this->parser->buildAliasNote();

        return <<<PROMPT
        You are a sports data assistant. Search for FIFA World Cup 2026 match results.

        Below is a list of matches. For each match, search for the result only if the match is fully over.

        Matches:
        {$json}
        {$aliasNote}

        A match is ONLY considered complete when ALL of the following are true:
        - The full 90 minutes (plus any added time, extra time, or penalties) have been played.
        - The result is officially confirmed as full-time (FT), after extra time (AET), or after penalties (PEN).
        - You can find a confirmed final scoreline from a reliable source — not a live or in-progress score.
        - The match time shown in live sources is NOT a running clock (e.g. 45', 67', 90'+2).

        If there is any doubt — the match is live, the clock is still running, or the result is unconfirmed — DO NOT include it.

        Return ONLY a JSON array for fully completed matches. Omit everything else.

        Example format:
        [
          {"id": "01JXXXXXXXXXXXXXXXXXXXXXXXXX", "home_score": 2, "away_score": 0, "status": "completed"}
        ]

        Rules:
        - status must be "completed" — never "in_progress", "live", or any other value.
        - home_score and away_score must be integers of 0 or above (never negative, never null for a completed match).
        - If a match is ongoing, scheduled, postponed, or the result is uncertain, omit it entirely.
        PROMPT;
    }

    private function buildRawPrompt(?string $specificDate = null): string
    {
        $today = now()->format('l, F j, Y');
        $aliasNote = $this->parser->buildAliasNote();

        $completionRule = 'Only include a match if it is fully over — '
            .'the full 90 minutes (plus any added time, extra time, or penalties) have been played '
            .'and the result is officially confirmed as FT, AET, or PEN. '
            .'If the match clock is still running, the match is live, or the result is unconfirmed, skip it entirely. '
            .'For each confirmed completed match output exactly one line in this format: Team1 X - Y Team2 '
            .'where X and Y are the final scores as whole numbers of 0 or above (never negative, never blank). '
            .'Do not include any other text, headings, commentary, or explanation — only the result lines. ';

        if ($specificDate !== null) {
            $label = Carbon::parse($specificDate)->format('l, F j, Y');

            return "Today is {$today}. The FIFA World Cup 2026 is currently underway. "
                ."Search for results ONLY for matches played on {$label} ({$specificDate}). "
                ."Search for \"FIFA World Cup 2026 results {$specificDate}\" and \"World Cup 2026 scores {$label}\". "
                .$completionRule
                .'Do not include matches from any other date. '
                ."Do not say results are unavailable — search and report what you find.\n{$aliasNote}";
        }

        return "Today is {$today}. The FIFA World Cup 2026 is currently underway. "
            .'Search for the latest match results right now. '
            .'Search for "FIFA World Cup 2026 results today" and "World Cup 2026 scores". '
            .$completionRule
            ."Do not say results are unavailable — search and report what you find.\n{$aliasNote}";
    }

    private function buildAllRawPrompt(): string
    {
        $today = now()->format('l, F j, Y');
        $aliasNote = $this->parser->buildAliasNote();

        return "Today is {$today}. The FIFA World Cup 2026 is currently underway. "
            .'Search for every completed match result from the entire tournament so far. '
            .'Use at least TWO independent sources (e.g. FIFA.com, BBC Sport, ESPN, Google Sports, Sky Sports) and only include a result if BOTH sources agree on the final scoreline. '
            .'First search "FIFA World Cup 2026 all results" and "FIFA World Cup 2026 scores", then verify each result with a second search. '
            ."\n\n"
            .'STRICT INCLUSION RULES — a match MUST satisfy ALL of the following to be included:'
            ."\n"
            .'- The full 90 minutes (plus any added time, extra time, or penalties) have been played.'
            ."\n"
            .'- The result is officially confirmed as FT, AET, or PEN.'
            ."\n"
            .'- A confirmed final scoreline is available from at least two independent reliable sources and both agree.'
            ."\n\n"
            .'STRICT EXCLUSION RULES — immediately discard any match that:'
            ."\n"
            .'- Has not yet kicked off (scheduled or upcoming).'
            ."\n"
            .'- Is currently in progress (live clock, half-time, extra time in progress, penalties in progress).'
            ."\n"
            .'- Shows a running minute (e.g. 23\', 45+2\', 90\') in any live source.'
            ."\n"
            .'- Has an unconfirmed or disputed result.'
            ."\n"
            .'- Cannot be verified by a second source.'
            ."\n\n"
            .'For each match that passes ALL inclusion rules, output exactly one line in this format:'
            ."\n"
            .'YYYY-MM-DD HH:MM Team1 X - Y Team2'
            ."\n"
            .'where YYYY-MM-DD is the match date, HH:MM is the kick-off time in UTC, and X and Y are the final integer scores (0 or above, never blank).'
            ."\n\n"
            .'Do not output headers, commentary, explanations, or any other text — only the result lines. '
            ."Do not say results are unavailable — search and report what you find.\n{$aliasNote}";
    }
}
