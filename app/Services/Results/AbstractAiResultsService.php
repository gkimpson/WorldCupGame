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
          {
            "id": "01JXXXXXXXXXXXXXXXXXXXXXXXXX",
            "home_score": 1,
            "away_score": 1,
            "home_score_aet": 1,
            "away_score_aet": 2,
            "home_score_pens": null,
            "away_score_pens": null,
            "status": "completed"
          }
        ]

        Rules:
        - status must be "completed" — never "in_progress", "live", or any other value.
        - home_score and away_score are the scores at exactly 90 minutes (including stoppage time, never AET), must be integers 0 or above.
        - home_score_aet and away_score_aet are the scores at end of extra time if the match went to AET; otherwise null. Only used for knockout rounds.
        - home_score_pens and away_score_pens are the number of penalties scored if the match went to a penalty shootout; otherwise null. Only used for knockout rounds.
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

        $fifaUrl = 'https://www.fifa.com/en/tournaments/mens/worldcup/canadamexicousa2026/scores-fixtures?utm_source=openai&country=GB&wtw-filter=ALL';

        $tournamentStart = 'June 11, 2026';
        $daysSinceStart = now()->diffInDays('2026-06-11');

        return "Today is {$today}. The FIFA World Cup 2026 started on {$tournamentStart}. "
            ."Today's date ({$today}) is {$daysSinceStart} days after the tournament started — matches have been played and completed. "
            .'Do NOT say the tournament has not started or that results are unavailable — you can verify this yourself by comparing today\'s date to the start date. '
            .'Search for every completed match result from the entire tournament so far. '
            .'Use these searches in order: '
            .'(1) "FIFA World Cup 2026 results" to find the official FIFA scores page — the authoritative URL is '.$fifaUrl.' '
            .'(2) "World Cup 2026 scores BBC Sport" or "World Cup 2026 results ESPN" as a cross-check. '
            .'FIFA.com is the single authoritative source — if FIFA and the secondary source disagree on a scoreline, trust FIFA. '
            .'Only omit a match if it genuinely cannot be found on FIFA.com. '
            ."\n\n"
            .'STRICT INCLUSION RULES — a match MUST satisfy ALL of the following to be included:'
            ."\n"
            .'- The full 90 minutes (plus any added time, extra time, or penalties) have been played.'
            ."\n"
            .'- The result is officially confirmed as FT, AET, or PEN.'
            ."\n"
            .'- A confirmed final scoreline is available from a reliable source.'
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
            ."\n\n"
            .'For each match that passes ALL inclusion rules, output exactly one line in this format:'
            ."\n"
            .'Team1 X - Y Team2'
            ."\n"
            .'where X and Y are the final integer scores (0 or above, never blank). Example: England 2 - 0 Germany'
            ."\n\n"
            .'Do not output headers, commentary, explanations, or any other text — only the result lines. '
            ."Do not say results are unavailable — search and report what you find.\n{$aliasNote}";
    }
}
