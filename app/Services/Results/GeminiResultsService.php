<?php

namespace App\Services\Results;

use App\Models\Fixture;
use App\Services\Results\Contracts\WorldCupResultsProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class GeminiResultsService implements WorldCupResultsProviderInterface
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ResultsResponseParser $parser,
    ) {}

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

        $response = Http::post(
            self::API_BASE."/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $this->buildPrompt($fixtureList)]],
                    ],
                ],
                'tools' => [['google_search' => (object) []]],
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException("Gemini API request failed with status {$response->status()}: {$response->body()}");
        }

        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        $decoded = json_decode($this->parser->extractJson($text), true);

        if (! is_array($decoded)) {
            Log::warning('GeminiResultsService: could not parse JSON response from Gemini', ['response' => $text]);

            return [];
        }

        return $this->parser->normalise($decoded);
    }

    /** @throws RuntimeException */
    public function fetchRawResults(?string $specificDate = null): string
    {
        $response = Http::post(
            self::API_BASE."/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $this->buildRawPrompt($specificDate)]],
                    ],
                ],
                'tools' => [['google_search' => (object) []]],
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException("Gemini API request failed with status {$response->status()}: {$response->body()}");
        }

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
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
                ."Use Google Search to find results ONLY for matches played on {$label} ({$specificDate}). "
                ."Search for \"FIFA World Cup 2026 results {$specificDate}\" and \"World Cup 2026 scores {$label}\". "
                .$completionRule
                .'Do not include matches from any other date. '
                ."Do not say results are unavailable — search and report what you find.\n{$aliasNote}";
        }

        return "Today is {$today}. The FIFA World Cup 2026 is currently underway. "
            .'Use Google Search to find the latest match results right now. '
            .'Search for "FIFA World Cup 2026 results today" and "World Cup 2026 scores". '
            .$completionRule
            ."Do not say results are unavailable — search and report what you find.\n{$aliasNote}";
    }

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
}
